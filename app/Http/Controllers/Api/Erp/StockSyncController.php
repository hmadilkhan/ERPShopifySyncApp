<?php

namespace App\Http\Controllers\Api\Erp;

use App\Http\Controllers\Controller;
use App\Models\ShopifyProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class StockSyncController extends Controller
{
    public function updateStock(Request $request)
    {
        $request->validate([
            'stock_update.sku' => 'required|string',
            'stock_update.quantity' => 'required|integer|min:0'
        ]);

        $sku = $request->input('stock_update.sku');
        $quantity = $request->input('stock_update.quantity');

        $product = ShopifyProduct::where('sku', $sku)->first();
        if (!$product) {
            return response()->json(['error' => 'SKU not found'], 404);
        }

        $shop = $product->shop;

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $shop->access_token,
        ])->post("https://{$shop->shop_domain}/admin/api/2025-01/inventory_levels/set.json", [
            "location_id" => $product->location_id, // store this earlier
            "inventory_item_id" => $product->inventory_item_id,
            "available" => $quantity
        ]);

        return $response->json();
    }
}
