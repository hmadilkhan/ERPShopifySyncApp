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
        Schema::create('shopify_shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop_domain')->unique();   // e.g. mystore.myshopify.com
            $table->string('access_token');            // permanent token after OAuth
            $table->string('scope')->nullable();       // granted scopes
            $table->string('name')->nullable();        // store name (optional)
            $table->string('email')->nullable();       // store email (optional)
            $table->string('currency')->nullable();    // default currency
            $table->string('timezone')->nullable();    // store timezone
            $table->boolean('is_active')->default(true); // active/inactive connection
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_shops');
    }
};
