<?php

use Illuminate\Support\Facades\Http;

if (! function_exists('registerShopifyWebhook')) {
    function registerShopifyWebhook($shop, $token, $topic, $endpoint)
    {
        $url = url($endpoint);

        // Step 1: Get existing webhooks
        $existing = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
        ])->get("https://{$shop}/admin/api/2025-01/webhooks.json")->json();

        $exists = collect($existing['webhooks'] ?? [])->first(function ($webhook) use ($topic, $url) {
            return $webhook['topic'] === $topic && $webhook['address'] === $url;
        });

        // Step 2: If exists → skip
        if ($exists) {
            return [
                'status' => 'exists',
                'webhook' => $exists
            ];
        }

        // Step 3: Otherwise → create webhook
        $new = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
        ])->post("https://{$shop}/admin/api/2025-01/webhooks.json", [
            "webhook" => [
                "topic"   => $topic,
                "address" => $url,
                "format"  => "json"
            ]
        ])->json();

        return [
            'status' => 'created',
            'webhook' => $new
        ];
    }
}
