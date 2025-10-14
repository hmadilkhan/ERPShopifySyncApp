<?php

namespace App\Http\Middleware;

use App\Models\ShopifyShop;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyErpToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = $request->header('Authorization');

        if (!$authorization || !preg_match('/Bearer\s+(\S+)/', $authorization, $matches)) {
            return response()->json(['error' => 'Missing or invalid token'], 401);
        }

        $token = $matches[1];
        \Log::info($token);
        // 🔎 Check token in database
        $shop = ShopifyShop::where('erp_secret', $token)->first();

        if (!$shop) {
            return response()->json(['error' => 'Unauthorized ERP request'], 401);
        }

        // ✅ You can also share the shop with controllers if needed
        $request->attributes->set('shop', $shop);

        return $next($request);
    }
}
