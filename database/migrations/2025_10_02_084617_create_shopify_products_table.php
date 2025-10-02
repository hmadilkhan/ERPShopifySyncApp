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
        Schema::create('shopify_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id'); // FK to shopify_shops
            $table->unsignedBigInteger('erp_product_id')->nullable(); // link to ERP
            $table->string('shopify_product_id')->index(); // Shopify product ID
            $table->string('shopify_variant_id')->nullable()->index();
            $table->string('inventory_item_id')->nullable()->index();
            $table->string('sku')->nullable()->index();
            $table->string('title')->nullable();
            $table->string('status')->nullable(); // active/draft/archived
            $table->integer('stock')->nullable();
            $table->decimal('price', 10, 2)->nullable();
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
        Schema::dropIfExists('shopify_products');
    }
};
