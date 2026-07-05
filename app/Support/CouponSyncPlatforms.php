<?php

namespace App\Support;

class CouponSyncPlatforms
{
    public const BLOG = 'blog';

    public const INSTAGRAM = 'instagram';

    public const FACEBOOK = 'facebook';

    /**
     * @return array<int, string>
     */
    public static function defaultsFromConfig(): array
    {
        $platforms = [];

        if (config('coupon_sync.enqueue_blog', true)) {
            $platforms[] = self::BLOG;
        }

        if (config('coupon_sync.enqueue_instagram', true)) {
            $platforms[] = self::INSTAGRAM;
        }

        if (config('coupon_sync.enqueue_facebook', true)) {
            $platforms[] = self::FACEBOOK;
        }

        return $platforms;
    }

    /**
     * @param  array<int, mixed>  $platforms
     * @return array<int, string>
     */
    public static function normalize(array $platforms): array
    {
        return collect($platforms)
            ->map(fn (mixed $platform): ?string => self::normalizeOne($platform))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function normalizeOne(mixed $platform): ?string
    {
        $value = strtolower(trim((string) $platform));

        return match ($value) {
            self::BLOG, 'blogs' => self::BLOG,
            self::INSTAGRAM, 'ig', 'insta' => self::INSTAGRAM,
            self::FACEBOOK, 'fb' => self::FACEBOOK,
            default => null,
        };
    }

    public static function isValid(mixed $platform): bool
    {
        if ($platform === null || trim((string) $platform) === '') {
            return false;
        }

        return self::normalizeOne($platform) !== null;
    }

    public static function invalidMessage(): string
    {
        return 'Mỗi phần tử trong platforms phải là "blog", "instagram" hoặc "facebook".';
    }

    public static function label(string $platform): string
    {
        return match ($platform) {
            self::BLOG => 'Blog',
            self::INSTAGRAM => 'Instagram',
            self::FACEBOOK => 'Facebook',
            default => $platform,
        };
    }
}
