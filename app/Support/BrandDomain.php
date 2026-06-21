<?php

namespace App\Support;

class BrandDomain
{
    /**
     * mayvenn.com → mayvenn, shop.mayvenn.com → mayvenn
     */
    public static function brandName(?string $input): ?string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }

        $host = strtolower(explode('/', preg_replace('#^https?://#i', '', $input) ?? $input)[0] ?? $input);
        $host = preg_replace('#^www\.#', '', $host) ?? $host;
        $host = trim($host, '.');

        if ($host === '') {
            return null;
        }

        $parts = array_values(array_filter(explode('.', $host), fn (string $part): bool => $part !== ''));

        if ($parts === []) {
            return null;
        }

        if (count($parts) === 1) {
            return self::sanitize($parts[0]);
        }

        $suffix2 = $parts[count($parts) - 2].'.'.$parts[count($parts) - 1];

        if (self::isTwoLevelPublicSuffix($suffix2) && count($parts) >= 3) {
            return self::sanitize($parts[count($parts) - 3]);
        }

        return self::sanitize($parts[count($parts) - 2]);
    }

    protected static function sanitize(string $name): ?string
    {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name) ?? '';

        return $name !== '' ? strtolower($name) : null;
    }

    protected static function isTwoLevelPublicSuffix(string $suffix): bool
    {
        return in_array($suffix, [
            'co.uk', 'org.uk', 'ac.uk', 'com.au', 'net.au', 'co.nz',
            'com.br', 'co.jp', 'com.mx', 'co.in', 'com.sg', 'com.hk',
        ], true);
    }
}
