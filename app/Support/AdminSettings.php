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
    public static function getGeminiApiKeys(): array
    {
        $keys = [];
        $settingKeys = ['gemini_api_key', 'gemini_api_key_2', 'gemini_api_key_3'];
        $configKey = (string) config('gemini.api_key');

        foreach ($settingKeys as $index => $settingKey) {
            $default = ($index === 0 && $configKey !== '') ? $configKey : null;
            $value = static::getEncrypted($settingKey, $default);

            if (is_string($value) && trim($value) !== '') {
                $keys[] = trim($value);
            }
        }

        return array_values(array_unique($keys));
    }

    public static function hasGeminiApiKey(): bool
    {
        return static::getGeminiApiKeys() !== [];
    }
}
