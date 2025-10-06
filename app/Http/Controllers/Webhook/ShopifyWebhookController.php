<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\ShopifyOrder;
use App\Models\ShopifyProduct;
use App\Models\ShopifyShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
            $payload = [
                "order_id"           => $data['id'],
                "name"               => $data['name'],
                "status"             => $order->status,
                "financial_status"   => $order->financial_status,
                "fulfillment_status" => $order->fulfillment_status,
                "currency"           => $order->currency,
                "total_price"        => $order->total_price,
                "line_items"         => collect($data['line_items'])->map(fn($item) => [
                    "product_id" => $item['product_id'],
                    "sku"        => $item['sku'] ?? null,
                    "title"      => $item['title'],
                    "quantity"   => $item['quantity'],
                    "price"      => $item['price'],
                ])->toArray(),
                "customer" => [
                    "first_name" => $data['customer']['first_name'] ?? null,
                    "last_name"  => $data['customer']['last_name'] ?? null,
                    "email"      => $data['customer']['email'] ?? null,
                    "phone"      => $data['customer']['phone'] ?? null,
                ],
                "shop" => [
                    "domain"  => $request->header('X-Shopify-Shop-Domain'),
                    "shop_id" => $order->shop_id,
                ],
                "synced_at" => now()->toISOString(),
            ];

            // Forward to ERP
            $this->forwardToErp($request, '/webhooks/order-created', $payload);

            return response()->json(['success' => true]);
        } catch (\Throwable $th) {
            // 🔹 Log the error with detailed trace
            \Log::error('❌ Shopify Order Created Webhook Failed', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            // (Optional) Return 500 so Shopify retries
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle Order Updated Webhook
     */
    public function orderUpdated(Request $request)
    {
        try {
            $data = $request->all();

            \Log::info('♻️ Shopify Order Updated Webhook', [
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
            \Log::error('❌ Shopify Order Updated Webhook Failed', [
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

            \Log::info('♻️ Shopify Product Updated Webhook', ['payload' => $data]);

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
            \Log::error('❌ Shopify Product Updated Webhook Failed', [
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

            \Log::info('🗑️ Shopify Product Deleted Webhook', ['payload' => $data]);

            ShopifyProduct::where('shopify_product_id', $data['id'])->delete();

            $this->forwardToErp($request, '/webhooks/product-deleted', [
                'product_id' => $data['id'],
                'deleted_at' => now()->toISOString(),
            ]);

            return response()->json(['success' => true]);
        } catch (\Throwable $th) {
            \Log::error('❌ Shopify Product Deleted Webhook Failed', [
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

            \Log::info('📦 Shopify Inventory Updated Webhook', ['payload' => $data]);

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
            \Log::error('❌ Shopify Inventory Updated Webhook Failed', [
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
        $shopDomain = $request->header('X-Shopify-Shop-Domain');
        $shop = ShopifyShop::where('shop_domain', $shopDomain)->first();

        if ($shop && $shop->erpIntegration) {
            Http::withToken($shop->erpIntegration->erp_secret)
                ->post($shop->erpIntegration->erp_url . $endpoint, $payload);
        }
    }
}
