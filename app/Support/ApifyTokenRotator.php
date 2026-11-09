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
        $lastIndex = count($tokens) - 1;

        foreach ($tokens as $index => $token) {
            $result = $callback($token);
            $hasMoreTokens = $index < $lastIndex;

            if (self::isWrappedResult($result)) {
                $value = $result['value'] ?? null;
                $failed = $result['token_failed'] === true || ($hasMoreTokens && self::isEmptyResult($value));

                if ($failed) {
                    $message = trim((string) ($result['error'] ?? 'Token Apify không trả dữ liệu.'));
                    $failures[] = $message;
                    Log::warning("{$context} token failed, trying next", [
                        'token_index' => $index + 1,
                        'token_count' => count($tokens),
                        'error' => $message,
                    ]);

                    continue;
                }

                return $value;
            }

            // Callback cũ trả [] khi token lỗi — phải thử token tiếp theo, không dừng ở token đầu.
            if ($hasMoreTokens && self::isEmptyResult($result)) {
                $failures[] = 'Token Apify không trả dữ liệu.';
                Log::warning("{$context} token returned empty, trying next", [
                    'token_index' => $index + 1,
                    'token_count' => count($tokens),
                ]);

                continue;
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

    /**
     * @param  mixed  $result
     * @phpstan-assert-if-true array{value: mixed, token_failed: bool, error?: string} $result
     */
    protected static function isWrappedResult(mixed $result): bool
    {
        return is_array($result)
            && array_key_exists('token_failed', $result)
            && array_key_exists('value', $result)
            && is_bool($result['token_failed']);
    }

    protected static function isEmptyResult(mixed $result): bool
    {
        return $result === null
            || $result === false
            || $result === []
            || $result === '';
    }
}
