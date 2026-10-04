<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('shopee_stores')->cascadeOnDelete();
            $table->string('order_sn', 50);
            $table->string('order_status', 50)->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('currency', 10)->nullable();
            $table->string('buyer_username')->nullable();
            $table->text('shipping_address')->nullable();
            $table->dateTime('paid_time')->nullable();
            $table->dateTime('ship_by_date')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->json('raw_response')->nullable();
            $table->dateTime('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'order_sn']);
            $table->index('order_status');
            $table->index('synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
