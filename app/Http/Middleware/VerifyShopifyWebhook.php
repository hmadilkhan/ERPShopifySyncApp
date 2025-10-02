<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyShopifyWebhook
{
    public function handle(Request $request, Closure $next)
    {
        $hmac = $request->header('X-Shopify-Hmac-Sha256');
        $calculated = base64_encode(hash_hmac('sha256', $request->getContent(), env('SHOPIFY_API_SECRET'), true));

        if (!hash_equals($hmac, $calculated)) {
            return response()->json(['error' => 'Invalid HMAC'], 401);
        }

        return $next($request);
    }
}
