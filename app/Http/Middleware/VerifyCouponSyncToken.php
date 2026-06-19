<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCouponSyncToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('coupon_sync.enabled', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon sync API is disabled.',
            ], 503);
        }

        $expected = trim((string) config('coupon_sync.api_token', ''));

        if ($expected === '') {
            return response()->json([
                'success' => false,
                'message' => 'Coupon sync API token is not configured on the blog server.',
            ], 503);
        }

        $provided = $request->bearerToken()
            ?? $request->header('X-Coupon-Sync-Token')
            ?? $request->input('api_token');

        if (! is_string($provided) || ! hash_equals($expected, trim($provided))) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}
