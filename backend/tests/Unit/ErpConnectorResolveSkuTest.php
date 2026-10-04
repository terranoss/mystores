<?php

namespace Tests\Unit;

use App\Services\ErpConnectorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ErpConnectorResolveSkuTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.erp_pos' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('erp_pos');

        $schema = Schema::connection('erp_pos');
        $schema->create('products', function ($table) {
            $table->increments('id');
            $table->string('sku')->nullable();
        });
        $schema->create('variations', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('product_id');
            $table->string('sub_sku')->nullable();
        });
    }

    public function test_exact_sub_sku_resolves(): void
    {
        $db = DB::connection('erp_pos');
        $db->table('products')->insert(['id' => 10, 'sku' => 'MCA1303']);
        $db->table('variations')->insert([
            'id' => 20,
            'product_id' => 10,
            'sub_sku' => 'MCA1303-1',
        ]);

        $resolved = app(ErpConnectorService::class)->resolveVariationBySku('MCA1303-1');

        $this->assertNotNull($resolved);
        $this->assertSame(10, $resolved['product_id']);
        $this->assertSame(20, $resolved['variation_id']);
        $this->assertSame('MCA1303-1', $resolved['sku']);
    }

    public function test_parent_sku_does_not_resolve_to_appended_variation(): void
    {
        $db = DB::connection('erp_pos');
        $db->table('products')->insert(['id' => 10, 'sku' => 'MCA1303']);
        $db->table('variations')->insert([
            'id' => 20,
            'product_id' => 10,
            'sub_sku' => 'MCA1303-1',
        ]);

        $resolved = app(ErpConnectorService::class)->resolveVariationBySku('MCA1303');

        $this->assertNull($resolved);
    }

    public function test_parent_sku_resolves_when_sub_sku_blank(): void
    {
        $db = DB::connection('erp_pos');
        $db->table('products')->insert(['id' => 11, 'sku' => 'PLAIN-SKU']);
        $db->table('variations')->insert([
            'id' => 21,
            'product_id' => 11,
            'sub_sku' => '',
        ]);

        $resolved = app(ErpConnectorService::class)->resolveVariationBySku('PLAIN-SKU');

        $this->assertNotNull($resolved);
        $this->assertSame(11, $resolved['product_id']);
        $this->assertSame(21, $resolved['variation_id']);
        $this->assertSame('PLAIN-SKU', $resolved['sku']);
    }

    public function test_identical_product_and_sub_sku_resolves(): void
    {
        $db = DB::connection('erp_pos');
        $db->table('products')->insert(['id' => 12, 'sku' => 'SAME-SKU']);
        $db->table('variations')->insert([
            'id' => 22,
            'product_id' => 12,
            'sub_sku' => 'SAME-SKU',
        ]);

        $resolved = app(ErpConnectorService::class)->resolveVariationBySku('SAME-SKU');

        $this->assertNotNull($resolved);
        $this->assertSame('SAME-SKU', $resolved['sku']);
    }
}
