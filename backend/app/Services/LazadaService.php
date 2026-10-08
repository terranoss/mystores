<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceConfig;

class LazadaService
{
    /** Maximum seconds to establish the connection to Lazada's auth endpoint. */
    private const AUTH_CONNECT_TIMEOUT_SECONDS = 10;

    /** Maximum seconds for a full token request (connect + transfer). */
    private const AUTH_REQUEST_TIMEOUT_SECONDS = 30;

    /** @var array<string, string> */
    private array $regionalBaseUrls = [
        'sg' => 'https://api.lazada.sg/rest',
        'my' => 'https://api.lazada.com.my/rest',
        'th' => 'https://api.lazada.co.th/rest',
        'ph' => 'https://api.lazada.com.ph/rest',
        'id' => 'https://api.lazada.co.id/rest',
        'vn' => 'https://api.lazada.vn/rest',
        'cb' => 'https://api.lazada.sg/rest',
    ];

    private WorkspaceAccess $workspaceAccess;
    private WorkspaceConfig $workspaceConfig;
    private ?int $activeWorkspaceId = null;

    /**
     * @param WorkspaceAccess|null $workspaceAccess
     * @param WorkspaceConfig|null $workspaceConfig
     */
    public function __construct(
        ?WorkspaceAccess $workspaceAccess = null,
        ?WorkspaceConfig $workspaceConfig = null
    ) {
        $this->workspaceAccess = $workspaceAccess ?? app(WorkspaceAccess::class);
        $this->workspaceConfig = $workspaceConfig ?? app(WorkspaceConfig::class);
    }

    /**
     * Set an explicit active workspace ID for the service context (useful in background jobs / CLI).
     */
    public function setActiveWorkspaceId(?int $workspaceId): self
    {
        $this->activeWorkspaceId = $workspaceId;
        return $this;
    }

    /**
     * Run a callback within the context of a specific workspace.
     */
    public function withWorkspace(int $workspaceId, callable $callback): mixed
    {
        $previous = $this->activeWorkspaceId;
        $this->activeWorkspaceId = $workspaceId;
        try {
            return $callback($this);
        } finally {
            $this->activeWorkspaceId = $previous;
        }
    }

    /**
     * Get the current user's workspace ID
     */
    private function getCurrentWorkspaceId(): ?int
    {
        if ($this->activeWorkspaceId !== null) {
            return $this->activeWorkspaceId;
        }

        $user = auth()->user();
        if (!$user) {
            return null;
        }

        $workspace = $this->workspaceAccess->ensurePersonalWorkspace($user);
        return $workspace->id;
    }

    /**
     * Get Lazada configuration for the current user's workspace
     */
    private function getCurrentWorkspaceLazadaConfig(): array
    {
        $workspaceId = $this->getCurrentWorkspaceId();
        if ($workspaceId) {
            return $this->workspaceConfig->getLazadaConfig($workspaceId);
        }

        // Fallback to global config
        return [
            'app_key' => config('lazada.app_key'),
            'app_secret' => config('lazada.app_secret'),
            'redirect_url' => config('lazada.redirect_url', 'https://mystores.melur.com/api/lazada/callback'),
            'sandbox' => config('lazada.sandbox', false),
        ];
    }

    /**
     * Get Lazada configuration for a specific workspace
     */
    private function getWorkspaceLazadaConfig(int $workspaceId): array
    {
        if ($workspaceId) {
            return $this->workspaceConfig->getLazadaConfig($workspaceId);
        }

        // Fallback to global config
        return [
            'app_key' => config('lazada.app_key'),
            'app_secret' => config('lazada.app_secret'),
            'redirect_url' => config('lazada.redirect_url', 'https://mystores.melur.com/api/lazada/callback'),
            'sandbox' => config('lazada.sandbox', false),
        ];
    }

    public function getAppKey(): string
    {
        return $this->requireConfigValue('app_key');
    }

    public function getRedirectUrl(): string
    {
        $config = $this->getCurrentWorkspaceLazadaConfig();
        return (string) ($config['redirect_url'] ?? '');
    }

    private function getAppSecret(): string
    {
        return $this->requireConfigValue('app_secret');
    }

