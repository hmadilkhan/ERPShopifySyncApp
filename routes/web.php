<?php

use App\Models\ShopifyShop;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/shopify/install', function (Request $request) {
    $shop = $request->query('shop'); // e.g. my-store.myshopify.com

    $apiKey = config('shopify.api_key');
    $scopes = "read_orders,write_orders,read_products,write_products,read_inventory,write_inventory";
    $redirectUri = url('/shopify/callback');

    $installUrl = "https://{$shop}/admin/oauth/authorize?client_id={$apiKey}&scope={$scopes}&redirect_uri={$redirectUri}";

    return redirect($installUrl);
});

Route::get('/shopify/callback', function (Request $request) {
    $shop = $request->query('shop');
    $code = $request->query('code');

    $apiKey = config('shopify.api_key');
    $secret = config('shopify.api_secret');

    // 1. Exchange code for access token
    $response = Http::post("https://{$shop}/admin/oauth/access_token", [
        'client_id' => $apiKey,
        'client_secret' => $secret,
        'code' => $code
    ])->json();

    $token = $response['access_token'];

    // 2. Fetch shop info using the access token
    $shopInfo = Http::withHeaders([
        'X-Shopify-Access-Token' => $token,
    ])->get("https://{$shop}/admin/api/2025-01/shop.json")
        ->json()['shop'];

    // 3. Save shop + token + shop info in DB
    ShopifyShop::updateOrCreate(
        ['shop_domain' => $shop], // unique
        [
            'access_token' => $token,
            'scope'        => $response['scope'] ?? null,
            'name'         => $shopInfo['name'] ?? null,
            'email'        => $shopInfo['email'] ?? null,
            'currency'     => $shopInfo['currency'] ?? null,
            'timezone'     => $shopInfo['iana_timezone'] ?? null,
            'is_active'    => true,
        ]
    );

    return "✅ Shopify app installed successfully for {$shop}";
});
