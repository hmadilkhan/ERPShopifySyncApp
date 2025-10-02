<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\ShopifyShop;

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

    // More endpoints like update inventory, update order status, etc.
}
