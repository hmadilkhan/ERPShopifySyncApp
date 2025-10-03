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
        return $request;
        $data = $request->validated()['product'];
        $shop = ShopifyShop::first(); // TODO: map ERP → correct shop

        $payload = [
            "product" => [
                "title" => $data['title'],
                "body_html" => $data['description'] ?? '',
                "vendor" => $data['vendor'] ?? 'ERP',
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

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $shop->access_token,
        ])->post("https://{$shop->shop_domain}/admin/api/2025-01/products.json", $payload);

        $result = $response->json();

        // Save mapping
        if (isset($result['product'])) {
            ShopifyProduct::updateOrCreate(
                ['sku' => $data['sku'], 'shop_id' => $shop->id],
                [
                    'shopify_product_id' => $result['product']['id'],
                    'shopify_variant_id' => $result['product']['variants'][0]['id'] ?? null,
                    'inventory_item_id' => $result['product']['variants'][0]['inventory_item_id'] ?? null,
                    'synced_at' => now(),
                ]
            );
        }

        return $result;
    }
}
