<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ERP Setup</title>

    <!-- Polaris CSS -->
    <link rel="stylesheet" href="https://unpkg.com/@shopify/polaris@11/build/esm/styles.css" />

    <!-- App Bridge -->
    <script src="https://unpkg.com/@shopify/app-bridge@3"></script>
</head>
<body>
    <div class="Polaris-Page">
        @yield('content')
    </div>

    <script>
        var AppBridge = window['app-bridge'];
        var createApp = AppBridge.createApp;

        var app = createApp({
            apiKey: "{{ config('services.shopify.api_key') }}",
            shopOrigin: "{{ $shop->shop_domain ?? request('shop') }}",
            forceRedirect: true, // 🔑 This ensures app always runs in Admin iframe
        });
    </script>
</body>
</html>
