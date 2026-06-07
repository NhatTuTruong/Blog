<?php

namespace App\Support;

class InstagramSettings
{
    public static function isEnabled(): bool
    {
        return (bool) AdminSettings::get('instagram_enabled', false);
    }

    public static function accessToken(): ?string
    {
        $token = AdminSettings::getEncrypted('instagram_access_token');

        if (! is_string($token)) {
            return null;
        }

        $token = trim($token);
        if ($token === '') {
            return null;
        }

        if (str_starts_with(strtolower($token), 'bearer ')) {
            $token = trim(substr($token, 7));
        }

        // Strip invisible / stray whitespace from paste.
        $token = preg_replace('/[\x00-\x1F\x7F\x{200B}-\x{200D}\x{FEFF}]/u', '', $token) ?? $token;

        return $token !== '' ? $token : null;
    }

    public static function usesInstagramLoginApi(): bool
    {
        $token = static::accessToken();

        return is_string($token) && str_starts_with($token, 'IG');
    }

    public static function apiHost(): string
    {
        return static::usesInstagramLoginApi()
            ? 'graph.instagram.com'
            : 'graph.facebook.com';
    }

    public static function userId(): ?string
    {
        $id = trim((string) AdminSettings::get('instagram_user_id', ''));

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
        if (! static::isEnabled() || static::accessToken() === null) {
            return false;
        }

        // Token IGAA… (Instagram Login): user_id lấy tự động từ /me.
        if (static::usesInstagramLoginApi()) {
            return true;
        }

        return static::userId() !== null;
    }
}
