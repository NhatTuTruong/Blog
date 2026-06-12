<?php

namespace App\Support;

class GeminiSettings
{
    public static function hasApiKey(string $scope, ?int $userId = null): bool
    {
        return static::getApiKey($scope, $userId) !== null;
    }

    public static function getApiKey(string $scope, ?int $userId = null): ?string
    {
        $store = IntegrationSettingsStore::for($userId);
        $scopedKey = trim((string) ($store->getEncrypted(GeminiKeyScope::settingKey($scope)) ?? ''));

        if ($scopedKey !== '') {
            return $scopedKey;
        }

        $legacyKey = trim((string) ($store->getEncrypted('gemini_api_key') ?? ''));

        return $legacyKey !== '' ? $legacyKey : null;
    }

    /**
     * @return array<int, string>
     */
    public static function getApiKeys(string $scope, ?int $userId = null): array
    {
        $key = static::getApiKey($scope, $userId);

        return $key !== null ? [$key] : [];
    }
}
