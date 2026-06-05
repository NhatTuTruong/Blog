<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeoIpService
{
    private const CACHE_TTL = 604800; // 7 days

    public function getCountry(string $ip): ?string
    {
        if ($this->isLocalOrPrivate($ip)) {
            return 'Local';
        }

        return Cache::remember("geo_country:{$ip}", self::CACHE_TTL, function () use ($ip) {
            return $this->fetchCountry($ip);
        });
    }

    private function isLocalOrPrivate(string $ip): bool
    {
        return in_array($ip, ['127.0.0.1', '::1'], true)
            || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    private function fetchCountry(string $ip): ?string
    {
        try {
            $response = Http::timeout(3)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'country',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['country'] ?? null;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }
}
