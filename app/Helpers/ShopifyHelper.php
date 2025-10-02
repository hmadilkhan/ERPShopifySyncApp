<?php

use Illuminate\Support\Facades\Http;

if (! function_exists('registerShopifyWebhook')) {
    function registerShopifyWebhook($shop, $token, $topic, $endpoint)
    {
        return Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
        ])->post("https://{$shop}/admin/api/2025-01/webhooks.json", [
            "webhook" => [
                "topic"   => $topic,
                "address" => url($endpoint),
                "format"  => "json",
            ]
        ])->json();
    }
}
