<?php

namespace App\Support;

class SocialMediaQueueSource
{
    public const MANUAL = 'manual';

    public const AUTO = 'auto';

    public static function label(?string $source): string
    {
        return match ($source) {
            self::AUTO => 'Tự động',
            default => 'Thủ công',
        };
    }

    public static function isManual(?string $source): bool
    {
        return $source === null || $source === '' || $source === self::MANUAL;
    }
}
