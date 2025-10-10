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

    // public function updateOrderStatus(Request $request)
    // {
    //     try {
    //         // ✅ Step 1: Validate input
    //         $validated = $request->validate([
    //             'order_update.erp_order_id'    => 'required|numeric',
    //             'order_update.status'          => 'required|string',
    //             'order_update.tracking_number' => 'nullable|string',
    //             'order_update.tracking_url'    => 'nullable|url',
    //             'order_update.tracking_company' => 'nullable|string',
    //         ]);

    //         $data = $request->input('order_update');

    //         // ✅ Step 2: Find the local Shopify order record
    //         $shopOrder = ShopifyOrder::where('erp_order_id', $data['erp_order_id'])->first();

    //         if (!$shopOrder) {
    //             return response()->json(['error' => 'Shopify order not found for ERP order ID'], 404);
    //         }

    //         // ✅ Step 3: Get shop credentials
    //         $shop = ShopifyShop::find($shopOrder->shop_id);

    //         if (!$shop) {
    //             return response()->json(['error' => 'Shop not found for this order'], 404);
    //         }

    //         $status      = strtolower($data['status']);
    //         $orderId     = $shopOrder->shopify_order_id;
    //         $shopDomain  = $shop->shop_domain;
    //         $accessToken = $shop->access_token;

    //         $headers = [
    //             'X-Shopify-Access-Token' => $accessToken,
    //             'Accept' => 'application/json',
    //             'Content-Type' => 'application/json',
    //         ];

    //         switch ($status) {

    //             case 'fulfilled':
    //             case 'delivered':
    //             case 'shipped':
    //                 // ✅ Step 4: Get fulfillment order ID (required in 2023+ APIs)
    //                 $fulfillmentOrdersUrl = "https://{$shopDomain}/admin/api/2025-01/orders/{$orderId}/fulfillment_orders.json";
    //                 $fulfillmentOrdersResp = Http::withHeaders($headers)->get($fulfillmentOrdersUrl);

    //                 if ($fulfillmentOrdersResp->failed()) {
    //                     return response()->json([
    //                         'error' => 'Failed to fetch fulfillment orders',
    //                         'response' => $fulfillmentOrdersResp->json(),
    //                     ], $fulfillmentOrdersResp->status());
    //                 }

    //                 $fulfillmentOrders = $fulfillmentOrdersResp->json()['fulfillment_orders'] ?? [];
    //                 if (empty($fulfillmentOrders)) {
    //                     return response()->json(['error' => 'No fulfillment orders found for this order'], 404);
    //                 }

    //                 $fulfillmentOrderId = $fulfillmentOrders[0]['id']; // Take first one for simplicity

    //                 // ✅ Step 5: Build payload (new API format)
    //                 $url = "https://{$shopDomain}/admin/api/2025-01/fulfillments.json";
    //                 $payload = [
    //                     'fulfillment' => [
    //                         'line_items_by_fulfillment_order' => [
    //                             [
    //                                 'fulfillment_order_id' => $fulfillmentOrderId,
    //                             ],
    //                         ],
    //                         'tracking_info' => [
    //                             'number'  => $data['tracking_number'] ?? null,
    //                             'company' => $data['tracking_company'] ?? 'ERP Logistics',
    //                             'url'     => $data['tracking_url'] ?? null,
    //                         ],
    //                         'notify_customer' => true,
    //                     ],
    //                 ];

    //                 $response = Http::withHeaders($headers)->post($url, $payload);
    //                 break;

    //             case 'cancelled':
    //             case 'canceled':
    //                 // ✅ Cancel the order
    //                 $url = "https://{$shopDomain}/admin/api/2025-01/orders/{$orderId}/cancel.json";
    //                 $payload = [
    //                     'email'  => true,
    //                     'reason' => 'customer',
    //                     "restock" => true,
    //                     // "refund" => [
    //                     //     "notify" => true,
    //                     //     "note" => "Customer requested cancellation"
    //                     // ]
    //                 ];

    //                 $response = Http::withHeaders($headers)->post($url, $payload);
    //                 break;

    //             case 'processing':
    //             case 'pending':
    //             case 'on-hold':
    //                 return response()->json([
    //                     'message' => "Shopify does not allow direct '{$status}' updates. Stored locally.",
    //                     'synced_to_shopify' => false,
    //                 ]);

    //             default:
    //                 return response()->json([
    //                     'error' => "Unsupported status '{$status}'"
    //                 ], 400);
    //         }

    //         // ✅ Log
    //         \Log::info('Shopify order status update', [
    //             'order_id' => $orderId,
    //             'status'   => $status,
    //             'payload'  => $payload,
    //             'response' => $response->json(),
    //         ]);

    //         if ($response->failed()) {
    //             return response()->json([
    //                 'success'  => false,
    //                 'message'  => 'Failed to update order status in Shopify',
    //                 'response' => $response->json(),
    //             ], $response->status());
    //         }

    //         return response()->json([
    //             'success'           => true,
    //             'message'           => "Order status '{$status}' synced successfully with Shopify",
    //             'shopify_response'  => $response->json(),
    //         ]);
    //     } catch (\Throwable $e) {
    //         \Log::error('Shopify order status update failed', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString(),
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'error'   => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    /*
    Key Features:
        1. Fulfilled/Shipped/Delivered

        Creates fulfillment with tracking info
        Adds "delivered" tag for delivered status

        2. Cancelled

        Automatically checks if order is fulfilled
        Cancels all fulfillments first (fixes your error)
        Then cancels the order
        Supports refund options

        3. Refunded

        Fetches order details
        Creates refund for all line items
        Supports full or partial refund amounts
        Handles restock options

        4. Returned

        Creates return request
        Supports return reasons (defective, unwanted, etc.)
        Handles restock logic
        Sends customer notifications

        5. Custom Statuses (Processing, Pending, Packed, etc.)

        Uses tags since Shopify doesn't have native support
        Updates order notes if provided
        Returns success with method indication

        New Request Parameters:
        json{
        "order_update": {
            "erp_order_id": 12345,
            "status": "cancelled",
            "reason": "customer",
            "note": "Customer requested cancellation",
            "refund_amount": 115.99,
            "restock": true,
            "notify_customer": true,
            "return_reason": "defective",
            "return_note": "Item damaged on arrival",
            "tracking_number": "TRACK123",
            "tracking_url": "https://track.com/123",
            "tracking_company": "TCS"
        }
        }
        Supported Statuses:

        fulfilled, shipped, delivered
        cancelled, canceled
        refunded, refund
        return, returned
        processing, pending, on-hold, packed, ready-to-ship

        The method now handles the complete order lifecycle with proper error handling and automatic fulfillment cancellation before order cancellation!
    
    */

    // public function updateOrderStatus(Request $request)
    // {
    //     try {
    //         // ✅ Step 1: Validate input
    //         $validated = $request->validate([
    //             'order_update.erp_order_id'      => 'required|numeric',
    //             'order_update.status'            => 'required|string',
    //             'order_update.tracking_number'   => 'nullable|string',
    //             'order_update.tracking_url'      => 'nullable|url',
    //             'order_update.tracking_company'  => 'nullable|string',
    //             'order_update.reason'            => 'nullable|string',
    //             'order_update.note'              => 'nullable|string',
    //             'order_update.refund_amount'     => 'nullable|numeric',
    //             'order_update.restock'           => 'nullable|boolean',
    //             'order_update.notify_customer'   => 'nullable|boolean',
    //             'order_update.return_reason'     => 'nullable|string',
    //             'order_update.return_note'       => 'nullable|string',
    //         ]);

    //         $data = $request->input('order_update');

    //         // ✅ Step 2: Find the local Shopify order record
    //         $shopOrder = ShopifyOrder::where('erp_order_id', $data['erp_order_id'])->first();

    //         if (!$shopOrder) {
    //             return response()->json(['error' => 'Shopify order not found for ERP order ID'], 404);
    //         }

    //         // ✅ Step 3: Get shop credentials
    //         $shop = ShopifyShop::find($shopOrder->shop_id);

    //         if (!$shop) {
    //             return response()->json(['error' => 'Shop not found for this order'], 404);
    //         }

    //         $status      = strtolower($data['status']);
    //         $orderId     = $shopOrder->shopify_order_id;
    //         $shopDomain  = $shop->shop_domain;
    //         $accessToken = $shop->access_token;

    //         $headers = [
    //             'X-Shopify-Access-Token' => $accessToken,
    //             'Accept' => 'application/json',
    //             'Content-Type' => 'application/json',
    //         ];

    //         $response = null;
    //         $payload = [];

    //         switch ($status) {

    //             case 'fulfilled':
    //             case 'delivered':
    //             case 'shipped':
    public function updateOrderStatus(Request $request)
    {
        try {
            // ✅ Step 1: Validate input
            $validated = $request->validate([
                'order_update.erp_order_id'      => 'required|numeric',
                'order_update.status'            => 'required|string',
                'order_update.tracking_number'   => 'nullable|string',
                'order_update.tracking_url'      => 'nullable|url',
                'order_update.tracking_company'  => 'nullable|string',
                'order_update.reason'            => 'nullable|string',
                'order_update.note'              => 'nullable|string',
                'order_update.refund_amount'     => 'nullable|numeric',
                'order_update.restock'           => 'nullable|boolean',
                'order_update.notify_customer'   => 'nullable|boolean',
                'order_update.return_reason'     => 'nullable|string',
                'order_update.return_note'       => 'nullable|string',
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

            $status      = strtolower($data['status']);
            $orderId     = $shopOrder->shopify_order_id;
            $shopDomain  = $shop->shop_domain;
            $accessToken = $shop->access_token;

            $headers = [
                'X-Shopify-Access-Token' => $accessToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];

            $response = null;
            $payload = [];

            switch ($status) {

                case 'fulfilled':
                case 'delivered':
                case 'shipped':
                    // ✅ Step 4: Check current order status first
                    $orderUrl = "https://{$shopDomain}/admin/api/2025-01/orders/{$orderId}.json";
                    $orderResp = Http::withHeaders($headers)->get($orderUrl);

                    if ($orderResp->failed()) {
                        return response()->json([
                            'error' => 'Failed to fetch order details',
                            'response' => $orderResp->json(),
                        ], $orderResp->status());
                    }

                    $orderData = $orderResp->json()['order'] ?? null;
                    $currentFulfillmentStatus = $orderData['fulfillment_status'] ?? null;
                    $financialStatus = $orderData['financial_status'] ?? null;

                    // ✅ Step 4.1: Check if payment is complete (optional based on business logic)
                    // $requirePayment = $data['require_payment'] ?? true; // Default: require payment

                    // if ($requirePayment) {
                    //     $allowedFinancialStatuses = ['paid', 'partially_paid', 'authorized'];

                    //     if (!in_array($financialStatus, $allowedFinancialStatuses)) {
                    //         return response()->json([
                    //             'success' => false,
                    //             'error' => 'Cannot fulfill order: Payment not completed',
                    //             'financial_status' => $financialStatus,
                    //             'message' => 'Order must be paid, authorized, or partially paid before fulfillment',
                    //             'order_id' => $orderId,
                    //         ], 422);
                    //     }

                    //     \Log::info('Payment check passed for fulfillment', [
                    //         'order_id' => $orderId,
                    //         'financial_status' => $financialStatus,
                    //     ]);
                    // }

                    // ✅ If already fulfilled, update tracking or just add tags
                    if ($currentFulfillmentStatus === 'fulfilled') {
                        $fulfillments = $orderData['fulfillments'] ?? [];

                        if (!empty($fulfillments) && isset($data['tracking_number'])) {
                            // Update existing fulfillment with tracking
                            $fulfillmentId = $fulfillments[0]['id'];
                            $url = "https://{$shopDomain}/admin/api/2025-01/fulfillments/{$fulfillmentId}.json";

                            $payload = [
                                'fulfillment' => [
                                    'tracking_number' => $data['tracking_number'],
                                    'tracking_company' => $data['tracking_company'] ?? 'ERP Logistics',
                                    'tracking_url' => $data['tracking_url'] ?? null,
                                    'notify_customer' => $data['notify_customer'] ?? true,
                                ],
                            ];

                            $response = Http::withHeaders($headers)->put($url, $payload);

                            \Log::info('Updated tracking for already fulfilled order', [
                                'order_id' => $orderId,
                                'fulfillment_id' => $fulfillmentId,
                            ]);
                        } else {
                            // Just update tags if no tracking to add
                            $this->updateOrderTags($shopDomain, $orderId, $headers, $status, $data['note'] ?? "Status updated to {$status}");

                            return response()->json([
                                'success' => true,
                                'message' => "Order already fulfilled. Tag '{$status}' added.",
                                'already_fulfilled' => true,
                            ]);
                        }

                        // Add status tag
                        if ($status === 'delivered') {
                            $this->updateOrderTags($shopDomain, $orderId, $headers, 'delivered');
                        }
                        break;
                    }

                    // ✅ Step 5: Get fulfillment order ID (for unfulfilled orders)
                    $fulfillmentOrdersUrl = "https://{$shopDomain}/admin/api/2025-01/orders/{$orderId}/fulfillment_orders.json";
                    $fulfillmentOrdersResp = Http::withHeaders($headers)->get($fulfillmentOrdersUrl);

                    if ($fulfillmentOrdersResp->failed()) {
                        return response()->json([
                            'error' => 'Failed to fetch fulfillment orders',
                            'response' => $fulfillmentOrdersResp->json(),
                        ], $fulfillmentOrdersResp->status());
                    }

                    $fulfillmentOrders = $fulfillmentOrdersResp->json()['fulfillment_orders'] ?? [];
                    if (empty($fulfillmentOrders)) {
                        return response()->json([
                            'error' => 'No fulfillment orders available',
                            'message' => 'Order may already be fulfilled or cancelled',
                            'current_status' => $currentFulfillmentStatus,
                        ], 404);
                    }

                    $fulfillmentOrderId = $fulfillmentOrders[0]['id']; // Take first one for simplicity

                    // ✅ Step 6: Build payload (new API format)
                    $url = "https://{$shopDomain}/admin/api/2025-01/fulfillments.json";
                    $payload = [
                        'fulfillment' => [
                            'line_items_by_fulfillment_order' => [
                                [
                                    'fulfillment_order_id' => $fulfillmentOrderId,
                                ],
                            ],
                            'tracking_info' => [
                                'number'  => $data['tracking_number'] ?? null,
                                'company' => $data['tracking_company'] ?? 'ERP Logistics',
                                'url'     => $data['tracking_url'] ?? null,
                            ],
                            'notify_customer' => $data['notify_customer'] ?? true,
                        ],
                    ];

                    $response = Http::withHeaders($headers)->post($url, $payload);

                    // ✅ Optional: Add custom tag for delivered status
                    if ($status === 'delivered' && $response->successful()) {
                        $this->updateOrderTags($shopDomain, $orderId, $headers, 'delivered');
                    }
                    break;

                case 'cancelled':
                case 'canceled':
                    // ✅ Check if order is fulfilled first
                    $orderUrl = "https://{$shopDomain}/admin/api/2025-01/orders/{$orderId}.json";
                    $orderResp = Http::withHeaders($headers)->get($orderUrl);

                    if ($orderResp->failed()) {
                        return response()->json([
                            'error' => 'Failed to fetch order details',
                            'response' => $orderResp->json(),
                        ], $orderResp->status());
                    }

                    $orderData = $orderResp->json()['order'] ?? null;
                    $fulfillmentStatus = $orderData['fulfillment_status'] ?? null;

                    // ✅ If fulfilled, cancel fulfillment first
                    if ($fulfillmentStatus === 'fulfilled') {
                        $fulfillments = $orderData['fulfillments'] ?? [];

                        foreach ($fulfillments as $fulfillment) {
                            $fulfillmentId = $fulfillment['id'];
                            $cancelFulfillmentUrl = "https://{$shopDomain}/admin/api/2025-01/fulfillments/{$fulfillmentId}/cancel.json";

                            $cancelFulfillmentPayload = [
                                'fulfillment' => [
                                    'notify_customer' => $data['notify_customer'] ?? true,
                                ]
                            ];

                            $cancelFulfillmentResp = Http::withHeaders($headers)->post($cancelFulfillmentUrl, $cancelFulfillmentPayload);

                            if ($cancelFulfillmentResp->failed()) {
                                return response()->json([
                                    'error' => 'Failed to cancel fulfillment before canceling order',
                                    'response' => $cancelFulfillmentResp->json(),
                                ], $cancelFulfillmentResp->status());
                            }

                            \Log::info('Fulfillment cancelled', [
                                'fulfillment_id' => $fulfillmentId,
                                'order_id' => $orderId,
                            ]);
                        }
                    }

                    // ✅ Now cancel the order
                    $url = "https://{$shopDomain}/admin/api/2025-01/orders/{$orderId}/cancel.json";
                    $payload = [
                        'email'  => $data['notify_customer'] ?? true,
                        'reason' => $data['reason'] ?? 'customer',
                        'restock' => $data['restock'] ?? true,
                    ];

                    // ✅ Optional: Add refund details
                    if (isset($data['refund_amount'])) {
                        $payload['refund'] = [
                            'notify' => $data['notify_customer'] ?? true,
                            'note' => $data['note'] ?? 'Order cancelled',
                        ];
                    }

                    $response = Http::withHeaders($headers)->post($url, $payload);
                    break;

                case 'refunded':
                case 'refund':
                    // ✅ Get order details for refund calculation
                    $orderUrl = "https://{$shopDomain}/admin/api/2025-01/orders/{$orderId}.json";
                    $orderResp = Http::withHeaders($headers)->get($orderUrl);

                    if ($orderResp->failed()) {
                        return response()->json([
                            'error' => 'Failed to fetch order details',
                            'response' => $orderResp->json(),
                        ], $orderResp->status());
                    }

                    $orderData = $orderResp->json()['order'] ?? null;
                    $lineItems = $orderData['line_items'] ?? [];
                    $totalPrice = $orderData['total_price'] ?? 0;
                    $currency = $orderData['currency'] ?? 'PKR';

                    // ✅ Build refund payload
                    $url = "https://{$shopDomain}/admin/api/2025-01/orders/{$orderId}/refunds.json";

                    $refundLineItems = [];
                    foreach ($lineItems as $item) {
                        $refundLineItems[] = [
                            'line_item_id' => $item['id'],
                            'quantity' => $item['quantity'],
                            'restock_type' => $data['restock'] ?? true ? 'return' : 'no_restock',
                        ];
                    }

                    $payload = [
                        'refund' => [
                            'currency' => $currency,
                            'notify' => $data['notify_customer'] ?? true,
                            'note' => $data['note'] ?? 'Refund processed',
                            'shipping' => [
                                'full_refund' => true,
                            ],
                            'refund_line_items' => $refundLineItems,
                            'transactions' => [
                                [
                                    'parent_id' => null,
                                    'amount' => $data['refund_amount'] ?? $totalPrice,
                                    'kind' => 'refund',
                                    'gateway' => 'manual',
                                ],
                            ],
                        ],
                    ];

                    $response = Http::withHeaders($headers)->post($url, $payload);
                    break;

                case 'return':
                case 'returned':
                    // ✅ Get fulfillment details for return
                    $orderUrl = "https://{$shopDomain}/admin/api/2025-01/orders/{$orderId}.json";
                    $orderResp = Http::withHeaders($headers)->get($orderUrl);

                    if ($orderResp->failed()) {
                        return response()->json([
                            'error' => 'Failed to fetch order details',
                            'response' => $orderResp->json(),
                        ], $orderResp->status());
                    }

                    $orderData = $orderResp->json()['order'] ?? null;
                    $lineItems = $orderData['line_items'] ?? [];

                    if (empty($lineItems)) {
                        return response()->json(['error' => 'No line items found for return'], 404);
                    }

                    // ✅ Build return payload
                    $url = "https://{$shopDomain}/admin/api/2025-01/returns.json";

                    $returnLineItems = [];
                    foreach ($lineItems as $item) {
                        $returnLineItems[] = [
                            'fulfillment_line_item_id' => $item['id'],
                            'quantity' => $item['quantity'],
                            'return_reason' => $data['return_reason'] ?? 'unwanted',
                            'return_reason_note' => $data['return_note'] ?? 'Customer requested return',
                            'restock_type' => $data['restock'] ?? true ? 'return' : 'no_restock',
                        ];
                    }

                    $payload = [
                        'return' => [
                            'order_id' => $orderId,
                            'return_line_items' => $returnLineItems,
                            'notify_customer' => $data['notify_customer'] ?? true,
                            'note' => $data['note'] ?? 'Return initiated',
                        ],
                    ];

                    $response = Http::withHeaders($headers)->post($url, $payload);
                    break;

                case 'processing':
                case 'pending':
                case 'on-hold':
                case 'packed':
                case 'ready-to-ship':
                    // ✅ Update order tags for custom statuses
                    $this->updateOrderTags($shopDomain, $orderId, $headers, $status, $data['note'] ?? null);

                    return response()->json([
                        'success' => true,
                        'message' => "Status '{$status}' stored as tag. Shopify does not have native support for this status.",
                        'synced_to_shopify' => true,
                        'method' => 'tags',
                    ]);

                default:
                    return response()->json([
                        'error' => "Unsupported status '{$status}'"
                    ], 400);
            }

            // ✅ Log
            \Log::info('Shopify order status update', [
                'order_id' => $orderId,
                'status'   => $status,
                'payload'  => $payload,
                'response' => $response ? $response->json() : null,
            ]);

            if ($response && $response->failed()) {
                return response()->json([
                    'success'  => false,
                    'message'  => 'Failed to update order status in Shopify',
                    'response' => $response->json(),
                ], $response->status());
            }

            return response()->json([
                'success'           => true,
                'message'           => "Order status '{$status}' synced successfully with Shopify",
                'shopify_response'  => $response ? $response->json() : null,
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

    /**
     * Helper method to update order tags
     */
    private function updateOrderTags($shopDomain, $orderId, $headers, $tag, $note = null)
    {
        $url = "https://{$shopDomain}/admin/api/2025-01/orders/{$orderId}.json";

        $payload = [
            'order' => [
                'id' => $orderId,
                'tags' => $tag,
            ]
        ];

        if ($note) {
            $payload['order']['note'] = $note;
        }

        $response = Http::withHeaders($headers)->put($url, $payload);

        \Log::info('Order tags updated', [
            'order_id' => $orderId,
            'tag' => $tag,
            'success' => $response->successful(),
        ]);

        return $response;
    }
}
