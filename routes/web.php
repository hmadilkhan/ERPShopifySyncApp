<?php

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

    // Exchange code for access token
    $response = Http::post("https://{$shop}/admin/oauth/access_token", [
        'client_id' => $apiKey,
        'client_secret' => $secret,
        'code' => $code
    ]);

    $token = $response['access_token'];

    // Save $shop + $token in DB for future API calls
    \DB::table('shopify_shops')->updateOrInsert(
        ['shop' => $shop],
        ['access_token' => $token]
    );

    return "Shopify app installed successfully!";
});