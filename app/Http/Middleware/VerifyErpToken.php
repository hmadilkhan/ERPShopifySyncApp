<?php

namespace App\Http\Middleware;

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
    public function handle(Request $request, Closure $next)
    {
        $authorization = $request->header('Authorization');

        if (!$authorization || !preg_match('/Bearer\s+(\S+)/', $authorization, $matches)) {
            return response()->json(['error' => 'Missing or invalid token'], 401);
        }

        $token = $matches[1];

        if ($token !== config('services.erp.secret')) {
            return response()->json(['error' => 'Unauthorized ERP request'], 401);
        }

        return $next($request);
    }
}