    private function requireConfigValue(string $key): string
    {
        $config = $this->getCurrentWorkspaceLazadaConfig();
        $value = trim((string) ($config[$key] ?? ''));
        if ($value === '') {
            throw new \RuntimeException("Missing Lazada configuration: lazada.{$key}. Set the corresponding LAZADA_" . strtoupper($key) . " environment variable or configure in Dashboard Settings.");
        }

        return $value;
    }

    /**
     * Compute Lazada HMAC-SHA256 request signature.
     *
     * Algorithm:
     *  1. Sort all parameter keys in ASCII (alphabetical) order.
     *  2. Prepend the API path (e.g. '/auth/token/create').
     *  3. Concatenate: path + key1 + val1 + key2 + val2 + ...
     *  4. HMAC-SHA256 with app_secret, return uppercase hex.
     */
    public function generateSignature(string $apiPath, array $params): string
    {
        unset($params['sign']);
        ksort($params);

        $stringToSign = $apiPath;
        foreach ($params as $key => $value) {
            $stringToSign .= $key . $value;
        }

        return strtoupper(hash_hmac('sha256', $stringToSign, $this->getAppSecret()));
    }

    /**
     * Generate the Lazada OAuth redirect URL (seller authorization page).
     * The state value is embedded in the redirect_uri so Lazada passes it back.
     */
    public function getAuthUrl(string $state): string
    {
        // Encode state into the redirect URI so it comes back in the callback
        $redirectUri = $this->getRedirectUrl() . '?state=' . urlencode($state);

        $query = http_build_query([
            'response_type' => 'code',
            'force_auth'    => 'true',
            'redirect_uri'  => $redirectUri,
            'client_id'     => $this->getAppKey(),
            'app_key'       => $this->getAppKey(),
        ]);

        return 'https://auth.lazada.com/oauth/authorize?' . $query;
    }

    /**
     * Exchange an authorization code for access + refresh tokens.
     * Endpoint: POST https://auth.lazada.com/rest/auth/token/create
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, refresh_expires_in: int, country: string, account: string, seller_id?: string|int}
     * @throws \RuntimeException
     */
    public function createToken(string $code): array
    {
        $apiPath = '/auth/token/create';

        $params = [
            'app_key'     => $this->getAppKey(),
            'code'        => $code,
            'timestamp'   => (string) (int) (microtime(true) * 1000),
            'sign_method' => 'sha256',
        ];
        $params['sign'] = $this->generateSignature($apiPath, $params);

        $response = Http::asForm()->post('https://auth.lazada.com/rest' . $apiPath, $params);

        if (!$response->successful()) {
            Log::error('Lazada createToken HTTP error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Lazada token service returned HTTP ' . $response->status());
        }

        $data = $response->json() ?? [];

        // Lazada returns code "0" on success; other values indicate errors.
        if (isset($data['code']) && $data['code'] !== '0') {
            Log::error('Lazada createToken API error', $data);
            throw new \RuntimeException($data['message'] ?? 'Lazada token creation failed (code: ' . $data['code'] . ')');
        }

        return $data;
    }

    /**
     * Refresh an existing access token.
     * Endpoint: POST https://auth.lazada.com/rest/auth/token/refresh
     *
     * Lazada rotates the refresh token on every call, so timeouts are pinned
     * explicitly (rather than inherited from framework defaults) to keep the
     * worst-case round trip bounded and predictable.
     *
     * @throws \RuntimeException
     */
    public function refreshToken(string $refreshToken): array
    {
        $apiPath = '/auth/token/refresh';

        $params = [
            'app_key'       => $this->getAppKey(),
            'refresh_token' => $refreshToken,
            'timestamp'     => (string) (int) (microtime(true) * 1000),
            'sign_method'   => 'sha256',
        ];
        $params['sign'] = $this->generateSignature($apiPath, $params);

        $response = Http::asForm()
            ->connectTimeout(self::AUTH_CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::AUTH_REQUEST_TIMEOUT_SECONDS)
            ->post('https://auth.lazada.com/rest' . $apiPath, $params);

        if (!$response->successful()) {
            throw new \RuntimeException('Lazada token refresh HTTP error ' . $response->status());
        }

        $data = $response->json() ?? [];

        if (isset($data['code']) && $data['code'] !== '0') {
            throw new \RuntimeException($data['message'] ?? 'Lazada token refresh failed (code: ' . $data['code'] . ')');
        }

        return $data;
    }

