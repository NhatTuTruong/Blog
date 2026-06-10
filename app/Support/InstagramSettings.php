<?php

namespace App\Support;

use App\Models\InstagramAccount;

class InstagramSettings
{
    protected static function store(?int $userId = null): IntegrationSettingsStore
    {
        return IntegrationSettingsStore::for($userId);
    }

    protected static function ownerUserId(?int $userId = null): int
    {
        return static::store($userId)->userId();
    }

    public static function isEnabled(?int $userId = null): bool
    {
        return (bool) static::store($userId)->get('instagram_enabled', false);
    }

    public static function apiHostForAccount(?InstagramAccount $account = null, ?int $userId = null): string
    {
        $account ??= static::primaryAccount($userId);

        return ($account?->usesInstagramLoginApi() ?? false)
            ? 'graph.instagram.com'
            : 'graph.facebook.com';
    }

    public static function apiHost(?int $userId = null): string
    {
        return static::apiHostForAccount(null, $userId);
    }

    public static function graphVersion(?int $userId = null): string
    {
        $version = trim((string) static::store($userId)->get('instagram_graph_version', 'v21.0'));

        return $version !== '' ? $version : 'v21.0';
    }

    public static function queueIntervalMinutes(?int $userId = null): int
    {
        return max(1, min(1440, (int) static::store($userId)->get('instagram_queue_interval_minutes', 30)));
    }

    public static function publicBaseUrl(?int $userId = null): ?string
    {
        $override = trim((string) static::store($userId)->get('instagram_public_base_url', ''));
        if ($override !== '') {
            return rtrim($override, '/');
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        $host = parse_url($appUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true)) {
            return null;
        }

        return $appUrl;
    }

    public static function isConfigured(?int $userId = null): bool
    {
        if (! static::isEnabled($userId)) {
            return false;
        }

        return InstagramAccount::query()
            ->where('owner_user_id', static::ownerUserId($userId))
            ->where('enabled', true)
            ->get()
            ->contains(fn (InstagramAccount $account): bool => $account->isConfigured());
    }

    public static function primaryAccount(?int $userId = null): ?InstagramAccount
    {
        return InstagramAccount::query()
            ->where('owner_user_id', static::ownerUserId($userId))
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->first(fn (InstagramAccount $account): bool => $account->isConfigured());
    }
}
