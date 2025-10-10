<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\ShopifyShop;

class ShopifyWebhookService
{
    public static function register(ShopifyShop $shop)
    {
        $topics = [
            'orders/create',
            'orders/updated',
            'products/create',
            'products/update',
            'products/delete',
            'inventory_levels/update',
            'fulfillments/create',
            'fulfillments/update',
            'read_locations'
        ];

        foreach ($topics as $topic) {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $shop->access_token,
            ])->post("https://{$shop->shop_domain}/admin/api/2025-01/webhooks.json", [
                "webhook" => [
                    "topic" => $topic,
                    "address" => config('app.url') . "/api/webhooks/shopify/{$topic}",
                    "format" => "json",
                ]
            ]);
        }
    }

    /**
     * ✅ Get all registered webhooks for a given shop
     */
    public static function getAll(ShopifyShop $shop)
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $shop->access_token,
        ])->get("https://{$shop->shop_domain}/admin/api/2025-01/webhooks.json");

        if ($response->successful()) {
            return $response->json('webhooks'); // returns array of all webhooks
        }

        \Log::error('Failed to fetch Shopify webhooks', [
            'shop' => $shop->shop_domain,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return [];
    }

    public static function deleteAll(ShopifyShop $shop)
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $shop->access_token,
        ])->get("https://{$shop->shop_domain}/admin/api/2025-01/webhooks.json");

        $webhooks = $response->json()['webhooks'] ?? [];

        foreach ($webhooks as $webhook) {
            Http::withHeaders([
                'X-Shopify-Access-Token' => $shop->access_token,
            ])->delete("https://{$shop->shop_domain}/admin/api/2025-01/webhooks/{$webhook['id']}.json");
        }

        return count($webhooks);
    }
}