    /**
     * Make an authenticated call to a Lazada regional REST API.
     *
     * @throws \RuntimeException
     */
    public function callApi(string $apiPath, array $businessParams, string $accessToken, string $region = 'sg'): array
    {
        $baseUrl = $this->regionalBaseUrls[strtolower($region)] ?? $this->regionalBaseUrls['sg'];

        $params = array_merge($businessParams, [
            'app_key'      => $this->getAppKey(),
            'access_token' => $accessToken,
            'timestamp'    => (string) (int) (microtime(true) * 1000),
            'sign_method'  => 'sha256',
        ]);
        $params['sign'] = $this->generateSignature($apiPath, $params);

        $response = Http::get($baseUrl . $apiPath, $params);

        if (!$response->successful()) {
            throw new \RuntimeException("Lazada API {$apiPath} HTTP error " . $response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * POST to a Lazada regional REST API (form-encoded business params).
     *
     * @throws \RuntimeException
     */
    public function callApiPost(string $apiPath, array $businessParams, string $accessToken, string $region = 'sg'): array
    {
        $baseUrl = $this->regionalBaseUrls[strtolower($region)] ?? $this->regionalBaseUrls['sg'];

        $params = array_merge($businessParams, [
            'app_key' => $this->getAppKey(),
            'access_token' => $accessToken,
            'timestamp' => (string) (int) (microtime(true) * 1000),
            'sign_method' => 'sha256',
        ]);
        $params['sign'] = $this->generateSignature($apiPath, $params);

        $response = Http::asForm()->post($baseUrl . $apiPath, $params);

        if (!$response->successful()) {
            throw new \RuntimeException("Lazada API {$apiPath} HTTP error " . $response->status());
        }

        $data = $response->json() ?? [];
        if (isset($data['code']) && (string) $data['code'] !== '0') {
            throw new \RuntimeException($data['message'] ?? "Lazada API {$apiPath} failed (code: {$data['code']})");
        }

        return $data;
    }

    /**
     * @return array{access_token:string,region:string}
     */
    public function credentialsForStore(\App\Models\ShopeeStore $store): array
    {
        $store->loadMissing('token');
        if (!$store->token || !$store->is_active) {
            throw new \RuntimeException("Store #{$store->id} token missing or inactive.");
        }

        return [
            'access_token' => decrypt($store->token->access_token),
            'region' => $store->region ?? 'sg',
        ];
    }

    /**
     * Get seller profile for the current access token.
     * Endpoint: GET /seller/get — data.name is the shop display name.
     */
    public function getSeller(string $accessToken, string $region): array
    {
        return $this->callApi('/seller/get', [], $accessToken, $region);
    }

    /**
     * Resolve Lazada shop display name (never use account email as the name).
     */
    public function resolveShopName(string $accessToken, string $region): ?string
    {
        try {
            $response = $this->getSeller($accessToken, $region);
            if (isset($response['code']) && (string) $response['code'] !== '0') {
                Log::warning('Lazada getSeller failed', [
                    'code' => $response['code'] ?? null,
                    'message' => $response['message'] ?? null,
                ]);

                return null;
            }
            $name = trim((string) ($response['data']['name'] ?? ''));

            return $name !== '' ? $name : null;
        } catch (\Throwable $e) {
            Log::warning('Lazada getSeller exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function getOrdersList(string $accessToken, string $region, array $params): array
    {
        $normalized = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $normalized[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return $this->callApi('/orders/get', $normalized, $accessToken, $region);
    }

    public function getOrder(string $accessToken, string $region, string|int $orderId): array
    {
        return $this->callApi('/order/get', [
            'order_id' => (string) $orderId,
        ], $accessToken, $region);
    }

    public function getOrderItems(string $accessToken, string $region, string|int $orderId): array
    {
        return $this->callApi('/order/items/get', [
            'order_id' => (string) $orderId,
        ], $accessToken, $region);
    }

    /**
     * @param  list<int|string>  $orderItemIds
     */
    public function cancelOrderItems(
        string $accessToken,
        string $region,
        string|int $orderId,
        array $orderItemIds,
        string $reasonId,
        ?string $reasonDetail = null,
    ): array {
        $payload = [
            'order_id' => (string) $orderId,
            'order_item_id_list' => array_map('strval', array_values($orderItemIds)),
            'reason_id' => $reasonId,
        ];
        if ($reasonDetail) {
            $payload['reason_detail'] = $reasonDetail;
        }

        return $this->callApiPost('/order/cancel', [
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ], $accessToken, $region);
    }

    /**
     * @param  list<int|string>  $orderItemIds
     */
    public function getShipmentProviders(
        string $accessToken,
        string $region,
        string|int $orderId,
        array $orderItemIds,
    ): array {
        $req = [
            'orders' => [[
                'order_id' => (int) $orderId,
                'order_item_ids' => array_map('intval', array_values($orderItemIds)),
            ]],
        ];

        return $this->callApiPost('/order/shipment/providers/get', [
            'getShipmentProvidersReq' => json_encode($req, JSON_UNESCAPED_UNICODE),
        ], $accessToken, $region);
    }

    /**
     * @param  list<int|string>  $orderItemIds
     */
    public function packOrder(
        string $accessToken,
        string $region,
        string|int $orderId,
        array $orderItemIds,
        string $shippingAllocateType = 'TFS',
        ?string $shipmentProviderCode = null,
    ): array {
        $packReq = [
            'pack_order_list' => [[
                'order_id' => (int) $orderId,
                'order_item_list' => array_map('intval', array_values($orderItemIds)),
            ]],
            'delivery_type' => 'dropship',
            'shipping_allocate_type' => $shippingAllocateType,
        ];
        if ($shipmentProviderCode) {
            $packReq['shipment_provider_code'] = $shipmentProviderCode;
        }

        return $this->callApiPost('/order/fulfill/pack', [
            'packReq' => json_encode($packReq, JSON_UNESCAPED_UNICODE),
        ], $accessToken, $region);
    }

    /**
     * @param  list<string>  $packageIds
     */
    public function printAwb(string $accessToken, string $region, array $packageIds, string $docType = 'PDF'): array
    {
        $req = [
            'doc_type' => $docType,
            'packages' => array_map(fn ($id) => ['package_id' => (string) $id], array_values($packageIds)),
        ];

        return $this->callApiPost('/order/package/document/get', [
            'getDocumentReq' => json_encode($req, JSON_UNESCAPED_UNICODE),
        ], $accessToken, $region);
    }

    /**
     * @param  list<string>  $packageIds
     */
    public function readyToShip(string $accessToken, string $region, array $packageIds): array
    {
        $req = [
            'packages' => array_map(fn ($id) => ['package_id' => (string) $id], array_values($packageIds)),
        ];

        return $this->callApiPost('/order/package/rts', [
            'readyToShipReq' => json_encode($req, JSON_UNESCAPED_UNICODE),
        ], $accessToken, $region);
    }

    /**
     * Get seller products from Lazada API.
     * Endpoint: GET /products/get
     */
    public function getProducts(string $accessToken, string $region = 'sg', int $offset = 0, int $limit = 50, string $filter = 'all'): array
    {
        return $this->callApi('/products/get', [
            'filter' => $filter,
            'offset' => (string) $offset,
            'limit'  => (string) $limit,
        ], $accessToken, $region);
    }

    /**
     * Get seller item detail by item_id from Lazada API.
     * Endpoint: GET /product/item/get
     */
    public function getItemDetail(string $accessToken, string $region, int $itemId): array
    {
        return $this->callApi('/product/item/get', [
            'item_id' => (string) $itemId,
        ], $accessToken, $region);
    }

    /**
     * Update price and/or quantity of SKUs for a product on Lazada.
     * Endpoint: POST /product/price_quantity/update
     * Accepts payload_xml or array of SKU updates.
     */
    public function updatePriceQuantity(string $accessToken, string $region, string $payloadXml): array
    {
        $apiPath = '/product/price_quantity/update';
        $baseUrl = $this->regionalBaseUrls[strtolower($region)] ?? $this->regionalBaseUrls['sg'];

        $params = [
            'app_key'      => $this->getAppKey(),
            'access_token' => $accessToken,
            'timestamp'    => (string) (int) (microtime(true) * 1000),
            'sign_method'  => 'sha256',
            'payload'      => $payloadXml,
        ];
        $params['sign'] = $this->generateSignature($apiPath, $params);

        $response = Http::asForm()->post($baseUrl . $apiPath, $params);

        if (!$response->successful()) {
            throw new \RuntimeException("Lazada updatePriceQuantity HTTP error " . $response->status());
        }

        $data = $response->json() ?? [];
        if (isset($data['code']) && $data['code'] !== '0') {
            throw new \RuntimeException($data['message'] ?? 'Lazada updatePriceQuantity failed (code: ' . $data['code'] . ')');
        }

        return $data;
    }

    /**
     * Build the XML payload for Lazada price/quantity updates.
     *
     * @param  list<array{SellerSku?:string,SkuId?:int|string,ItemId?:int|string,Quantity?:int,Price?:float|string}>  $skuUpdates
     */
    public function buildPriceQuantityPayload(array $skuUpdates): string
    {
        if ($skuUpdates === []) {
            throw new \InvalidArgumentException('Lazada update payload requires at least one SKU update.');
        }

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Request></Request>');
        $productNode = $xml->addChild('Product');
        $skusNode = $productNode->addChild('Skus');

        foreach ($skuUpdates as $update) {
            $skuNode = $skusNode->addChild('Sku');

            if (!empty($update['ItemId'])) {
                $skuNode->addChild('ItemId', (string) $update['ItemId']);
            }

            if (!empty($update['SkuId'])) {
                $skuNode->addChild('SkuId', (string) $update['SkuId']);
            }

            if (!empty($update['SellerSku'])) {
                $skuNode->addChild('SellerSku', htmlspecialchars((string) $update['SellerSku'], ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            }

            if (array_key_exists('Quantity', $update) && $update['Quantity'] !== null) {
                $skuNode->addChild('Quantity', (string) (int) $update['Quantity']);
            }

            if (array_key_exists('Price', $update) && $update['Price'] !== null) {
                $skuNode->addChild('Price', number_format((float) $update['Price'], 2, '.', ''));
            }
        }

        return $xml->asXML() ?: '';
    }

    /**
     * @return list<array{SellerSku:string,SkuId?:int|string,ItemId:int|string,Quantity?:int,Price?:float}>
     */
    public function buildPriceQuantitySkuUpdates(Product $product, ?array $stockList = null, ?array $priceList = null): array
    {
        $stockByModelId = [];
        foreach ($stockList ?? [] as $entry) {
            $key = isset($entry['model_id']) ? (string) $entry['model_id'] : '__product__';
            $stockByModelId[$key] = isset($entry['seller_stock'][0]['stock'])
                ? (int) $entry['seller_stock'][0]['stock']
                : null;
        }

        $priceByModelId = [];
        foreach ($priceList ?? [] as $entry) {
            $key = isset($entry['model_id']) ? (string) $entry['model_id'] : '__product__';
            $priceByModelId[$key] = isset($entry['original_price'])
                ? (float) $entry['original_price']
                : null;
        }

        $updates = [];

        if ($product->has_variants) {
            $variants = $product->relationLoaded('variants') ? $product->variants : $product->variants()->get();
            foreach ($variants as $variant) {
                $update = $this->buildVariantPriceQuantityUpdate($product, $variant, $stockByModelId, $priceByModelId);
                if ($update !== null) {
                    $updates[] = $update;
                }
            }
        } else {
            $update = [
                'ItemId' => (int) $product->item_id,
                'SellerSku' => (string) $product->item_sku,
            ];

            $simpleSkuId = data_get($product->raw_response, 'skus.0.SkuId');
            if ($simpleSkuId !== null && $simpleSkuId !== '') {
                $update['SkuId'] = $simpleSkuId;
            }

            if (array_key_exists('__product__', $stockByModelId) && $stockByModelId['__product__'] !== null) {
                $update['Quantity'] = $stockByModelId['__product__'];
            }

            if (array_key_exists('__product__', $priceByModelId) && $priceByModelId['__product__'] !== null) {
                $update['Price'] = $priceByModelId['__product__'];
            }

            if (isset($update['Quantity']) || isset($update['Price'])) {
                $updates[] = $update;
            }
        }

        if ($updates === []) {
            throw new \InvalidArgumentException("No Lazada SKU updates could be derived for product #{$product->id}.");
        }

        return $updates;
    }

    /**
     * @param  array<string, int|null>  $stockByModelId
     * @param  array<string, float|null>  $priceByModelId
     * @return array{SellerSku:string,SkuId?:int|string,ItemId:int|string,Quantity?:int,Price?:float}|null
     */
    private function buildVariantPriceQuantityUpdate(Product $product, ProductVariant $variant, array $stockByModelId, array $priceByModelId): ?array
    {
        $key = (string) $variant->model_id;
        $update = [
            'ItemId' => (int) $product->item_id,
            'SellerSku' => (string) $variant->model_sku,
        ];

        if ($variant->model_id !== null) {
            $update['SkuId'] = $variant->model_id;
        }

        if (array_key_exists($key, $stockByModelId) && $stockByModelId[$key] !== null) {
            $update['Quantity'] = $stockByModelId[$key];
        }

        if (array_key_exists($key, $priceByModelId) && $priceByModelId[$key] !== null) {
            $update['Price'] = $priceByModelId[$key];
        }

        if (!isset($update['Quantity']) && !isset($update['Price'])) {
            return null;
        }

        return $update;
    }

    /**
     * GET /promotion/vouchers/get — list seller vouchers.
     *
     * @param  array<string, scalar|null>  $params
     */
    public function getVoucherList(string $accessToken, string $region, array $params = []): array
    {
        return $this->assertLazadaOk(
            $this->callApi('/promotion/vouchers/get', $this->stringifyParams($params), $accessToken, $region),
            '/promotion/vouchers/get'
        );
    }

    /**
     * GET /promotion/voucher/get — voucher detail.
     *
     * @param  array<string, scalar|null>  $params
     */
    public function getVoucher(string $accessToken, string $region, array $params): array
    {
        return $this->assertLazadaOk(
            $this->callApi('/promotion/voucher/get', $this->stringifyParams($params), $accessToken, $region),
            '/promotion/voucher/get'
        );
    }

    /**
     * POST /promotion/voucher/create
     *
     * @param  array<string, mixed>  $params
     */
    public function createVoucher(string $accessToken, string $region, array $params): array
    {
        return $this->callApiPost('/promotion/voucher/create', $this->stringifyParams($params), $accessToken, $region);
    }

    /**
     * POST /promotion/voucher/update
     *
     * @param  array<string, mixed>  $params
     */
    public function updateVoucher(string $accessToken, string $region, array $params): array
    {
        return $this->callApiPost('/promotion/voucher/update', $this->stringifyParams($params), $accessToken, $region);
    }

    /**
     * POST /promotion/voucher/activate
     *
     * @param  array<string, mixed>  $params
     */
    public function activateVoucher(string $accessToken, string $region, array $params): array
    {
        return $this->callApiPost('/promotion/voucher/activate', $this->stringifyParams($params), $accessToken, $region);
    }

    /**
     * POST /promotion/voucher/deactivate
     *
     * @param  array<string, mixed>  $params
     */
    public function deactivateVoucher(string $accessToken, string $region, array $params): array
    {
        return $this->callApiPost('/promotion/voucher/deactivate', $this->stringifyParams($params), $accessToken, $region);
    }

    /**
     * GET /promotion/voucher/products/get
     *
     * @param  array<string, scalar|null>  $params
     */
    public function getVoucherProducts(string $accessToken, string $region, array $params): array
    {
        return $this->assertLazadaOk(
            $this->callApi('/promotion/voucher/products/get', $this->stringifyParams($params), $accessToken, $region),
            '/promotion/voucher/products/get'
        );
    }

    /**
     * POST /promotion/voucher/product/sku/add
     *
     * @param  array<string, mixed>  $params
     */
    public function addVoucherSku(string $accessToken, string $region, array $params): array
    {
        return $this->callApiPost('/promotion/voucher/product/sku/add', $this->stringifyParams($params), $accessToken, $region);
    }

    /**
     * POST /promotion/voucher/product/sku/remove
     *
     * @param  array<string, mixed>  $params
     */
    public function removeVoucherSku(string $accessToken, string $region, array $params): array
    {
        return $this->callApiPost('/promotion/voucher/product/sku/remove', $this->stringifyParams($params), $accessToken, $region);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, string>
     */
    private function stringifyParams(array $params): array
    {
        $out = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_bool($value)) {
                $out[$key] = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $out[$key] = json_encode(array_values($value));
            } else {
                $out[$key] = (string) $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function assertLazadaOk(array $data, string $apiPath): array
    {
        if (isset($data['code']) && (string) $data['code'] !== '0') {
            throw new \RuntimeException($data['message'] ?? "Lazada API {$apiPath} failed (code: {$data['code']})");
        }

        return $data;
    }
}
