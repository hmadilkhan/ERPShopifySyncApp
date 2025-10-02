<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Erp\ProductSyncController;
use App\Http\Controllers\Api\Erp\StockSyncController;
use App\Http\Controllers\Api\Erp\OrderSyncController;
use App\Http\Controllers\Webhook\ShopifyWebhookController;

// Group ERP routes
Route::prefix('erp')->middleware('auth.erp')->group(function () {
    Route::post('/products/sync', [ProductSyncController::class, 'syncProduct']);
    Route::post('/stock-updated', [StockSyncController::class, 'updateStock']);
    Route::post('/orders/update-status', [OrderSyncController::class, 'updateOrderStatus']);
});

Route::prefix('webhooks/shopify')->middleware('verify.shopify.webhook')->group(function () {
    Route::post('/orders/create', [ShopifyWebhookController::class, 'orderCreated']);
    Route::post('/orders/updated', [ShopifyWebhookController::class, 'orderUpdated']);
    Route::post('/products/create', [ShopifyWebhookController::class, 'productCreated']);
    Route::post('/products/update', [ShopifyWebhookController::class, 'productUpdated']);
    Route::post('/products/delete', [ShopifyWebhookController::class, 'productDeleted']);
    Route::post('/inventory_levels/update', [ShopifyWebhookController::class, 'inventoryUpdated']);
});
