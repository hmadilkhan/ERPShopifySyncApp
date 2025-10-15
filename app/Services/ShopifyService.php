<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\ShopifyShop;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    protected $shop;

    public function __construct(ShopifyShop $shop)
    {
        $this->shop = $shop;
    }

    private function headers()
    {
        return [
            'X-Shopify-Access-Token' => $this->shop->access_token,
        ];
    }

    public function getProducts()
    {
        return Http::withHeaders($this->headers())
            ->get("https://{$this->shop->shop_domain}/admin/api/2025-01/products.json", [
                'status' => 'active'
            ])
            ->json();
    }

    public function getOrders()
    {
        return Http::withHeaders($this->headers())
            ->get("https://{$this->shop->shop_domain}/admin/api/2025-01/orders.json")
            ->json();
    }

    public function createProduct($data)
    {
        return Http::withHeaders($this->headers())
            ->post("https://{$this->shop->shop_domain}/admin/api/2025-01/products.json", [
                'product' => $data
            ])
            ->json();
    }

    public function getShopifyLocationId($shop)
    {
        // 🟢 If already cached in DB, reuse it
        if (!empty($shop->shopify_location_id)) {
            return $shop->shopify_location_id;
        }

        // 🧾 Fetch from Shopify API
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $shop->access_token,
        ])->get("https://{$shop->shop_domain}/admin/api/2025-01/locations.json");

        if ($response->failed()) {
            Log::error('Failed to fetch Shopify locations', [
                'shop' => $shop->shop_domain,
                'response' => $response->body(),
            ]);
            return null;
        }

        $locations = $response->json('locations') ?? [];

        if (empty($locations)) {
            Log::warning("No locations found for shop: {$shop->shop_domain}");
            return null;
        }

        // 🏬 Usually, use the first active location
        $locationId = $locations[0]['id'] ?? null;

        // 💾 Save it to your database for future use
        if ($locationId) {
            $shop->update(['shopify_location_id' => $locationId]);
        }

        return $locationId;
    }

    // More endpoints like update inventory, update order status, etc.
}
