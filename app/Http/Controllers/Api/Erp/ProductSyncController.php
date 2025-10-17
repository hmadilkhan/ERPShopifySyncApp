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
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing Bearer token'
                ], 401);
            }
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
            $shop = ShopifyShop::where('erp_secret', $token)->first();

            // ✅ Find existing product by SKU (and shop)
            $existingProduct = ShopifyProduct::where('erp_product_id', $data['id'])
                ->where('shop_id', $shop->id)
                ->first();




            // // ✅ Include product ID only if updating
            // if (!empty($data['id'])) {
            //     $payload['product']['id'] = $data['id'];
            // }

            // // ✅ Only include variants if not empty
            // if (!empty($data['variants'])) {
            //     $payload['product']['variants'] = collect($data['variants'])
            //         ->filter(function ($variant) {
            //             return !empty($variant['sku']);
            //         })
            //         ->map(function ($variant) {
            //             $variantPayload = [
            //                 'sku'                 => $variant['sku'] ?? null,
            //                 'option1'             => $variant['option'] ?? 'Default',
            //                 'price'               => $variant['price'] ?? 0,
            //                 'inventory_quantity'  => (int)($variant['stock'] ?? 0),
            //             ];

            //             // ✅ Include variant ID if exists (for updates)
            //             if (!empty($variant['shopify_variant_id'])) {
            //                 $variantPayload['id'] = $variant['shopify_variant_id'];
            //             }

            //             return $variantPayload;
            //         })
            //         ->values()
            //         ->toArray();
            // }

            // // ✅ Choose CREATE or UPDATE endpoint
            // if ($existingProduct && $existingProduct->shopify_product_id) {
            //     // 🔁 Update existing Shopify product
            //     $shopifyUrl = "https://{$shop->shop_domain}/admin/api/2025-01/products/{$existingProduct->shopify_product_id}.json";
            //     $response = Http::withHeaders([
            //         'X-Shopify-Access-Token' => $shop->access_token,
            //     ])->put($shopifyUrl, $payload);
            // } else {

            //     $response = Http::withHeaders([
            //         'X-Shopify-Access-Token' => $shop->access_token,
            //     ])->post("https://{$shop->shop_domain}/admin/api/2025-01/products.json", $payload);
            // }

            // 🧱 Build base payload
            $payload = [
                'product' => [
                    'title'        => $data['title'],
                    'body_html'    => $data['description'] ?? '',
                    'vendor'       => $data['vendor'] ?? 'ERP',
                    'product_type' => $data['product_type'] ?? '',
                    'status'       => $data['status'] ?? 'active',
                    'images'       => array_map(fn($img) => ['src' => $img], $data['images'] ?? []),
                ],
            ];

            // 🔹 Include product ID if updating
            if (!empty($data['id'])) {
                $payload['product']['id'] = $data['id'];
            }

            // 🔹 Handle variants
            if (!empty($data['variants'])) {
                // Map all provided variants
                $payload['product']['variants'] = collect($data['variants'])
                    ->filter(fn($v) => !empty($v['sku']))
                    ->map(function ($variant) {
                        $variantPayload = [
                            'sku'                 => $variant['sku'] ?? null,
                            'option1'             => $variant['option'] ?? 'Default Title',
                            'price'               => $variant['price'] ?? 0,
                            'inventory_quantity'  => (int)($variant['stock'] ?? 0),
                            'inventory_management' => 'shopify',  // ✅ Enables tracking
                            'inventory_policy'    => 'deny',     // Optional: prevent overselling
                        ];

                        // ✅ Include image if provided
                        if (!empty($variant['image'])) {
                            // Shopify expects an object with src
                            $variantPayload['image'] = [
                                'src' => $variant['image'],
                            ];
                        }

                        if (!empty($variant['shopify_variant_id'])) {
                            $variantPayload['id'] = $variant['shopify_variant_id'];
                        }

                        return $variantPayload;
                    })
                    ->values()
                    ->toArray();
            } else {
                // ✅ Ensure at least one variant exists
                $variant = [
                    'sku'                 => $data['sku'] ?? 'NO-SKU',
                    'option1'             => 'Default Title',
                    'price'               => $data['price'] ?? 0,
                    'inventory_quantity'  => (int)($data['stock'] ?? 0),
                    'inventory_management' => 'shopify',  // ✅ Enables tracking
                    'inventory_policy'    => 'deny',     // Optional: prevent overselling
                ];

                // ✅ Single variant image if exists
                if (!empty($data['image'])) {
                    $variant['image'] = [
                        'src' => $data['image'],
                    ];
                }

                // Include variant ID if exists (update)
                if (!empty($data['shopify_variant_id'])) {
                    $variant['id'] = $data['shopify_variant_id'];
                }

                $payload['product']['variants'] = [$variant];
            }

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

            $this->updateImagesToVariant($result, $shop);

            // ✅ Determine if this is create or update
            $isNewProduct = !$existingProduct || !$existingProduct->shopify_product_id;

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

                // 🧾 Get the created or updated product data
                $productData = $response->json('product');

                // 🔹 Optional: Sync inventory via API (more reliable for updates)
                if (!empty($productData['variants'][0]['inventory_item_id'])) {
                    $locationResponse = Http::withHeaders([
                        'X-Shopify-Access-Token' => $shop->access_token,
                    ])->get("https://{$shop->shop_domain}/admin/api/2025-01/locations.json");

                    $locationId = $locationResponse->json('locations')[0]['id'] ?? null;

                    if ($locationId) {
                        // 🧩 STEP 1: CONNECT only if NEW product
                        if ($isNewProduct) {
                            Http::withHeaders([
                                'X-Shopify-Access-Token' => $shop->access_token,
                                'Content-Type' => 'application/json',
                            ])->post("https://{$shop->shop_domain}/admin/api/2025-01/inventory_levels/connect.json", [
                                'location_id' => $locationId,
                                'inventory_item_id' => $productData['variants'][0]['inventory_item_id'],
                                'relocate_if_necessary' => true,
                            ]);
                        }

                        Http::withHeaders([
                            'X-Shopify-Access-Token' => $shop->access_token,
                        ])->post("https://{$shop->shop_domain}/admin/api/2025-01/inventory_levels/set.json", [
                            'location_id'       => $locationId,
                            'inventory_item_id' => $productData['variants'][0]['inventory_item_id'],
                            'available'         => (int)($data['stock'] ?? 0),
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => $response->successful(),
                'shopify_response' => $result,

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
                'message' => 'An error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function updateImagesToVariant($syncResponse, $shop)
    {
        // Assuming you already have these:
        $payload = $syncResponse['payload'];     // ERP product payload
        $shopifyResponse = $syncResponse['data']; // Shopify API response (after product created)
        $product = $shopifyResponse['product'] ?? null;

        if (!$product) {
            \Log::error('❌ Missing product data in Shopify response.');
            return;
        }

        $images   = $product['images'] ?? [];
        $variants = $product['variants'] ?? [];
        $erpVariants = $payload['product']['variants'] ?? [];

        // 1️⃣ Build map of image src → Shopify image_id
        $imageMap = collect($images)->mapWithKeys(fn($img) => [$img['src'] => $img['id']]);

        // 2️⃣ Loop Shopify variants and link their ERP image
        foreach ($variants as $variant) {
            // Find matching ERP variant by SKU
            $erpVariant = collect($erpVariants)->firstWhere('sku', $variant['sku']);
            if (!$erpVariant || empty($erpVariant['image']['src'])) continue;

            $variantImageUrl = $erpVariant['image']['src'];

            // Match ERP image URL to Shopify image_id
            $imageId = $imageMap[$variantImageUrl] ?? null;
            if (!$imageId) {
                \Log::warning("⚠️ No Shopify image match found for variant {$variant['sku']} ($variantImageUrl)");
                continue;
            }

            // 3️⃣ Send request to link the image to the variant
            Http::withHeaders([
                'X-Shopify-Access-Token' => $shop->access_token,
                'Content-Type' => 'application/json',
            ])->put("https://{$shop->shop_domain}/admin/api/2025-01/variants/{$variant['id']}.json", [
                'variant' => [
                    'id' => $variant['id'],
                    'image_id' => $imageId,
                ]
            ]);

            \Log::info("✅ Linked variant {$variant['sku']} to Shopify image ID {$imageId}");
        }
    }

    protected function shopifyRequest($method, $endpoint, $body = [], $shop = null)
    {
        // if shop info not passed, get from config or wherever you store it
        $shopDomain  = $shop->shop_domain ?? config('shopify.shop_domain');
        $accessToken = $shop->access_token ?? config('shopify.access_token');

        $url = "https://{$shopDomain}{$endpoint}";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $accessToken,
            ])->send($method, $url, ['json' => $body]);

            if ($response->failed()) {
                \Log::error('❌ Shopify API Request Failed', [
                    'url' => $url,
                    'method' => $method,
                    'body' => $body,
                    'response' => $response->body(),
                ]);
            }

            return $response->json();
        } catch (\Exception $e) {
            \Log::error('❌ Shopify API Exception', [
                'url' => $url,
                'method' => $method,
                'body' => $body,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
