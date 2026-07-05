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
