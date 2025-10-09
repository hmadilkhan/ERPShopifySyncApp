<?php

use App\Http\Controllers\ShopifyErpController;
use App\Models\ShopifyShop;
use App\Services\ShopifyService;
use App\Services\ShopifyWebhookService;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', function () {
    $shop = request()->query('shop');

    if ($shop) {
        $shopModel = \App\Models\ShopifyShop::where('shop_domain', $shop)->first();
        if ($shopModel) {
            return redirect()->route('shopify.erp.show', $shopModel->id);
        }
    }

    return view('welcome'); // fallback if accessed directly
});


Route::get('/shopify/install', function (Request $request) {
    $shop = $request->query('shop'); // e.g. my-store.myshopify.com

    $apiKey = config('shopify.api_key');
    $scopes = "read_orders,write_orders,read_products,write_products,read_inventory,write_inventory,read_fulfillments,write_fulfillments";
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

    $erp_secret = Str::random(40);

    // 3. Save shop + token + shop info in DB
    $shopModel =  ShopifyShop::updateOrCreate(
        ['shop_domain' => $shop], // unique
        [
            'access_token' => $token,
            'scope'        => $response['scope'] ?? null,
            'name'         => $shopInfo['name'] ?? null,
            'email'        => $shopInfo['email'] ?? null,
            'currency'     => $shopInfo['currency'] ?? null,
            'timezone'     => $shopInfo['iana_timezone'] ?? null,
            'is_active'    => true,
            'erp_secret'   => $erp_secret ?? null,
        ]
    );

    // 🔹 Register Webhooks here
    registerShopifyWebhook($shop, $token, 'orders/create', '/shopify/webhook/orders');
    registerShopifyWebhook($shop, $token, 'inventory_levels/update', '/shopify/webhook/inventory');
    registerShopifyWebhook($shop, $token, 'orders/updated', '/shopify/webhook/order-updated');

    ShopifyWebhookService::register($shopModel);

    // ✅ Redirect merchant to ERP Setup Page inside Shopify Admin
    return redirect()->route('shopify.erp.show', $shopModel->id);
    // return "✅ Shopify app installed successfully for {$shop}";
});


Route::get('/shopify/test-webhook', function () {
    $shop = ShopifyShop::first(); // get your saved shop
    return registerShopifyWebhook(
        $shop->shop_domain,
        $shop->access_token,
        'orders/create',
        '/shopify/webhook/orders'
    );
});

Route::get('/shopify/register-webhook', function () {
    $shop = ShopifyShop::first(); // get your saved shop
    return ShopifyWebhookService::register($shop);
});

Route::get('/shopify/get-all-webhook', function () {
    $shop = ShopifyShop::first(); // get your saved shop
    return ShopifyWebhookService::getAll($shop);
});
Route::get('/shopify/delete-webhook', function () {
    $shop = ShopifyShop::first(); // get your saved shop
    return ShopifyWebhookService::deleteAll($shop);
});

Route::get('/shopify/list-webhooks', function () {
    $shop = ShopifyShop::first(); // or find the shop you want

    $response = Http::withHeaders([
        'X-Shopify-Access-Token' => $shop->access_token,
    ])->get("https://{$shop->shop_domain}/admin/api/2025-01/webhooks.json");

    return $response->json();
});

Route::get('/shopify/test-products', function () {
    $shop = ShopifyShop::first();
    $service = new ShopifyService($shop);
    return $service->getProducts();
});

Route::get('/shopify/test-orders', function () {
    $shop = ShopifyShop::first();
    $service = new ShopifyService($shop);
    return $service->getOrders();
});

Route::get('/shopify/create-product', function () {
    $shop = ShopifyShop::first(); // pick your saved shop
    if (!$shop) {
        return "No shop found!";
    }

    $payload = [
        "product" => [
            "title"       => "Blink",
            "body_html"   => "<strong>Good product!</strong>",
            "vendor"      => "ERP System",
            "product_type" => "Shoes",
            "status"      => "active",
            "variants"    => [
                [
                    "option1" => "Default Title",
                    "price"   => "99.99",
                    "sku"     => "ERP-SKU-001",
                    "inventory_quantity" => 10
                ]
            ],
            "images" => [
                [
                    "src" => "https://retail.sabsoft.com.pk/storage/images/products/1669024269cloud%20printer.png.png"
                ]
            ]
        ]
    ];

    $response = Http::withHeaders([
        'X-Shopify-Access-Token' => $shop->access_token,
    ])->post("https://{$shop->shop_domain}/admin/api/2025-01/products.json", $payload);

    return $response->json();
});


Route::prefix('shopify')->middleware(['web'])->group(function () {
    Route::get('/erp/{shopId}', [ShopifyErpController::class, 'show'])->name('shopify.erp.show');
    Route::post('/erp/{shopId}', [ShopifyErpController::class, 'save'])->name('shopify.erp.save');
});
