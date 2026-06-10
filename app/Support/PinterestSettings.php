<?php

namespace App\Support;

use App\Models\PinterestAccount;

class PinterestSettings
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
        return (bool) static::store($userId)->get('pinterest_enabled', false);
    }

    public static function apiBaseUrl(?int $userId = null): string
    {
        $override = trim((string) static::store($userId)->get('pinterest_api_base_url', ''));

        return $override !== '' ? rtrim($override, '/') : 'https://api.pinterest.com/v5';
    }

    public static function queueIntervalMinutes(?int $userId = null): int
    {
        return max(1, min(1440, (int) static::store($userId)->get('pinterest_queue_interval_minutes', 30)));
    }

    public static function publicBaseUrl(?int $userId = null): ?string
    {
        $override = trim((string) static::store($userId)->get('pinterest_public_base_url', ''));
        if ($override !== '') {
            return rtrim($override, '/');
        }

        $facebookOverride = trim((string) static::store($userId)->get('facebook_public_base_url', ''));
        if ($facebookOverride !== '') {
            return rtrim($facebookOverride, '/');
        }

        $instagramOverride = trim((string) static::store($userId)->get('instagram_public_base_url', ''));
        if ($instagramOverride !== '') {
            return rtrim($instagramOverride, '/');
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

    public static function hasReadyAccount(?int $userId = null): bool
    {
        return PinterestAccount::query()
            ->where('owner_user_id', static::ownerUserId($userId))
            ->where('enabled', true)
            ->get()
            ->contains(fn (PinterestAccount $account): bool => $account->isConfigured());
    }

    public static function isConfigured(?int $userId = null): bool
    {
        return static::hasReadyAccount($userId);
    }

    public static function configurationErrorMessage(?int $userId = null): ?string
    {
        if (static::hasReadyAccount($userId)) {
            return null;
        }

        $hasDisabledAccount = PinterestAccount::query()
            ->where('owner_user_id', static::ownerUserId($userId))
            ->get()
            ->contains(fn (PinterestAccount $account): bool => $account->isConfigured() && ! $account->enabled);

        if ($hasDisabledAccount) {
            return 'Tài khoản Pinterest đang tắt — bật lại trong Cài đặt tích hợp.';
        }

        return 'Pinterest chưa có tài khoản — thêm Access Token trong Cài đặt tích hợp.';
    }

    public static function primaryAccount(?int $userId = null): ?PinterestAccount
    {
        return PinterestAccount::query()
            ->where('owner_user_id', static::ownerUserId($userId))
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->first(fn (PinterestAccount $account): bool => $account->isConfigured());
    }
}
