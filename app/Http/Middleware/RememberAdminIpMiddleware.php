<?php

namespace App\Http\Middleware;

use App\Services\ClientIpService;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RememberAdminIpMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $user = Filament::auth()->user();
        if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
            $ip = app(ClientIpService::class)->getClientIp($request);
            if (trim($ip) !== '') {
                Cache::put($this->cacheKey($ip), true, now()->addHours(12));
            }
        }

        return $response;
    }

    private function cacheKey(string $ip): string
    {
        return 'admin_active_ip:' . $ip;
    }
}
