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
        Schema::create('erp_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Sabsoft ERP", "Client ERP"
            $table->string('erp_url'); // base API URL
            $table->string('erp_secret'); // secure token
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_integrations');
    }
};
