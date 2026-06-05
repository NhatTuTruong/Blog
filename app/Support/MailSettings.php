<?php

namespace App\Support;

class MailSettings
{
    public static function applyToConfig(): void
    {
        $username = static::username();

        if ($username !== '') {
            config([
                'mail.mailers.smtp.username' => $username,
                'mail.from.address' => $username,
                'imap.username' => $username,
            ]);
        }

        $password = static::password();

        if ($password !== '') {
            config([
                'mail.mailers.smtp.password' => $password,
                'imap.password' => $password,
            ]);
        }

        config([
            'mail.mailers.smtp.host' => static::host(),
            'mail.mailers.smtp.port' => static::port(),
            'mail.mailers.smtp.encryption' => static::encryption(),
            'mail.from.name' => static::fromName(),
            'imap.host' => static::imapHost(),
            'imap.port' => static::imapPort(),
            'imap.encryption' => static::imapEncryption(),
            'imap.sync_limit' => static::syncLimit(),
            'imap.auto_sync_seconds' => static::autoSyncSeconds(),
            'imap.ui_poll_seconds' => static::uiPollSeconds(),
            'imap.notifications_poll_seconds' => static::notificationsPollSeconds(),
        ]);
    }

    public static function username(): string
    {
        $value = trim((string) AdminSettings::get('mail_username', env('MAIL_USERNAME', '')));

        return $value;
    }

    public static function password(): string
    {
        $fromSettings = AdminSettings::getEncrypted('mail_password');

        if (is_string($fromSettings) && $fromSettings !== '') {
            return $fromSettings;
        }

        return (string) env('MAIL_PASSWORD', '');
    }

    public static function fromAddress(): string
    {
        return static::username() ?: (string) config('mail.from.address', 'hello@example.com');
    }

    public static function fromName(): string
    {
        return trim((string) AdminSettings::get('mail_from_name', env('MAIL_FROM_NAME', config('app.name'))));
    }

    public static function host(): string
    {
        return trim((string) AdminSettings::get('mail_host', env('MAIL_HOST', 'smtp.gmail.com')));
    }

    public static function port(): int
    {
        return (int) AdminSettings::get('mail_port', env('MAIL_PORT', 587));
    }

    public static function encryption(): string
    {
        return trim((string) AdminSettings::get('mail_encryption', env('MAIL_ENCRYPTION', 'tls')));
    }

    public static function imapHost(): string
    {
        return trim((string) AdminSettings::get('imap_host', env('IMAP_HOST', 'imap.gmail.com')));
    }

    public static function imapPort(): int
    {
        return (int) AdminSettings::get('imap_port', env('IMAP_PORT', 993));
    }

    public static function imapEncryption(): string
    {
        return trim((string) AdminSettings::get('imap_encryption', env('IMAP_ENCRYPTION', 'ssl')));
    }

    public static function syncLimit(): int
    {
        return max(1, (int) AdminSettings::get('imap_sync_limit', env('IMAP_SYNC_LIMIT', 50)));
    }

    public static function autoSyncSeconds(): int
    {
        return max(0, (int) AdminSettings::get('imap_auto_sync_seconds', env('IMAP_AUTO_SYNC_SECONDS', 120)));
    }

    public static function uiPollSeconds(): int
    {
        return max(0, (int) AdminSettings::get('imap_ui_poll_seconds', env('IMAP_UI_POLL_SECONDS', 15)));
    }

    public static function notificationsPollSeconds(): int
    {
        return max(0, (int) AdminSettings::get('imap_notifications_poll_seconds', env('IMAP_NOTIFICATIONS_POLL_SECONDS', 10)));
    }

    public static function isConfigured(): bool
    {
        return static::username() !== '' && static::password() !== '';
    }
}
