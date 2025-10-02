<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shopify_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id'); // FK to shopify_shops
            $table->unsignedBigInteger('erp_order_id')->nullable(); // link to ERP
            $table->string('shopify_order_id')->index();
            $table->string('name')->nullable(); // e.g. #1001
            $table->string('status')->nullable(); // open, closed, cancelled
            $table->string('financial_status')->nullable(); // paid, pending, refunded
            $table->string('fulfillment_status')->nullable(); // fulfilled, unfulfilled, partial
            $table->decimal('total_price', 10, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->json('raw_payload')->nullable(); // store raw Shopify JSON
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->foreign('shop_id')->references('id')->on('shopify_shops')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_orders');
    }
};
