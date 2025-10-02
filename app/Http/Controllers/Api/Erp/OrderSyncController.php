<?php

namespace App\Http\Controllers\Api\Erp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ShopifyShop;

class OrderSyncController extends Controller
{
    public function updateOrderStatus(Request $request)
    {
        $request->validate([
            'order_update.shopify_order_id' => 'required|numeric',
            'order_update.status' => 'required|string',
            'order_update.tracking_number' => 'nullable|string',
            'order_update.tracking_url' => 'nullable|url'
        ]);

        $data = $request->input('order_update');
        $shop = ShopifyShop::first(); // TODO: find correct shop

        $payload = [
            "order" => [
                "id" => $data['shopify_order_id'],
                "fulfillment_status" => $data['status'],
            ]
        ];

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $shop->access_token,
        ])->put("https://{$shop->shop_domain}/admin/api/2025-01/orders/{$data['shopify_order_id']}.json", $payload);

        return $response->json();
    }
}
