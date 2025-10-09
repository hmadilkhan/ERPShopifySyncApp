<?php

namespace App\Http\Controllers\Api\Erp;

use App\Http\Controllers\Controller;
use App\Models\ShopifyOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ShopifyShop;

class OrderSyncController extends Controller
{
    // public function updateOrderStatus(Request $request)
    // {
    //     $request->validate([
    //         'order_update.erp_order_id' => 'required|numeric',
    //         'order_update.status' => 'required|string',
    //         'order_update.tracking_number' => 'nullable|string',
    //         'order_update.tracking_url' => 'nullable|url'
    //     ]);

    //     $data = $request->input('order_update');
    //     $shopOrder = ShopifyOrder::where("erp_order_id",$data['shopify_order_id'])->first();
    //     $shop = ShopifyShop::where("id",$shopOrder->shop_id)->first(); // TODO: find correct shop

    //     $payload = [
    //         "order" => [
    //             "id" => $data['shopify_order_id'],
    //             "fulfillment_status" => $data['status'],
    //         ]
    //     ];

    //     $response = Http::withHeaders([
    //         'X-Shopify-Access-Token' => $shop->access_token,
    //     ])->put("https://{$shop->shop_domain}/admin/api/2025-01/orders/{$data['shopify_order_id']}.json", $payload);

    //     return $response->json();
    // }

    public function updateOrderStatus(Request $request)
    {
        try {
            // ✅ Step 1: Validate input
            $validated = $request->validate([
                'order_update.erp_order_id'    => 'required|numeric',
                'order_update.status'          => 'required|string',
                'order_update.tracking_number' => 'nullable|string',
                'order_update.tracking_url'    => 'nullable|url',
                'order_update.tracking_company' => 'nullable|string',
            ]);

            $data = $request->input('order_update');

            // ✅ Step 2: Find the local Shopify order record
            $shopOrder = ShopifyOrder::where('erp_order_id', $data['erp_order_id'])->first();

            if (!$shopOrder) {
                return response()->json(['error' => 'Shopify order not found for ERP order ID'], 404);
            }

            // ✅ Step 3: Get shop credentials
            $shop = ShopifyShop::find($shopOrder->shop_id);

            if (!$shop) {
                return response()->json(['error' => 'Shop not found for this order'], 404);
            }

            // ✅ Step 4: Map ERP status → Shopify fulfillment or cancel logic
            $status       = strtolower($data['status']);
            $orderId      = $shopOrder->shopify_order_id;
            $shopDomain   = $shop->shop_domain;
            $accessToken  = $shop->access_token;

            // Base headers
            $headers = [
                'X-Shopify-Access-Token' => $accessToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];

            switch ($status) {

                case 'fulfilled':
                case 'delivered':
                case 'shipped':
                    // ✅ Fulfill the order (mark as fulfilled)
                    $url = "https://{$shopDomain}/admin/api/2025-01/fulfillments.json";
                    $payload = [
                        'fulfillment' => [
                            'order_id'        => $orderId,
                            'tracking_number' => $data['tracking_number'] ?? null,
                            'tracking_urls'   => isset($data['tracking_url']) ? [$data['tracking_url']] : [],
                            'tracking_company' => $data['tracking_company'] ?? 'ERP Logistics',
                            'notify_customer' => true,
                        ],
                    ];

                    $response = Http::withHeaders($headers)->post($url, $payload);
                    break;

                case 'cancelled':
                case 'canceled':
                    // ✅ Cancel the order
                    $url = "https://{$shopDomain}/admin/api/2025-01/orders/{$orderId}/cancel.json";
                    $payload = [
                        'email'  => true,
                        'reason' => 'customer', // or 'inventory' / 'fraud'
                    ];

                    $response = Http::withHeaders($headers)->post($url, $payload);
                    break;

                case 'processing':
                case 'pending':
                case 'on-hold':
                    // ⚠️ Shopify does not support these intermediate states via API
                    return response()->json([
                        'message' => "Shopify does not allow direct '{$status}' updates. Stored locally.",
                        'synced_to_shopify' => false,
                    ]);

                default:
                    return response()->json([
                        'error' => "Unsupported status '{$status}'"
                    ], 400);
            }

            // ✅ Step 5: Log + return
            \Log::info('Shopify order status update', [
                'order_id' => $orderId,
                'status'   => $status,
                'payload'  => $payload,
                'response' => $response->json(),
            ]);

            if ($response->failed()) {
                return response()->json([
                    'success'  => false,
                    'message'  => 'Failed to update order status in Shopify',
                    'response' => $response->json(),
                ], $response->status());
            }

            return response()->json([
                'success'           => true,
                'message'           => "Order status '{$status}' synced successfully with Shopify",
                'shopify_response'  => $response->json(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Shopify order status update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
