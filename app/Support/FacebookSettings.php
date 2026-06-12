<?php

namespace App\Support;

use App\Models\FacebookAccount;

class FacebookSettings
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
        return (bool) static::store($userId)->get('facebook_enabled', false);
    }

    public static function graphVersion(?int $userId = null): string
    {
        $version = trim((string) static::store($userId)->get('facebook_graph_version', 'v21.0'));

        return $version !== '' ? $version : 'v21.0';
    }

    public static function queueIntervalMinutes(?int $userId = null): int
    {
        return max(1, min(1440, (int) static::store($userId)->get('facebook_queue_interval_minutes', 30)));
    }

    public static function isAutoQueueEnabled(?int $userId = null): bool
    {
        return (bool) static::store($userId)->get('facebook_auto_queue_enabled', false);
    }

    public static function autoQueueIntervalMinutes(?int $userId = null): int
    {
        return max(1, min(1440, (int) static::store($userId)->get('facebook_auto_queue_interval_minutes', 60)));
    }

    /**
     * @return array<string, mixed>
     */
    public static function autoQueueState(?int $userId = null): array
    {
        $state = static::store($userId)->get('facebook_auto_queue_state', []);

        return is_array($state) ? $state : [];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function setAutoQueueState(?int $userId, array $state): void
    {
        static::store($userId)->set('facebook_auto_queue_state', $state);
    }

    public static function publicBaseUrl(?int $userId = null): ?string
    {
        $override = trim((string) static::store($userId)->get('facebook_public_base_url', ''));
        if ($override !== '') {
            return rtrim($override, '/');
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

    public static function isConfigured(?int $userId = null): bool
    {
        if (! static::isEnabled($userId)) {
            return false;
        }

        return FacebookAccount::query()
            ->where('owner_user_id', static::ownerUserId($userId))
            ->where('enabled', true)
            ->get()
            ->contains(fn (FacebookAccount $account): bool => $account->isConfigured());
    }

    public static function primaryAccount(?int $userId = null): ?FacebookAccount
    {
        return FacebookAccount::query()
            ->where('owner_user_id', static::ownerUserId($userId))
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->first(fn (FacebookAccount $account): bool => $account->isConfigured());
    }
}
