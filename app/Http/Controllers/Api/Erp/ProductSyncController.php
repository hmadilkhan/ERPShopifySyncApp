<?php

namespace App\Http\Controllers\Api\Erp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Erp\ProductSyncRequest;
use App\Models\ShopifyShop;
use App\Models\ShopifyProduct;
use Illuminate\Support\Facades\Http;

class ProductSyncController extends Controller
{
    public function syncProduct(ProductSyncRequest $request)
    {
        $data = $request->validated()['product'];
        $shop = ShopifyShop::first(); // TODO: map ERP → correct shop

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

        //  // ✅ Build ERP payload
        //  $payload = [
        //     'product' => [
        //         'sku'          => $product->sku,
        //         'title'        => $product->title,
        //         'description'  => $data['body_html'] ?? null,
        //         'price'        => $product->price,
        //         'currency'     => $data['variants'][0]['currency'] ?? $data['currency'] ?? 'USD',
        //         'stock'        => $product->stock,
        //         'vendor'       => $data['vendor'] ?? null,
        //         'product_type' => $data['product_type'] ?? null,
        //         'status'       => $product->status,

        //         // ✅ Variants list
        //         'variants' => collect($data['variants'] ?? [])->map(function ($variant) {
        //             return [
        //                 'sku'    => $variant['sku'] ?? null,
        //                 'option' => implode(' / ', array_filter([
        //                     $variant['option1'] ?? null,
        //                     $variant['option2'] ?? null,
        //                     $variant['option3'] ?? null,
        //                 ])),
        //                 'price'  => $variant['price'] ?? 0,
        //                 'stock'  => $variant['inventory_quantity'] ?? 0,
        //             ];
        //         })->values()->toArray(),

        //         // ✅ Images list
        //         'images' => collect($data['images'] ?? [])->pluck('src')->values()->toArray(),
        //     ],
        // ];

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $shop->access_token,
        ])->post("https://{$shop->shop_domain}/admin/api/2025-01/products.json", $payload);

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
        ], $response->status());
    }
}
