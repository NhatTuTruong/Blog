<?php

namespace App\Support;

class SocialMediaMediaType
{
    public const IMAGE = 'image';

    public const VIDEO = 'video';

    public static function normalize(?string $value): string
    {
        return strtolower(trim((string) $value)) === self::VIDEO ? self::VIDEO : self::IMAGE;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::IMAGE => 'Hình ảnh',
            self::VIDEO => 'Video',
        ];
    }
}
