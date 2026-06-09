<?php

namespace App\Support;

use App\Models\FacebookAccount;

class FacebookSettings
{
    public static function isEnabled(): bool
    {
        return (bool) AdminSettings::get('facebook_enabled', false);
    }

    public static function graphVersion(): string
    {
        $version = trim((string) AdminSettings::get('facebook_graph_version', 'v21.0'));

        return $version !== '' ? $version : 'v21.0';
    }

    public static function queueIntervalMinutes(): int
    {
        return max(1, min(1440, (int) AdminSettings::get('facebook_queue_interval_minutes', 30)));
    }

    public static function publicBaseUrl(): ?string
    {
        $override = trim((string) AdminSettings::get('facebook_public_base_url', ''));
        if ($override !== '') {
            return rtrim($override, '/');
        }

        $instagramOverride = trim((string) AdminSettings::get('instagram_public_base_url', ''));
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

    public static function isConfigured(): bool
    {
        if (! static::isEnabled()) {
            return false;
        }

        return FacebookAccount::query()
            ->where('enabled', true)
            ->get()
            ->contains(fn (FacebookAccount $account): bool => $account->isConfigured());
    }

    public static function primaryAccount(): ?FacebookAccount
    {
        return FacebookAccount::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->first(fn (FacebookAccount $account): bool => $account->isConfigured());
    }
}
