<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncShopeeOrderJob;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopeeStore;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ShopeeWebhookController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {}

    public function handle(Request $request): Response
    {
        $payload = $request->all();
        $eventId = $payload['event_id'] ?? null;
        $code = isset($payload['code']) ? (int) $payload['code'] : null;
        $shopId = $payload['shop_id'] ?? null;

        Log::info('Shopee webhook received', [
            'event_id' => $eventId,
            'code' => $code,
            'shop_id' => $shopId,
        ]);

        // Shopee's "Verify and Save" test push has no code (or code 0) and is signed
        // with a separate Test Push Partner Key. Ack with an empty 200 and no signature
        // requirement. Real pushes carry no event_id, so we must not gate on it.
        if ($code === null || $code === 0) {
            Log::info('Shopee webhook verification ping acknowledged', ['code' => $code]);
            return $this->ack();
        }

        // Actionable pushes must be signed with the app partner key.
        if (!$this->verifySignature($request)) {
            return response('', 401);
        }

        // Shopee pushes carry no event_id, so derive an idempotency key from the exact
        // signed bytes. Identical retries collapse; a later genuine status change hashes
        // differently and is processed.
        $rawBody = $request->getContent();
        $eventKey = $eventId
            ? "event_{$eventId}"
            : 'body_'.hash('sha256', $rawBody);

        $ttl = $eventId ? now()->addHours(24) : now()->addMinutes(30);
        if (!Cache::add("shopee:webhook:{$eventKey}", true, $ttl)) {
            Log::info('Shopee webhook already processed (deduplicated)', ['event_key' => $eventKey]);
            return $this->ack();
        }

        $store = $shopId !== null
            ? ShopeeStore::where('shop_id', $shopId)->first()
            : null;

        $data = $payload['data'] ?? [];

        if ($code === 3) {
            $this->handleOrderStatusPush($store, $data, $eventKey);
        } elseif (in_array($code, [7, 8], true)) {
            $this->handleStockPush($store, $data);
        } else {
            Log::info('Shopee webhook ignored unsupported push code', [
                'event_key' => $eventKey,
                'code' => $code,
            ]);
        }

        return $this->ack();
    }

    /**
     * Shopee requires 2xx with an empty body to count as success.
     */
    protected function ack(): Response
    {
        return response('', 200);
    }

    protected function handleOrderStatusPush(?ShopeeStore $store, array $data, string $eventKey): void
    {
        $orderSn = $data['ordersn'] ?? $data['order_sn'] ?? null;

        if (!$store || !$orderSn) {
            Log::warning('Shopee order push missing store or ordersn', [
                'event_key' => $eventKey,
                'store_id' => $store?->id,
                'ordersn' => $orderSn,
            ]);
            return;
        }

        SyncShopeeOrderJob::dispatch($store->id, (string) $orderSn);

        Log::info('Dispatched SyncShopeeOrderJob from order status push', [
            'event_key' => $eventKey,
            'store_id' => $store->id,
            'order_sn' => $orderSn,
        ]);
    }

    protected function handleStockPush(?ShopeeStore $store, array $data): void
    {
        $itemId = $data['item_id'] ?? null;
        $modelId = $data['model_id'] ?? null;
        $newStock = $data['stock'] ?? null;

        if (!$itemId || $newStock === null) {
            return;
        }

        $productQuery = Product::where('item_id', $itemId);
        if ($store) {
            $productQuery->where('store_id', $store->id);
        }

        $product = $productQuery->first();
        if (!$product) {
            return;
        }

        $sku = null;
        if ($modelId) {
            $variant = ProductVariant::where('product_id', $product->id)
                ->where('model_id', $modelId)
                ->first();
            if ($variant) {
                $variant->update(['stock' => $newStock]);
                $sku = $variant->model_sku;
            }
        } else {
            $product->update(['stock' => $newStock]);
            $sku = $product->item_sku;
        }

        if ($sku) {
            Log::info('Triggering cross-store sync from webhook', ['sku' => $sku, 'stock' => $newStock]);
            $user = $product->store?->user;
            if ($user) {
                $this->productService->syncLinkedStores($user, $sku, $newStock, $product->id);
            }
        }
    }

    protected function verifySignature(Request $request): bool
    {
        $signature = $request->header('Authorization') ?? $request->query('sign');
        // Console Push Partner Key is primary; App Partner Key kept as fallback.
        $partnerKeys = array_values(array_filter([
            config('shopee.push_partner_key'),
            config('shopee.partner_key'),
        ], fn ($key) => is_string($key) && $key !== ''));

        if (!$signature || $partnerKeys === []) {
            Log::warning('Shopee webhook signature verification failed', [
                'reason' => !$signature ? 'missing_signature' : 'missing_partner_key',
            ]);
            return false;
        }

        $configuredUrl = config('shopee.webhook_url');
        $url = $configuredUrl ?: $request->fullUrl();
        $body = $request->getContent();
        $baseString = $url.'|'.$body;
        $computedPrefixes = [];

        foreach ($partnerKeys as $partnerKey) {
            $calculatedSignature = hash_hmac('sha256', $baseString, $partnerKey);
            $computedPrefixes[] = substr($calculatedSignature, 0, 8);
            if (hash_equals($calculatedSignature, $signature)) {
                return true;
            }
        }

        // Non-secret diagnostics: a mismatched SHOPEE_WEBHOOK_URL is the usual cause.
        Log::warning('Shopee webhook signature verification failed', [
            'reason' => 'signature_mismatch',
            'signing_url' => $url,
            'url_source' => $configuredUrl ? 'config' : 'request',
            'body_length' => strlen($body),
            'received_prefix' => substr($signature, 0, 8),
            'computed_prefixes' => $computedPrefixes,
        ]);
        return false;
    }
}
