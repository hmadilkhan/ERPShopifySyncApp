<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\ShopifyOrder;
use App\Models\ShopifyProduct;
use App\Models\ShopifyShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\FacadesLog;

class ShopifyWebhookController extends Controller
{
    public function orderCreated(Request $request)
    {
        try {
            $data = $request->all();

            // Save in SYNC DB
            $order = ShopifyOrder::updateOrCreate(
                ['shopify_order_id' => $data['id']],
                [
                    'shop_id'            => $this->getShopId($request),
                    'name'               => $data['name'] ?? null,
                    'status'             => $data['cancelled_at'] ? 'cancelled' : 'open',
                    'financial_status'   => $data['financial_status'] ?? null,
                    'fulfillment_status' => $data['fulfillment_status'] ?? null,
                    'total_price'        => $data['total_price'] ?? 0,
                    'currency'           => $data['currency'] ?? 'USD',
                    'raw_payload'        => $data,
                    'synced_at'          => now(),
                ]
            );

            // Transform to ERP contract
            // $payload = [
            //     "order_id"           => $data['id'],
            //     "name"               => $data['name'],
            //     "status"             => $order->status,
            //     "financial_status"   => $order->financial_status,
            //     "fulfillment_status" => $order->fulfillment_status,
            //     "currency"           => $order->currency,
            //     "total_price"        => $order->total_price,
            //     "line_items"         => collect($data['line_items'])->map(fn($item) => [
            //         "product_id" => $item['product_id'],
            //         "sku"        => $item['sku'] ?? null,
            //         "title"      => $item['title'],
            //         "quantity"   => $item['quantity'],
            //         "price"      => $item['price'],
            //     ])->toArray(),
            //     "customer" => [
            //         "first_name" => $data['customer']['first_name'] ?? null,
            //         "last_name"  => $data['customer']['last_name'] ?? null,
            //         "email"      => $data['customer']['email'] ?? null,
            //         "phone"      => $data['customer']['phone'] ?? null,
            //     ],
            //     "shop" => [
            //         "domain"  => $request->header('X-Shopify-Shop-Domain'),
            //         "shop_id" => $order->shop_id,
            //     ],
            //     "synced_at" => now()->toISOString(),
            // ];

            $payload = $this->transformToErpPayload($data,$request,$order);

            Log::info('🗑️ Shopify Product Payload', ['payload' => $payload]);

            // Forward to ERP
            $this->forwardToErp($request, '/webhooks/order-created', $payload);

            return response()->json(['success' => true]);
        } catch (\Throwable $th) {
            // 🔹 Log the error with detailed trace
            Log::error('❌ Shopify Order Created Webhook Failed', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            // (Optional) Return 500 so Shopify retries
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Transform Shopify webhook payload into ERP-ready format
     */
    protected function transformToErpPayload(array $data, Request $request, $order): array
    {
        return [
            'order_id'           => $data['id'],
            'name'               => $data['name'] ?? ('#' . ($data['order_number'] ?? 'N/A')),
            'status'             => $order->status ?? ($data['cancelled_at'] ? 'cancelled' : 'open'),
            'financial_status'   => $data['financial_status'] ?? 'pending',
            'fulfillment_status' => $data['fulfillment_status'] ?? null,
            'currency'           => $data['currency'] ?? 'PKR',
            'total_price'        => $data['total_price'] ?? 0,

            // 🧾 Line Items Mapping
            'line_items' => collect($data['line_items'] ?? [])->map(function ($item) {
                return [
                    'product_id' => $item['product_id'] ?? null,
                    'sku'        => $item['sku'] ?? null,
                    'title'      => $item['title'] ?? null,
                    'quantity'   => $item['quantity'] ?? 0,
                    'price'      => $item['price'] ?? 0,
                    'tax'        => collect($item['tax_lines'] ?? [])->sum('price') ?? 0,
                ];
            })->values()->toArray(),

            // 👤 Customer Mapping
            'customer' => [
                'first_name' => $data['customer']['first_name'] ?? ($data['billing_address']['first_name'] ?? 'Guest'),
                'last_name'  => $data['customer']['last_name'] ?? ($data['billing_address']['last_name'] ?? ''),
                'email'      => $data['customer']['email'] ?? $data['email'] ?? null,
                'phone'      => $data['customer']['phone'] ?? $data['billing_address']['phone'] ?? null,
                'address'    => $data['billing_address']['address1'] ?? null,
                'city'       => $data['billing_address']['city'] ?? null,
                'country'    => $data['billing_address']['country'] ?? null,
            ],

            // 🏬 Shop / Website info
            'shop' => [
                'domain'  => $request->header('X-Shopify-Shop-Domain'),
                'shop_id' => $order->shop_id ?? null,
            ],

            // 🕒 Metadata
            'synced_at' => now()->toISOString(),
        ];
    }


    /**
     * Handle Order Updated Webhook
     */
    public function orderUpdated(Request $request)
    {
        try {
            $data = $request->all();

            Log::info('♻️ Shopify Order Updated Webhook', [
                'payload' => $data,
            ]);

            $order = ShopifyOrder::updateOrCreate(
                ['shopify_order_id' => $data['id']],
                [
                    'shop_id'            => $this->getShopId($request),
                    'name'               => $data['name'] ?? null,
                    'status'             => $data['cancelled_at'] ? 'cancelled' : 'open',
                    'financial_status'   => $data['financial_status'] ?? null,
                    'fulfillment_status' => $data['fulfillment_status'] ?? null,
                    'total_price'        => $data['total_price'] ?? 0,
                    'currency'           => $data['currency'] ?? 'USD',
                    'raw_payload'        => json_encode($data),
                    'synced_at'          => now(),
                ]
            );

            $this->forwardToErp($request, '/webhooks/order-updated', [
                'order_id' => $data['id'],
                'status'   => $order->status,
                'updated_at' => now()->toISOString(),
            ]);

            return response()->json(['success' => true]);
        } catch (\Throwable $th) {
            Log::error('❌ Shopify Order Updated Webhook Failed', [
                'error'   => $th->getMessage(),
                'payload' => $request->all(),
            ]);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    public function productCreated(Request $request)
    {
        $data = $request->all();

        // Save locally
        $product = ShopifyProduct::updateOrCreate(
            ['shopify_product_id' => $data['id']],
            [
                'shop_id'            => $this->getShopId($request),
                'shopify_variant_id' => $data['variants'][0]['id'] ?? null,
                'inventory_item_id'  => $data['variants'][0]['inventory_item_id'] ?? null,
                'sku'                => $data['variants'][0]['sku'] ?? null,
                'title'              => $data['title'],
                'status'             => $data['status'] ?? 'active',
                'price'              => $data['variants'][0]['price'] ?? 0,
                'stock'              => $data['variants'][0]['inventory_quantity'] ?? null,
                'synced_at'          => now(),
            ]
        );

        // Transform contract
        $payload = [
            "product_id" => $data['id'],
            "sku"        => $product->sku,
            "title"      => $product->title,
            "status"     => $product->status,
            "price"      => $product->price,
            "currency"   => $data['currency'] ?? null, // safer: pull from order/shop not variant
            "stock"      => $product->stock,
            "shop" => [
                "domain"  => $request->header('X-Shopify-Shop-Domain'),
                "shop_id" => $product->shop_id,
            ],
            "synced_at" => now()->toISOString(),
        ];

        // Forward to ERP
        $this->forwardToErp($request, '/webhooks/product-created', $payload);

        return response()->json(['success' => true]);
    }

    /**
     * Handle Product Updated Webhook
     */
    public function productUpdated(Request $request)
    {
        try {
            $data = $request->all();

            Log::info('♻️ Shopify Product Updated Webhook', ['payload' => $data]);

            $product = ShopifyProduct::updateOrCreate(
                ['shopify_product_id' => $data['id']],
                [
                    'shop_id'            => $this->getShopId($request),
                    'shopify_variant_id' => $data['variants'][0]['id'] ?? null,
                    'inventory_item_id'  => $data['variants'][0]['inventory_item_id'] ?? null,
                    'title'     => $data['title'] ?? null,
                    'status'    => $data['status'] ?? 'active',
                    'price'     => $data['variants'][0]['price'] ?? 0,
                    'stock'     => $data['variants'][0]['inventory_quantity'] ?? 0,
                    'synced_at' => now(),
                ]
            );

            $this->forwardToErp($request, '/webhooks/product-updated', [
                'product_id' => $product->shopify_product_id,
                'status'     => $product->status,
                'price'      => $product->price,
                'stock'      => $product->stock,
                'synced_at'  => now()->toISOString(),
            ]);

            return response()->json(['success' => true]);
        } catch (\Throwable $th) {
            Log::error('❌ Shopify Product Updated Webhook Failed', [
                'error'   => $th->getMessage(),
                'payload' => $request->all(),
            ]);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle Product Deleted Webhook
     */
    public function productDeleted(Request $request)
    {
        try {
            $data = $request->all();

            Log::info('🗑️ Shopify Product Deleted Webhook', ['payload' => $data]);

            ShopifyProduct::where('shopify_product_id', $data['id'])->delete();

            $this->forwardToErp($request, '/webhooks/product-deleted', [
                'product_id' => $data['id'],
                'deleted_at' => now()->toISOString(),
            ]);

            return response()->json(['success' => true]);
        } catch (\Throwable $th) {
            Log::error('❌ Shopify Product Deleted Webhook Failed', [
                'error'   => $th->getMessage(),
                'payload' => $request->all(),
            ]);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle Inventory Level Updated Webhook
     */
    public function inventoryUpdated(Request $request)
    {
        try {
            $data = $request->all();

            Log::info('📦 Shopify Inventory Updated Webhook', ['payload' => $data]);

            if (isset($data['inventory_item_id'])) {
                ShopifyProduct::where('inventory_item_id', $data['inventory_item_id'])
                    ->update(['stock' => $data['available'] ?? 0]);
            }

            $this->forwardToErp($request, '/webhooks/inventory-updated', [
                'inventory_item_id' => $data['inventory_item_id'] ?? null,
                'available'         => $data['available'] ?? 0,
                'synced_at'         => now()->toISOString(),
            ]);

            return response()->json(['success' => true]);
        } catch (\Throwable $th) {
            Log::error('❌ Shopify Inventory Updated Webhook Failed', [
                'error'   => $th->getMessage(),
                'payload' => $request->all(),
            ]);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    private function getShopId(Request $request)
    {
        $domain = $request->header('X-Shopify-Shop-Domain');
        return ShopifyShop::where('shop_domain', $domain)->value('id');
    }

    private function forwardToErp(Request $request, string $endpoint, array $payload)
    {
        try {

            $shopDomain = $request->header('X-Shopify-Shop-Domain');
            Log::info('🟦 [ERP Forwarding] Incoming shop domain', ['shop_domain' => $shopDomain]);
            $shop = ShopifyShop::where('shop_domain', $shopDomain)->first();

            if (!$shop) {
                Log::warning('❌ [ERP Forwarding] No shop found in database for domain', [
                    'shop_domain' => $shopDomain,
                ]);
                return;
            }

            if (!$shop->erpIntegration) {
                Log::warning('⚠️ [ERP Forwarding] No ERP integration configured for shop', [
                    'shop_id' => $shop->id,
                ]);
                return;
            }

            $erpUrl = rtrim($shop->erpIntegration->erp_url, '/') . $endpoint;
            $erpToken = $shop->erpIntegration->erp_secret;

            Log::info('➡️ [ERP Forwarding] Sending payload to ERP', [
                'erp_url' => $erpUrl,
                'endpoint' => $endpoint,
                'payload_preview' => array_slice($payload, 0, 5), // limit log size
            ]);

            $response = Http::withToken($erpToken)
                ->timeout(15)
                ->acceptJson()
                ->post($erpUrl, $payload);

            Log::info('⬅️ [ERP Forwarding Response]', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            if ($response->failed()) {
                Log::error('🚨 [ERP Forwarding Failed]', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('🔥 [ERP Forwarding Exception]', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
