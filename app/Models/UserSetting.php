<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class UserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'key',
        'value',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function has(int $userId, string $key): bool
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('key', $key)
            ->exists();
    }

    public static function get(int $userId, string $key, mixed $default = null): mixed
    {
        /** @var self|null $row */
        $row = static::query()
            ->where('user_id', $userId)
            ->where('key', $key)
            ->first();

        if ($row === null) {
            return $default;
        }

        if ($row->is_encrypted) {
            if (! is_string($row->value) || trim($row->value) === '') {
                return $default;
            }

            try {
                return Crypt::decryptString($row->value);
            } catch (\Throwable) {
                return $default;
            }
        }

        if ($row->value === null || $row->value === '') {
            return $default;
        }

        $decoded = json_decode($row->value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $row->value;
    }

    public static function set(int $userId, string $key, mixed $value): void
    {
        $stored = is_string($value) ? $value : json_encode($value);

        static::query()->updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $stored, 'is_encrypted' => false],
        );
    }

    public static function setEncrypted(int $userId, string $key, ?string $value): void
    {
        $value = trim((string) $value);

        if ($value === '') {
            static::query()
                ->where('user_id', $userId)
                ->where('key', $key)
                ->delete();

            return;
        }

        static::query()->updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            [
                'value' => Crypt::encryptString($value),
                'is_encrypted' => true,
            ],
        );
    }

    public static function getEncrypted(int $userId, string $key, ?string $default = null): ?string
    {
        $value = static::get($userId, $key);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
