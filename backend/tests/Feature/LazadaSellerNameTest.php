<?php

namespace Tests\Feature;

use App\Services\LazadaService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LazadaSellerNameTest extends TestCase
{
    public function test_resolve_shop_name_uses_seller_get_name_not_email(): void
    {
        config([
            'lazada.app_key' => 'test-key',
            'lazada.app_secret' => 'test-secret',
        ]);

        Http::fake([
            'https://api.lazada.com.my/rest/seller/get*' => Http::response([
                'code' => '0',
                'data' => [
                    'name' => 'Melur Plants MY',
                    'email' => 'nor@melur.com',
                    'seller_id' => 3817179422,
                ],
            ], 200),
        ]);

        $name = app(LazadaService::class)->resolveShopName('access-token', 'my');

        $this->assertSame('Melur Plants MY', $name);
    }

    public function test_resolve_shop_name_returns_null_when_name_missing(): void
    {
        config([
            'lazada.app_key' => 'test-key',
            'lazada.app_secret' => 'test-secret',
        ]);

        Http::fake([
            'https://api.lazada.com.my/rest/seller/get*' => Http::response([
                'code' => '0',
                'data' => [
                    'email' => 'nor@melur.com',
                ],
            ], 200),
        ]);

        $name = app(LazadaService::class)->resolveShopName('access-token', 'my');

        $this->assertNull($name);
    }
}
