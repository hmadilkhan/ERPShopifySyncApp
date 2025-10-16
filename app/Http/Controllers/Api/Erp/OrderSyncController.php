<?php

namespace App\Http\Controllers\Api\Erp;

use App\Http\Controllers\Controller;
use App\Models\ShopifyOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ShopifyShop;
use Illuminate\Support\Facades\Log;

class OrderSyncController extends Controller
{
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

            // ✅ Step 2: Find local Shopify order record
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
            $payload  = [];

            switch ($status) {
                // ✅ --------------- GraphQL Fulfillment Handling -------------------
                case 'fulfilled':
                case 'delivered':
                case 'shipped': {

                        \Log::info("➡️ Starting GraphQL fulfillment for order {$orderId}");

                        $orderGid = "gid://shopify/Order/{$orderId}";
                        $graphqlUrl = "https://{$shopDomain}/admin/api/2025-01/graphql.json";

                        // Step 1: Fetch fulfillment orders
                        $query = <<<GQL
                            query GetFulfillmentOrders(\$orderId: ID!) {
                                order(id: \$orderId) {
                                    id
                                    name
                                    fulfillmentOrders(first: 10) {
                                        edges {
                                            node {
                                                id
                                                status
                                                assignedLocation {
                                                    location {
                                                        id
                                                        name
                                                    }
                                                }
                                                lineItems(first: 50) {
                                                    edges {
                                                        node {
                                                            id
                                                            remainingQuantity
                                                            lineItem {
                                                                name
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            GQL;

                        $graphqlResponse = Http::withHeaders($headers)->post($graphqlUrl, [
                            'query' => $query,
                            'variables' => ['orderId' => $orderGid],
                        ]);

                        $dataGraph = $graphqlResponse->json();

                        if (
                            !isset($dataGraph['data']['order']['fulfillmentOrders']['edges']) ||
                            empty($dataGraph['data']['order']['fulfillmentOrders']['edges'])
                        ) {
                            return response()->json([
                                'error' => 'No fulfillment orders found (may already be fulfilled)',
                                'shopify_response' => $dataGraph,
                            ], 404);
                        }

                        // Step 2: Find first OPEN fulfillment order
                        $fulfillmentOrders = $dataGraph['data']['order']['fulfillmentOrders']['edges'];
                        $openOrder = collect($fulfillmentOrders)->firstWhere('node.status', 'OPEN');

                        if (!$openOrder) {
                            return response()->json([
                                'error' => 'No OPEN fulfillment order found',
                                'details' => $fulfillmentOrders,
                            ], 404);
                        }

                        $fulfillmentOrderId = $openOrder['node']['id'];
                        $lineItems = $openOrder['node']['lineItems']['edges'];

                        $lineItemInputs = [];
                        foreach ($lineItems as $item) {
                            $lineItemInputs[] = [
                                'id' => $item['node']['id'],
                                'quantity' => (int)($item['node']['remainingQuantity'] ?? 1),
                            ];
                        }

                        // Step 3: Create fulfillment with fulfillmentOrderId (required)
                        $mutation = <<<GQL
                            mutation FulfillOrder(\$fulfillment: FulfillmentCreateV3Input!) {
                            fulfillmentCreateV3(fulfillment: \$fulfillment) {
                                fulfillment {
                                id
                                status
                                trackingInfo {
                                    number
                                    company
                                    url
                                }
                                }
                                userErrors {
                                field
                                message
                                }
                            }
                            }
                            GQL;

                        $variables = [
                            'fulfillment' => [
                                'lineItemsByFulfillmentOrder' => [
                                    [
                                        'fulfillmentOrderId' => $fulfillmentOrderId,
                                        'fulfillmentOrderLineItems' => $lineItemInputs,
                                    ],
                                ],
                                'trackingInfo' => [
                                    'number'  => $data['tracking_number'] ?? null,
                                    'company' => $data['tracking_company'] ?? 'ERP Logistics',
                                    'url'     => $data['tracking_url'] ?? null,
                                ],
                                'notifyCustomer' => $data['notify_customer'] ?? true,
                            ],
                        ];

                        $graphqlFulfill = Http::withHeaders($headers)->post($graphqlUrl, [
                            'query' => $mutation,
                            'variables' => $variables,
                        ]);

                        $fulfillResult = $graphqlFulfill->json();

                        \Log::info('🟢 Fulfillment GraphQL Response', $fulfillResult);

                        if (isset($fulfillResult['data']['fulfillmentCreateV3']['userErrors'][0])) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Shopify returned errors while fulfilling',
                                'errors' => $fulfillResult['data']['fulfillmentCreateV3']['userErrors'],
                            ], 422);
                        }

                        // Optional: Tag as delivered
                        if ($status === 'delivered') {
                            $this->updateOrderTags($shopDomain, $orderId, $headers, 'delivered');
                        }

                        return response()->json([
                            'success' => true,
                            'message' => "Order {$orderId} fulfilled successfully via GraphQL",
                            'shopify_response' => $fulfillResult,
                        ]);
                    }

                    // ✅ --------------- REST CANCELLATION -------------------
                case 'cancelled':
                case 'canceled': {
                        $url = "https://{$shopDomain}/admin/api/2025-01/orders/{$orderId}/cancel.json";
                        $payload = [
                            'email' => $data['notify_customer'] ?? true,
                            'reason' => $data['reason'] ?? 'customer',
                            'restock' => $data['restock'] ?? true,
                        ];

                        $response = Http::withHeaders($headers)->post($url, $payload);
                        break;
                    }

                    // ✅ --------------- REST REFUND -------------------
                case 'refunded':
                case 'refund': {
                        $orderUrl = "https://{$shopDomain}/admin/api/2025-01/orders/{$orderId}.json";
                        $orderResp = Http::withHeaders($headers)->get($orderUrl);
                        $orderData = $orderResp->json()['order'] ?? [];
                        $lineItems = $orderData['line_items'] ?? [];
                        $currency = $orderData['currency'] ?? 'PKR';
                        $totalPrice = $orderData['total_price'] ?? 0;

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
                                'shipping' => ['full_refund' => true],
                                'refund_line_items' => $refundLineItems,
                                'transactions' => [[
                                    'parent_id' => null,
                                    'amount' => $data['refund_amount'] ?? $totalPrice,
                                    'kind' => 'refund',
                                    'gateway' => 'manual',
                                ]],
                            ],
                        ];

                        $url = "https://{$shopDomain}/admin/api/2025-01/orders/{$orderId}/refunds.json";
                        $response = Http::withHeaders($headers)->post($url, $payload);
                        break;
                    }

                    // ✅ --------------- REST RETURN -------------------
                case 'return':
                case 'returned': {
                        $url = "https://{$shopDomain}/admin/api/2025-01/returns.json";
                        $payload = [
                            'return' => [
                                'order_id' => $orderId,
                                'notify_customer' => $data['notify_customer'] ?? true,
                                'note' => $data['note'] ?? 'Return initiated',
                            ],
                        ];
                        $response = Http::withHeaders($headers)->post($url, $payload);
                        break;
                    }

                    // ✅ --------------- TAG-BASED STATUSES -------------------
                case 'processing':
                case 'pending':
                case 'on-hold':
                case 'packed':
                case 'ready-to-ship': {
                        $this->updateOrderTags($shopDomain, $orderId, $headers, $status, $data['note'] ?? null);

                        return response()->json([
                            'success' => true,
                            'message' => "Status '{$status}' stored as tag. Shopify does not have native support for this status.",
                            'synced_to_shopify' => true,
                            'method' => 'tags',
                        ]);
                    }

                default:
                    return response()->json(['error' => "Unsupported status '{$status}'"], 400);
            }

            // ✅ Log for REST actions
            \Log::info('Shopify order status update (REST)', [
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
                'success' => true,
                'message' => "Order status '{$status}' synced successfully with Shopify",
                'shopify_response' => $response ? $response->json() : null,
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
     * ✅ Helper: Update Order Tags
     */
    private function updateOrderTags($shopDomain, $orderId, $headers, $tag, $note = null)
    {
        $url = "https://{$shopDomain}/admin/api/2025-01/orders/{$orderId}.json";

        $payload = ['order' => ['id' => $orderId, 'tags' => $tag]];
        if ($note) $payload['order']['note'] = $note;

        $response = Http::withHeaders($headers)->put($url, $payload);

        \Log::info('Order tags updated', [
            'order_id' => $orderId,
            'tag' => $tag,
            'success' => $response->successful(),
        ]);

        return $response;
    }
}
