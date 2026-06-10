<?php

namespace App\Support;

use App\Models\User;
use Filament\Facades\Filament;

class IntegrationSettingsStore
{
    public function __construct(private readonly int $userId) {}

    public function userId(): int
    {
        return $this->userId;
    }

    public static function for(?int $userId = null): self
    {
        if ($userId === null) {
            $userId = static::resolveUserId();
        }

        if ($userId === null) {
            $userId = static::fallbackAdminUserId();
        }

        if ($userId === null) {
            throw new \RuntimeException('Không xác định được người dùng cho cài đặt tích hợp.');
        }

        return new self($userId);
    }

    public static function resolveUserId(): ?int
    {
        $user = Filament::auth()->user() ?? auth()->user();

        return $user instanceof User ? $user->id : null;
    }

    public static function fallbackAdminUserId(): ?int
    {
        return User::query()->where('is_admin', true)->orderBy('id')->value('id')
            ?? User::query()->orderBy('id')->value('id');
    }

    public function has(string $key): bool
    {
        return UserSettings::has($this->userId, $key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (UserSettings::has($this->userId, $key)) {
            return UserSettings::get($this->userId, $key, $default);
        }

        return AdminSettings::get($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        UserSettings::set($this->userId, $key, $value);
    }

    public function getEncrypted(string $key, ?string $default = null): ?string
    {
        if (UserSettings::has($this->userId, $key)) {
            return UserSettings::getEncrypted($this->userId, $key, $default);
        }

        return AdminSettings::getEncrypted($key, $default);
    }

    public function setEncrypted(string $key, ?string $value): void
    {
        UserSettings::setEncrypted($this->userId, $key, $value);
    }

    /** @return array<int, string> */
    public function getGeminiApiKeys(): array
    {
        $keys = UserSettings::getGeminiApiKeys($this->userId);

        return $keys !== [] ? $keys : AdminSettings::getGeminiApiKeys();
    }

    public function hasGeminiApiKey(): bool
    {
        return $this->getGeminiApiKeys() !== [];
    }
}
