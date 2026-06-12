<?php

namespace App\Support;

class GeminiKeyScope
{
    public const AUTO_BLOG = 'auto_blog';

    public const INSTAGRAM = 'instagram';

    public const FACEBOOK = 'facebook';

    public const PINTEREST = 'pinterest';

    public static function settingKey(string $scope): string
    {
        return match ($scope) {
            self::INSTAGRAM => 'gemini_api_key_instagram',
            self::FACEBOOK => 'gemini_api_key_facebook',
            self::PINTEREST => 'gemini_api_key_pinterest',
            default => 'gemini_api_key_auto_blog',
        };
    }

    public static function label(string $scope): string
    {
        return match ($scope) {
            self::INSTAGRAM => 'Instagram',
            self::FACEBOOK => 'Facebook',
            self::PINTEREST => 'Pinterest',
            default => 'Đăng bài viết tự động',
        };
    }
}
