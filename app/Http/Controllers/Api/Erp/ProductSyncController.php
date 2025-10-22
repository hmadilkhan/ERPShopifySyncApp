<?php

namespace App\Http\Controllers\Api\Erp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Erp\ProductSyncRequest;
use App\Models\ShopifyShop;
use App\Models\ShopifyProduct;
use App\Models\ShopifyProductVariant;
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
                'product.variants.*.id' => 'nullable|numeric',
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
            // $this->updateImagesToVariantGraphQL($result, $shop);

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

                if (isset($result['product']['variants']) && !empty($result['product']['variants'])) {
                    foreach ($result['product']['variants'] as $index => $variantData) {
                        $erpVariant = $data['variants'][$index] ?? null;

                        ShopifyProductVariant::updateOrCreate(
                            [
                                'shopify_variant_id' => $variantData['id'],
                                'shop_id' => $shop->id,
                            ],
                            [
                                'shopify_product_id' => $result['product']['id'],
                                'erp_variant_id' => $erpVariant['id'] ?? null, // ERP variant id if available
                                'inventory_item_id' => $variantData['inventory_item_id'] ?? null,
                                'sku'               => $variantData['sku'] ?? null,
                                'title'             => $variantData['title'] ?? null,
                                'price'             => $variantData['price'] ?? 0,
                                'stock'             => $variantData['inventory_quantity'] ?? 0,
                                'image_url'         => $erpVariant['image'] ?? null,
                            ]
                        );
                    }
                }

                // 🧾 Get the created or updated product data
                $productData = $response->json('product');

                // 🔹 Optional: Sync inventory via API (more reliable for updates)
                if (!empty($productData['variants'])) {

                    // Get location
                    $locationResponse = Http::withHeaders([
                        'X-Shopify-Access-Token' => $shop->access_token,
                    ])->get("https://{$shop->shop_domain}/admin/api/2025-01/locations.json");

                    $locationId = $locationResponse->json('locations')[0]['id'] ?? null;

                    if ($locationId) {
                        foreach ($productData['variants'] as $variant) {
                            $inventoryItemId = $variant['inventory_item_id'] ?? null;
                            $variantId = $variant['id'] ?? null;

                            if (!$inventoryItemId || !$variantId) {
                                continue;
                            }

                            // 🧩 STEP 1: CONNECT only if NEW product
                            if ($isNewProduct) {
                                Http::withHeaders([
                                    'X-Shopify-Access-Token' => $shop->access_token,
                                    'Content-Type' => 'application/json',
                                ])->post("https://{$shop->shop_domain}/admin/api/2025-01/inventory_levels/connect.json", [
                                    'location_id' => $locationId,
                                    'inventory_item_id' => $inventoryItemId,
                                    'relocate_if_necessary' => true,
                                ]);
                            }

                            // 🧩 STEP 2: SET inventory (variant-specific if available)
                            $variantStock = 0;

                            if (!empty($data['variants']) && is_array($data['variants'])) {
                                // Try to match variant by SKU or by ID if provided
                                foreach ($data['variants'] as $inputVariant) {
                                    if (
                                        (isset($inputVariant['id']) && $inputVariant['id'] == $variantId) ||
                                        (isset($inputVariant['sku']) && $inputVariant['sku'] == ($variant['sku'] ?? null))
                                    ) {
                                        $variantStock = (int)($inputVariant['stock'] ?? (int)$data["stock"]);
                                        break;
                                    }
                                }
                            }

                            // Fallback to main stock if variant stock not found
                            if ($variantStock === 0 && isset($data['stock'])) {
                                $variantStock = (int)$data['stock'];
                            }

                            // Update stock for this variant
                            Http::withHeaders([
                                'X-Shopify-Access-Token' => $shop->access_token,
                            ])->post("https://{$shop->shop_domain}/admin/api/2025-01/inventory_levels/set.json", [
                                'location_id'       => $locationId,
                                'inventory_item_id' => $inventoryItemId,
                                'available'         => $variantStock,
                            ]);
                        }
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

    private function updateImagesToVariant($syncResponse, $shop, $maxAttempts = 3, $delaySeconds = 3)
    {
        try {
            $erpPayload     = $syncResponse['payload']['product'] ?? null;
            $shopifyProduct = $syncResponse['data']['shopify_response']['product'] ?? null;

            if (empty($erpPayload) || empty($shopifyProduct)) {
                \Log::warning("⚠️ Missing ERP payload or Shopify product in sync response");
                return;
            }

            $erpVariants     = $erpPayload['variants'] ?? [];
            $shopifyVariants = $shopifyProduct['variants'] ?? [];

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                \Log::info("🔁 Attempt {$attempt}/{$maxAttempts} — fetching latest images for product {$shopifyProduct['id']}");

                $fetchUrl = "https://{$shop->shop_domain}/admin/api/2025-01/products/{$shopifyProduct['id']}.json";
                $productResponse = Http::withHeaders([
                    'X-Shopify-Access-Token' => $shop->access_token,
                ])->get($fetchUrl);

                if (!$productResponse->successful()) {
                    \Log::error("❌ Failed to fetch Shopify product: " . $productResponse->body());
                    return;
                }

                $latestProduct = $productResponse->json('product');
                $shopifyImages = $latestProduct['images'] ?? [];

                // 🔹 Create map of filename → image_id
                $imageMap = collect($shopifyImages)->mapWithKeys(function ($img) {
                    $filename = basename(parse_url($img['src'], PHP_URL_PATH));
                    return [$filename => $img['id']];
                });

                // 🧩 Link variant images
                foreach ($shopifyVariants as $variant) {
                    $erpVariant = collect($erpVariants)->firstWhere('sku', $variant['sku']);

                    if (empty($erpVariant['image']['src'])) {
                        \Log::warning("⚠️ ERP image missing for SKU {$variant['sku']}");
                        continue;
                    }

                    $erpImageUrl = $erpVariant['image']['src'];
                    $erpFilename = basename(parse_url($erpImageUrl, PHP_URL_PATH));
                    $imageId = $imageMap[$erpFilename] ?? null;

                    if (!$imageId) {
                        \Log::warning("⚠️ Image not found for SKU {$variant['sku']} ({$erpFilename}) (Attempt {$attempt})");
                        continue;
                    }

                    // ✅ Assign image_id to variant
                    $updateUrl = "https://{$shop->shop_domain}/admin/api/2025-01/variants/{$variant['id']}.json";
                    $response = Http::withHeaders([
                        'X-Shopify-Access-Token' => $shop->access_token,
                        'Content-Type' => 'application/json',
                    ])->put($updateUrl, [
                        'variant' => [
                            'id' => $variant['id'],
                            'image_id' => $imageId,
                        ]
                    ]);

                    if ($response->successful()) {
                        \Log::info("✅ Linked variant {$variant['sku']} → image ID {$imageId}");
                    } else {
                        \Log::error("❌ Failed to link variant {$variant['sku']}: " . $response->body());
                    }
                }

                // 🕒 Check if all linked
                $unlinked = collect($erpVariants)->filter(function ($erpVar) use ($imageMap) {
                    $filename = basename(parse_url($erpVar['image']['src'] ?? '', PHP_URL_PATH));
                    return empty($imageMap[$filename]);
                });

                if ($unlinked->isEmpty()) {
                    \Log::info("✅ All variant images successfully linked on attempt {$attempt}");
                    break;
                }

                sleep($delaySeconds);
            }
        } catch (\Throwable $e) {
            \Log::error("❌ Error in updateImagesToVariant: {$e->getMessage()}");
        }
    }

    private function updateImagesToVariantGraphQL($syncResponse, $shop, $maxAttempts = 3, $delaySeconds = 3)
    {
        try {
            $erpPayload     = $syncResponse['payload']['product'] ?? null;
            $shopifyProduct = $syncResponse['data']['shopify_response']['product'] ?? null;

            if (empty($erpPayload) || empty($shopifyProduct)) {
                \Log::warning("⚠️ Missing ERP payload or Shopify product in sync response");
                return;
            }

            $erpVariants     = $erpPayload['variants'] ?? [];
            $shopifyVariants = $shopifyProduct['variants'] ?? [];

            // 🔁 Retry loop to handle image delays
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                \Log::info("🔁 Attempt {$attempt}/{$maxAttempts} — fetching latest images for product {$shopifyProduct['id']}");

                // Fetch latest product (to ensure image IDs exist)
                $fetchUrl = "https://{$shop->shop_domain}/admin/api/2025-01/products/{$shopifyProduct['id']}.json";
                $productResponse = Http::withHeaders([
                    'X-Shopify-Access-Token' => $shop->access_token,
                ])->get($fetchUrl);

                if (!$productResponse->successful()) {
                    \Log::error("❌ Failed to fetch Shopify product: " . $productResponse->body());
                    return;
                }

                $latestProduct = $productResponse->json('product');
                $shopifyImages = $latestProduct['images'] ?? [];

                // 🔹 Build image map (filename → image_id)
                $imageMap = collect($shopifyImages)->mapWithKeys(function ($img) {
                    $filename = basename(parse_url($img['src'], PHP_URL_PATH));
                    return [$filename => $img['id']];
                });

                // 🧩 Loop through variants
                foreach ($shopifyVariants as $variant) {
                    $erpVariant = collect($erpVariants)->firstWhere('sku', $variant['sku']);

                    if (empty($erpVariant['image']['src'])) {
                        \Log::warning("⚠️ ERP image missing for SKU {$variant['sku']}");
                        continue;
                    }

                    $erpImageUrl = $erpVariant['image']['src'];
                    $erpFilename = basename(parse_url($erpImageUrl, PHP_URL_PATH));
                    $imageId = $imageMap[$erpFilename] ?? null;

                    if (!$imageId) {
                        \Log::warning("⚠️ Image not found for SKU {$variant['sku']} ({$erpFilename}) (Attempt {$attempt})");
                        continue;
                    }

                    // 🔹 Convert numeric IDs to GraphQL GIDs
                    $variantGid = "gid://shopify/ProductVariant/{$variant['id']}";
                    $imageGid   = "gid://shopify/ProductImage/{$imageId}";

                    // 🧠 GraphQL mutation
                    $mutation = <<<GQL
                mutation UpdateVariantImage(\$variantId: ID!, \$imageId: ID!) {
                  productVariantUpdate(input: {id: \$variantId, imageId: \$imageId}) {
                    productVariant {
                      id
                      title
                      image {
                        id
                        src
                      }
                    }
                    userErrors {
                      field
                      message
                    }
                  }
                }
                GQL;

                    $graphqlUrl = "https://{$shop->shop_domain}/admin/api/2025-01/graphql.json";
                    $response = Http::withHeaders([
                        'X-Shopify-Access-Token' => $shop->access_token,
                        'Content-Type' => 'application/json',
                    ])->post($graphqlUrl, [
                        'query' => $mutation,
                        'variables' => [
                            'variantId' => $variantGid,
                            'imageId'   => $imageGid,
                        ],
                    ]);

                    $json = $response->json();
                    $errors = $json['data']['productVariantUpdate']['userErrors'] ?? [];

                    if (!empty($errors)) {
                        \Log::error("❌ GraphQL error linking image to {$variant['sku']}: " . json_encode($errors));
                        continue;
                    }

                    if ($response->successful()) {
                        \Log::info("✅ Linked variant {$variant['sku']} → image ID {$imageId}");
                    } else {
                        \Log::error("❌ Failed to link variant {$variant['sku']}: " . $response->body());
                    }
                }

                // 🕒 Check if all variants have linked images
                $unlinked = collect($erpVariants)->filter(function ($erpVar) use ($imageMap) {
                    $filename = basename(parse_url($erpVar['image']['src'] ?? '', PHP_URL_PATH));
                    return empty($imageMap[$filename]);
                });

                if ($unlinked->isEmpty()) {
                    \Log::info("✅ All variant images successfully linked on attempt {$attempt}");
                    break;
                }

                sleep($delaySeconds);
            }
        } catch (\Throwable $e) {
            \Log::error("❌ Error in updateImagesToVariant: {$e->getMessage()}");
        }
    }
}
