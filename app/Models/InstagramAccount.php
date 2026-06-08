<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstagramAccount extends Model
{
    protected $fillable = [
        'name',
        'access_token',
        'user_id',
        'username',
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
        return $this->hasMany(InstagramQueueItem::class);
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

    public function usesInstagramLoginApi(): bool
    {
        $token = $this->normalizedAccessToken();

        return is_string($token) && str_starts_with($token, 'IG');
    }

    public function isConfigured(): bool
    {
        if ($this->normalizedAccessToken() === null) {
            return false;
        }

        if ($this->usesInstagramLoginApi()) {
            return true;
        }

        return filled($this->user_id);
    }

    public function displayLabel(): string
    {
        if (filled($this->username)) {
            $label = '@'.$this->username;

            if (filled($this->name)) {
                $label .= ' ('.$this->name.')';
            }

            return $label;
        }

        if (filled($this->name)) {
            return (string) $this->name;
        }

        return 'Instagram #'.$this->id;
    }

    /**
     * @return array<int, string>
     */
    public static function optionsForSelect(): array
    {
        return static::query()
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
    public static function enabledConfiguredIds(): array
    {
        return static::query()
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
}
