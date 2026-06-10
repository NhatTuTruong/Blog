<?php

namespace App\Support;

use App\Models\UserSetting;

class UserSettings
{
    public static function has(int $userId, string $key): bool
    {
        return UserSetting::has($userId, $key);
    }

    public static function get(int $userId, string $key, mixed $default = null): mixed
    {
        return UserSetting::get($userId, $key, $default);
    }

    public static function set(int $userId, string $key, mixed $value): void
    {
        UserSetting::set($userId, $key, $value);
    }

    public static function getEncrypted(int $userId, string $key, ?string $default = null): ?string
    {
        return UserSetting::getEncrypted($userId, $key, $default);
    }

    public static function setEncrypted(int $userId, string $key, ?string $value): void
    {
        UserSetting::setEncrypted($userId, $key, $value);
    }

    /** @return array<int, string> */
    public static function getGeminiApiKeys(int $userId): array
    {
        $keys = [];
        $settingKeys = ['gemini_api_key', 'gemini_api_key_2', 'gemini_api_key_3'];
        $configKey = (string) config('gemini.api_key');

        foreach ($settingKeys as $index => $settingKey) {
            $default = ($index === 0 && $configKey !== '') ? $configKey : null;
            $value = static::getEncrypted($userId, $settingKey, $default);

            if (is_string($value) && trim($value) !== '') {
                $keys[] = trim($value);
            }
        }

        return array_values(array_unique($keys));
    }

    public static function hasGeminiApiKey(int $userId): bool
    {
        return static::getGeminiApiKeys($userId) !== [];
    }
}
