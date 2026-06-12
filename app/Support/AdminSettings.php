<?php

namespace App\Support;

use App\Models\SiteContent;
use Illuminate\Support\Facades\Crypt;

class AdminSettings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return SiteContent::get('settings.' . $key, $default);
    }

    public static function set(string $key, mixed $value): void
    {
        SiteContent::set('settings.' . $key, $value);
    }

    public static function getEncrypted(string $key, ?string $default = null): ?string
    {
        $raw = SiteContent::get('settings.secure.' . $key);
        if (! is_string($raw) || trim($raw) === '') {
            return $default;
        }

        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function setEncrypted(string $key, ?string $value): void
    {
        $value = trim((string) $value);
        if ($value === '') {
            SiteContent::set('settings.secure.' . $key, '');

            return;
        }

        SiteContent::set('settings.secure.' . $key, Crypt::encryptString($value));
    }

    /** @return array<int, string> */
    public static function getGeminiApiKeys(string $scope = GeminiKeyScope::AUTO_BLOG): array
    {
        return GeminiSettings::getApiKeys($scope, IntegrationSettingsStore::fallbackAdminUserId());
    }

    public static function hasGeminiApiKey(string $scope = GeminiKeyScope::AUTO_BLOG): bool
    {
        return GeminiSettings::hasApiKey($scope, IntegrationSettingsStore::fallbackAdminUserId());
    }
}
