<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookAccount extends Model
{
    protected $fillable = [
        'owner_user_id',
        'name',
        'access_token',
        'page_id',
        'page_name',
        'enabled',
        'sort_order',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function queueItems(): HasMany
    {
        return $this->hasMany(FacebookQueueItem::class);
    }

    public function normalizedAccessToken(): ?string
    {
        $token = $this->access_token;
        if (! is_string($token)) {
            return null;
        }

        $token = trim($token);
        if ($token === '') {
            return null;
        }

        if (str_starts_with(strtolower($token), 'bearer ')) {
            $token = trim(substr($token, 7));
        }

        $token = preg_replace('/[\x00-\x1F\x7F\x{200B}-\x{200D}\x{FEFF}]/u', '', $token) ?? $token;

        return $token !== '' ? $token : null;
    }

    public function isConfigured(): bool
    {
        return $this->normalizedAccessToken() !== null && filled($this->page_id);
    }

    public function displayLabel(): string
    {
        if (filled($this->page_name)) {
            $label = (string) $this->page_name;

            if (filled($this->name)) {
                $label .= ' ('.$this->name.')';
            }

            return $label;
        }

        if (filled($this->name)) {
            return (string) $this->name;
        }

        return 'Page #'.$this->page_id;
    }

    /**
     * @return array<int, string>
     */
    public static function optionsForSelect(?int $ownerUserId = null): array
    {
        $ownerUserId = static::resolveOwnerUserId($ownerUserId);

        return static::query()
            ->where('owner_user_id', $ownerUserId)
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (self $account): bool => $account->isConfigured())
            ->mapWithKeys(fn (self $account): array => [$account->id => $account->displayLabel()])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public static function enabledConfiguredIds(?int $ownerUserId = null): array
    {
        $ownerUserId = static::resolveOwnerUserId($ownerUserId);

        return static::query()
            ->where('owner_user_id', $ownerUserId)
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (self $account): bool => $account->isConfigured())
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public static function firstEnabledConfiguredId(?int $ownerUserId = null): ?int
    {
        $ids = static::enabledConfiguredIds($ownerUserId);

        return $ids[0] ?? null;
    }

    protected static function resolveOwnerUserId(?int $ownerUserId): int
    {
        if ($ownerUserId !== null) {
            return $ownerUserId;
        }

        return \App\Support\IntegrationSettingsStore::for()->userId();
    }
}
