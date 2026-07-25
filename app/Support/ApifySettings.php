<?php

namespace App\Support;

class ApifySettings
{
    public static function apiToken(?int $userId = null): ?string
    {
        $tokens = static::apiTokens($userId);

        return $tokens[0] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function apiTokens(?int $userId = null): array
    {
        $fromStore = IntegrationSettingsStore::for($userId)->getEncrypted('apify_api_token');
        $tokens = static::parseTokenList(is_string($fromStore) ? $fromStore : null);

        if ($tokens !== []) {
            return $tokens;
        }

        return static::parseTokenList((string) config('apify.api_token', ''));
    }

    /**
     * @return array<int, string>
     */
    public static function parseTokenList(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/[\r\n,;]+/', $raw) ?: [];

        return collect($parts)
            ->map(fn (mixed $part): string => trim((string) $part))
            ->filter(fn (string $token): bool => $token !== '' && $token !== '********')
            ->unique()
            ->values()
            ->all();
    }

    public static function isConfigured(?int $userId = null): bool
    {
        return static::apiTokens($userId) !== [];
    }

    public static function shouldRotateToken(?int $httpStatus, string $body): bool
    {
        if ($httpStatus !== null && in_array($httpStatus, [401, 402, 403, 429], true)) {
            return true;
        }

        $lower = strtolower($body);

        foreach ([
            'invalid token',
            'token is invalid',
            'unauthorized',
            'authentication',
            'not authorized',
            'user was not found',
            'billing',
            'quota exceeded',
            'usage hard limit',
            'rate limit',
            'insufficient credits',
            'payment required',
        ] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function normalizeStoredTokens(string $input): ?string
    {
        $tokens = static::parseTokenList($input);

        return $tokens !== [] ? implode("\n", $tokens) : null;
    }

    public static function inputUnchanged(string $input, ?string $stored = null): bool
    {
        $trimmed = trim($input);

        if ($trimmed === '********') {
            return true;
        }

        $inputTokens = static::parseTokenList($trimmed);
        $storedTokens = $stored !== null ? static::parseTokenList($stored) : [];

        return $inputTokens === $storedTokens;
    }
}
