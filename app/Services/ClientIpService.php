<?php

namespace App\Services;

use Illuminate\Http\Request;

class ClientIpService
{
    /**
     * Returns a stable client IP string for tracking / blocking.
     * Prefers IPv4 when possible (including IPv6-mapped IPv4).
     */
    public function getClientIp(Request $request): string
    {
        return $this->getClientIpv4($request) ?? (string) $request->ip();
    }

    /**
     * Try to extract an IPv4 from common proxy headers.
     * Returns null if no IPv4 is found.
     */
    public function getClientIpv4(Request $request): ?string
    {
        $candidates = [];

        // Cloudflare
        $cf = $request->header('CF-Connecting-IP');
        if (is_string($cf) && trim($cf) !== '') {
            $candidates[] = $cf;
        }

        // Common reverse proxy headers
        $xff = $request->header('X-Forwarded-For');
        if (is_string($xff) && trim($xff) !== '') {
            $parts = array_map('trim', explode(',', $xff));
            foreach ($parts as $p) {
                if ($p !== '') {
                    $candidates[] = $p;
                }
            }
        }

        $xri = $request->header('X-Real-IP');
        if (is_string($xri) && trim($xri) !== '') {
            $candidates[] = $xri;
        }

        // Fallback: Laravel's detected ip (may be IPv6)
        $candidates[] = (string) $request->ip();

        foreach ($candidates as $raw) {
            $raw = trim((string) $raw);
            if ($raw === '') {
                continue;
            }

            // Handle IPv6-mapped IPv4: ::ffff:192.0.2.1
            if (stripos($raw, '::ffff:') === 0) {
                $raw = substr($raw, 7);
            }

            if (filter_var($raw, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $raw;
            }
        }

        return null;
    }
}

