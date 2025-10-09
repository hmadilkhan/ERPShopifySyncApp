<?php

return [
    'api_key'       => env('SHOPIFY_API_KEY'),
    'api_secret'    => env('SHOPIFY_API_SECRET'),
    'scopes'        => env('SHOPIFY_SCOPES', 'read_orders,write_orders,read_fulfillments,write_fulfillments,read_assigned_fulfillment_orders,write_assigned_fulfillment_orders'),
    'redirect_uri'  => env('SHOPIFY_REDIRECT_URI'),
    'api_version'   => env('SHOPIFY_API_VERSION', '2025-01'),
];
