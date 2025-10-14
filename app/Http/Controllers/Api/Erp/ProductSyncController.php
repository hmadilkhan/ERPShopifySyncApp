<?php

namespace App\Http\Controllers\Api\Erp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Erp\ProductSyncRequest;
use App\Models\ShopifyShop;
use App\Models\ShopifyProduct;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class ProductSyncController extends Controller
{
    public function syncProduct(Request $request)
    {
        try {
            // Validate the request manually using the same rules from ProductSyncRequest
            $validated = $request->validate([
                'product.id' => 'nullable|integer',
                'product.sku' => 'required|string',
                'product.title' => 'required|string',
                'product.description' => 'nullable|string',
                'product.price' => 'required|numeric|min:0',
                'product.currency' => 'required|string|size:3',
                'product.stock' => 'required|integer|min:0',
                'product.vendor' => 'nullable|string',
                'product.product_type' => 'nullable|string',
                'product.status' => 'required|in:active,draft,archived',
                'product.variants.*.sku' => 'nullable|string',
                'product.variants.*.option' => 'nullable|string',
                'product.variants.*.price' => 'numeric',
                'product.variants.*.stock' => 'integer|min:0',
                'product.images.*' => 'url'
            ]);

            $data = $validated['product'];
            $shop = ShopifyShop::first(); // TODO: map ERP → correct shop

            // ✅ Find existing product by SKU (and shop)
            $existingProduct = ShopifyProduct::where('erp_product_id', $data['id'])
                ->where('shop_id', $shop->id)
                ->first();


            $payload = [
                "product" => [
                    "title" => $data['title'],
                    "body_html" => $data['description'] ?? '',
                    "vendor" => $data['vendor'] ?? 'ERP',
                    "price" => $data['price'],
                    "currency" => $data['currency'],
                    "stock" => $data['stock'],
                    "product_type" => $data['product_type'] ?? '',
                    "status" => $data['status'],
                    "variants" => collect($data['variants'] ?? [])->map(function ($variant) {
                        return [
                            "sku" => $variant['sku'] ?? null,
                            "option1" => $variant['option'] ?? 'Default',
                            "price" => $variant['price'] ?? null,
                            "inventory_quantity" => $variant['stock'] ?? 0,
                        ];
                    })->values()->toArray(),
                    "images" => array_map(fn($img) => ["src" => $img], $data['images'] ?? [])
                ]
            ];

            // ✅ Choose CREATE or UPDATE endpoint
            if ($existingProduct && $existingProduct->shopify_product_id) {
                // 🔁 Update existing Shopify product
                $shopifyUrl = "https://{$shop->shop_domain}/admin/api/2025-01/products/{$existingProduct->shopify_product_id}.json";
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $shop->access_token,
                ])->put($shopifyUrl, $payload);
            } else {

                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $shop->access_token,
                ])->post("https://{$shop->shop_domain}/admin/api/2025-01/products.json", $payload);
            }

            $result = $response->json();

            // ✅ Save mapping in local DB
            if (isset($result['product'])) {
                ShopifyProduct::updateOrCreate(
                    ['sku' => $data['sku'] ?? ($data['variants'][0]['sku'] ?? null), 'shop_id' => $shop->id],
                    [
                        'erp_product_id'      => $data['id'] ?? null,
                        'shopify_product_id'  => $result['product']['id'],
                        'shopify_variant_id'  => $result['product']['variants'][0]['id'] ?? null,
                        'inventory_item_id'   => $result['product']['variants'][0]['inventory_item_id'] ?? null,
                        'title'               => $result['product']['title'],
                        'price'               => $result['product']['variants'][0]['price'] ?? 0,
                        'stock'               => $result['product']['variants'][0]['inventory_quantity'] ?? 0,
                        'status'              => $result['product']['status'] ?? 'active',
                        'synced_at'           => now(),
                    ]
                );
            }

            return response()->json([
                'success' => $response->successful(),
                'shopify_response' => $result,
                'id' => $data['id']
            ], $response->status());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
