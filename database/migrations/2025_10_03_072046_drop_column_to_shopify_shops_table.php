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
            if (Schema::hasColumn('shopify_shops', 'erp_integration_id')) {
                $table->dropForeign(['erp_integration_id']); // drop FK if exists
                $table->dropColumn('erp_integration_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopify_shops', function (Blueprint $table) {
            $table->unsignedBigInteger('erp_integration_id')->nullable()->after('id');

            $table->foreign('erp_integration_id')
                ->references('id')
                ->on('erp_integrations')
                ->onDelete('cascade');
        });
    }
};
