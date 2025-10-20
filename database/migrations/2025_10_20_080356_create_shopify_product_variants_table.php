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
        Schema::create('shopify_product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopify_product_id')->nullable(); // local FK to shopify_products
            $table->unsignedBigInteger('shop_id')->nullable(); // reference shopify_shops
            $table->unsignedBigInteger('erp_variant_id')->nullable(); // ERP side mapping
            $table->unsignedBigInteger('shopify_variant_id')->nullable(); // Shopify variant ID
            $table->unsignedBigInteger('inventory_item_id')->nullable();
            $table->string('sku')->nullable();
            $table->string('title')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('stock')->nullable();
            $table->string('image_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_product_variants');
    }
};
