<?php

namespace App\Support;

class ApifySettings
{
    public static function apiToken(?int $userId = null): ?string
    {
        $fromStore = trim((string) IntegrationSettingsStore::for($userId)->getEncrypted('apify_api_token'));

        if ($fromStore !== '') {
            return $fromStore;
        }

        $fromEnv = trim((string) config('apify.api_token', ''));

        return $fromEnv !== '' ? $fromEnv : null;
    }

    public static function isConfigured(?int $userId = null): bool
    {
        return filled(static::apiToken($userId));
    }
}
