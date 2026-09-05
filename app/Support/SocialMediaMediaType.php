<?php

namespace App\Support;

class SocialMediaMediaType
{
    public const IMAGE = 'image';

    public const VIDEO = 'video';

    public static function normalize(?string $value): string
    {
        return self::normalizeFromApi((string) $value);
    }

    public static function normalizeFromApi(string $value): string
    {
        $raw = strtolower(trim($value));

        if (in_array($raw, [self::VIDEO, 'vid', 'mp4', 'tiktok'], true)) {
            return self::VIDEO;
        }

        return self::IMAGE;
    }

    public static function isValidApiType(mixed $value): bool
    {
        if ($value === null || trim((string) $value) === '') {
            return true;
        }

        $raw = strtolower(trim((string) $value));

        return in_array($raw, [
            self::VIDEO,
            self::IMAGE,
            'vid',
            'mp4',
            'tiktok',
            'img',
            'photo',
            'picture',
        ], true);
    }

    public static function invalidApiTypeMessage(): string
    {
        return 'Trường type phải là "video" hoặc "image".';
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

    public static function isAutoVideoOptionEnabled(): bool
    {
        return (bool) config('social_media.auto_video_option_enabled', true);
    }

    /**
     * Options for admin "Loại media tự động" select (respects SOCIAL_VIDEO_UI_ENABLED).
     *
     * @return array<string, string>
     */
    public static function formOptions(): array
    {
        $options = self::options();

        if (! self::isAutoVideoOptionEnabled()) {
            unset($options[self::VIDEO]);
        }

        return $options;
    }
}
