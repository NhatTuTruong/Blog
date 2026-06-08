<?php

namespace App\Support;

use App\Models\InstagramAccount;

class InstagramSettings
{
    public static function isEnabled(): bool
    {
        return (bool) AdminSettings::get('instagram_enabled', false);
    }

    /**
     * @deprecated Use InstagramAccount model per account.
     */
    public static function accessToken(): ?string
    {
        $account = static::primaryAccount();

        return $account?->normalizedAccessToken();
    }

    /**
     * @deprecated Use InstagramAccount model per account.
     */
    public static function usesInstagramLoginApi(): bool
    {
        $account = static::primaryAccount();

        return $account?->usesInstagramLoginApi() ?? false;
    }

    public static function apiHostForAccount(?InstagramAccount $account = null): string
    {
        $account ??= static::primaryAccount();

        return ($account?->usesInstagramLoginApi() ?? false)
            ? 'graph.instagram.com'
            : 'graph.facebook.com';
    }

    public static function apiHost(): string
    {
        return static::apiHostForAccount();
    }

    /**
     * @deprecated Use InstagramAccount model per account.
     */
    public static function userId(): ?string
    {
        $id = trim((string) (static::primaryAccount()?->user_id ?? ''));

        return $id !== '' ? $id : null;
    }

    public static function graphVersion(): string
    {
        $version = trim((string) AdminSettings::get('instagram_graph_version', 'v21.0'));

        return $version !== '' ? $version : 'v21.0';
    }

    public static function queueIntervalMinutes(): int
    {
        return max(1, min(1440, (int) AdminSettings::get('instagram_queue_interval_minutes', 30)));
    }

    public static function publicBaseUrl(): ?string
    {
        $override = trim((string) AdminSettings::get('instagram_public_base_url', ''));
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

    public static function isConfigured(): bool
    {
        if (! static::isEnabled()) {
            return false;
        }

        return InstagramAccount::query()
            ->where('enabled', true)
            ->get()
            ->contains(fn (InstagramAccount $account): bool => $account->isConfigured());
    }

    public static function primaryAccount(): ?InstagramAccount
    {
        return InstagramAccount::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->first(fn (InstagramAccount $account): bool => $account->isConfigured());
    }
}
