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
                'message' => 'API đồng bộ coupon đang bị tắt.',
            ], 503);
        }

        $expected = trim((string) config('coupon_sync.api_token', ''));

        if ($expected === '') {
            return response()->json([
                'success' => false,
                'message' => 'Chưa cấu hình token API đồng bộ coupon trên server blog.',
            ], 503);
        }

        $provided = $request->bearerToken()
            ?? $request->header('X-Coupon-Sync-Token')
            ?? $request->input('api_token');

        if (! is_string($provided) || ! hash_equals($expected, trim($provided))) {
            return response()->json([
                'success' => false,
                'message' => 'Token không hợp lệ hoặc thiếu quyền truy cập.',
            ], 401);
        }

        return $next($request);
    }
}
