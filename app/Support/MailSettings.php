<?php

namespace App\Support;

class MailSettings
{
    protected static function store(?int $userId = null): IntegrationSettingsStore
    {
        return IntegrationSettingsStore::for($userId);
    }

    public static function applyToConfig(): void
    {
        static::applyForUser(IntegrationSettingsStore::fallbackAdminUserId());
    }

    public static function applyForUser(?int $userId): void
    {
        $username = static::username($userId);

        if ($username !== '') {
            config([
                'mail.mailers.smtp.username' => $username,
                'mail.from.address' => $username,
                'imap.username' => $username,
            ]);
        }

        $password = static::password($userId);

        if ($password !== '') {
            config([
                'mail.mailers.smtp.password' => $password,
                'imap.password' => $password,
            ]);
        }

        config([
            'mail.mailers.smtp.host' => static::host($userId),
            'mail.mailers.smtp.port' => static::port($userId),
            'mail.mailers.smtp.encryption' => static::encryption($userId),
            'mail.from.name' => static::fromName($userId),
            'imap.host' => static::imapHost($userId),
            'imap.port' => static::imapPort($userId),
            'imap.encryption' => static::imapEncryption($userId),
            'imap.sync_limit' => static::syncLimit($userId),
            'imap.auto_sync_seconds' => static::autoSyncSeconds($userId),
            'imap.ui_poll_seconds' => static::uiPollSeconds($userId),
            'imap.notifications_poll_seconds' => static::notificationsPollSeconds($userId),
        ]);
    }

    public static function username(?int $userId = null): string
    {
        return trim((string) static::store($userId)->get('mail_username', env('MAIL_USERNAME', '')));
    }

    public static function password(?int $userId = null): string
    {
        $fromSettings = static::store($userId)->getEncrypted('mail_password');

        if (is_string($fromSettings) && $fromSettings !== '') {
            return $fromSettings;
        }

        return (string) env('MAIL_PASSWORD', '');
    }

    public static function fromAddress(?int $userId = null): string
    {
        return static::username($userId) ?: (string) config('mail.from.address', 'hello@example.com');
    }

    public static function fromName(?int $userId = null): string
    {
        return trim((string) static::store($userId)->get('mail_from_name', env('MAIL_FROM_NAME', config('app.name'))));
    }

    public static function host(?int $userId = null): string
    {
        return trim((string) static::store($userId)->get('mail_host', env('MAIL_HOST', 'smtp.gmail.com')));
    }

    public static function port(?int $userId = null): int
    {
        return (int) static::store($userId)->get('mail_port', env('MAIL_PORT', 587));
    }

    public static function encryption(?int $userId = null): string
    {
        return trim((string) static::store($userId)->get('mail_encryption', env('MAIL_ENCRYPTION', 'tls')));
    }

    public static function imapHost(?int $userId = null): string
    {
        return trim((string) static::store($userId)->get('imap_host', env('IMAP_HOST', 'imap.gmail.com')));
    }

    public static function imapPort(?int $userId = null): int
    {
        return (int) static::store($userId)->get('imap_port', env('IMAP_PORT', 993));
    }

    public static function imapEncryption(?int $userId = null): string
    {
        return trim((string) static::store($userId)->get('imap_encryption', env('IMAP_ENCRYPTION', 'ssl')));
    }

    public static function syncLimit(?int $userId = null): int
    {
        return max(1, (int) static::store($userId)->get('imap_sync_limit', env('IMAP_SYNC_LIMIT', 50)));
    }

    public static function autoSyncSeconds(?int $userId = null): int
    {
        return max(0, (int) static::store($userId)->get('imap_auto_sync_seconds', env('IMAP_AUTO_SYNC_SECONDS', 120)));
    }

    public static function uiPollSeconds(?int $userId = null): int
    {
        return max(0, (int) static::store($userId)->get('imap_ui_poll_seconds', env('IMAP_UI_POLL_SECONDS', 15)));
    }

    public static function notificationsPollSeconds(?int $userId = null): int
    {
        return max(0, (int) static::store($userId)->get('imap_notifications_poll_seconds', env('IMAP_NOTIFICATIONS_POLL_SECONDS', 10)));
    }

    public static function isConfigured(?int $userId = null): bool
    {
        return static::username($userId) !== '' && static::password($userId) !== '';
    }
}
