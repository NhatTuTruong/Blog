<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class ApifyTokenRotator
{
    /**
     * @template T
     *
     * @param  callable(string): T  $callback
     * @return T|null
     */
    public static function attempt(?int $userId, callable $callback, ?string &$lastError = null, string $context = 'Apify'): mixed
    {
        $tokens = ApifySettings::apiTokens($userId);

        if ($tokens === []) {
            $lastError = 'Chưa cấu hình Apify API token.';

            return null;
        }

        $failures = [];

        foreach ($tokens as $index => $token) {
            $result = $callback($token);

            if (is_array($result) && array_key_exists('token_failed', $result)) {
                if ($result['token_failed'] === true) {
                    $message = trim((string) ($result['error'] ?? 'Token Apify lỗi.'));
                    $failures[] = $message;
                    Log::warning("{$context} token failed, trying next", [
                        'token_index' => $index + 1,
                        'token_count' => count($tokens),
                        'error' => $message,
                    ]);

                    continue;
                }

                return $result['value'] ?? null;
            }

            return $result;
        }

        $lastError = $failures !== []
            ? 'Tất cả Apify token đều lỗi: '.implode(' | ', array_slice($failures, 0, 3))
            : 'Tất cả Apify token đều lỗi.';

        return null;
    }

    /**
     * @return array{value: mixed, token_failed: bool, error?: string}
     */
    public static function result(mixed $value, bool $tokenFailed = false, ?string $error = null): array
    {
        $payload = [
            'value' => $value,
            'token_failed' => $tokenFailed,
        ];

        if ($error !== null) {
            $payload['error'] = $error;
        }

        return $payload;
    }
}
