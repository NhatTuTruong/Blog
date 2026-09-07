<?php

namespace App\Support;

class GeminiSettings
{
    public static function hasApiKey(string $scope, ?int $userId = null): bool
    {
        return static::getApiKeyEntries($scope, $userId) !== [];
    }

    public static function getApiKey(string $scope, ?int $userId = null): ?string
    {
        $store = IntegrationSettingsStore::for($userId);
        $scopedKey = trim((string) ($store->getEncrypted(GeminiKeyScope::settingKey($scope)) ?? ''));

        if ($scopedKey !== '') {
            return $scopedKey;
        }

        if ($scope !== GeminiKeyScope::AUTO_BLOG) {
            return null;
        }

        $legacyKey = trim((string) ($store->getEncrypted('gemini_api_key') ?? ''));

        return $legacyKey !== '' ? $legacyKey : null;
    }

    /**
     * Key theo scope, kèm fallback sang các scope khác (bỏ trùng).
     *
     * @return array<int, array{key: string, scope: string}>
     */
    public static function getApiKeyEntries(string $scope, ?int $userId = null): array
    {
        $entries = [];
        $seen = [];

        foreach (GeminiKeyScope::orderedScopes($scope) as $tryScope) {
            $key = static::getApiKey($tryScope, $userId);
            if ($key === null || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $entries[] = [
                'key' => $key,
                'scope' => $tryScope,
            ];
        }

        return $entries;
    }

    /**
     * @return array<int, string>
     */
    public static function getApiKeys(string $scope, ?int $userId = null): array
    {
        return array_map(
            fn (array $entry): string => $entry['key'],
            static::getApiKeyEntries($scope, $userId),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function availableModels(): array
    {
        /** @var array<int, string> $models */
        $models = config('gemini.models', [
            'gemini-2.5-flash',
            'gemini-2.5-flash-lite',
            'gemini-3.1-flash-lite',
            'gemini-3.5-flash',
        ]);

        return collect($models)
            ->map(fn (mixed $model): string => trim((string) $model))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function modelSelectOptions(): array
    {
        $options = [];

        foreach (static::availableModels() as $model) {
            $options[$model] = $model;
        }

        return $options;
    }

    public static function defaultModel(): string
    {
        $configured = trim((string) config('gemini.model', 'gemini-2.5-flash-lite'));

        if ($configured !== '' && static::isKnownModel($configured)) {
            return $configured;
        }

        return static::availableModels()[0] ?? 'gemini-2.5-flash-lite';
    }

    public static function primaryModel(?int $userId = null): string
    {
        $store = IntegrationSettingsStore::for($userId);
        $selected = trim((string) $store->get('gemini_model', static::defaultModel()));

        return static::normalizeStoredModel($selected !== '' ? $selected : null);
    }

    /**
     * Model ưu tiên trước, sau đó lần lượt các model còn lại trong danh sách cấu hình.
     *
     * @return array<int, string>
     */
    public static function modelsToTry(?int $userId = null): array
    {
        $primary = static::primaryModel($userId);
        $models = [$primary];

        foreach (static::availableModels() as $model) {
            if (! in_array($model, $models, true)) {
                $models[] = $model;
            }
        }

        return $models;
    }

    public static function isKnownModel(string $model): bool
    {
        return in_array(trim($model), static::availableModels(), true);
    }

    public static function normalizeStoredModel(?string $model): string
    {
        $model = trim((string) $model);

        if ($model !== '' && static::isKnownModel($model)) {
            return $model;
        }

        return static::defaultModel();
    }
}
