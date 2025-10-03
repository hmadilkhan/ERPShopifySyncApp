<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>ERP Setup</title>

    <!-- Polaris CSS -->
    <link rel="stylesheet" href="https://unpkg.com/@shopify/polaris@11/build/esm/styles.css" />

    <!-- App Bridge -->
    <script src="https://unpkg.com/@shopify/app-bridge@3"></script>
    <!-- Tailwind via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body>
    <div class="Polaris-Page bg-gray-100 text-gray-900">
        <div class="min-h-screen">
            {{-- Navbar --}}
            <header class="bg-white shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <h1 class="text-3xl font-bold text-gray-900">
                        {{ config('app.name') }}
                    </h1>
                </div>
            </header>

            {{-- Main Content --}}
            <main class="p-6">
                @yield('content')
            </main>
        </div>
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
