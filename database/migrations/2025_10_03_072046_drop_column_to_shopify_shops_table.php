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
        Schema::table('shopify_shops', function (Blueprint $table) {
            $table->dropColumn('erp_integration_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopify_shops', function (Blueprint $table) {
            $table->dropForeign(['erp_integration_id']);
            $table->dropColumn('erp_integration_id');
        });
    }
};
