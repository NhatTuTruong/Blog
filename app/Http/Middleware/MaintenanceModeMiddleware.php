<?php

namespace App\Http\Middleware;

use App\Models\SiteContent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceModeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isMaintenanceEnabled()) {
            return $next($request);
        }

        // Cho phép truy cập Admin và health check khi bảo trì
        if ($request->is('health')) {
            return $next($request);
        }
        // Segment đầu tiên của path: admin, admin/login, admin/blog → segment(1) = 'admin'
        if ($request->segment(1) === 'admin') {
            return $next($request);
        }

        $content = SiteContent::get('error_503', SiteContent::defaultErrorContent('503'));

        return response()->view('errors.503', [], 503)
            ->header('Retry-After', '3600');
    }

    protected function isMaintenanceEnabled(): bool
    {
        $value = SiteContent::get('maintenance_enabled', false);
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
