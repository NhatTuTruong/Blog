<?php

namespace App\Support;

class ApifyTikTokSharedVideo
{
    public static function relativePath(int $userId, string $hashtag): string
    {
        $hashtag = strtolower(preg_replace('/[^a-z0-9_]/', '', ltrim($hashtag, '#')) ?: 'fyp');

        return "apify-videos/user-{$userId}/{$hashtag}.mp4";
    }

    public static function coverRelativePath(int $userId, string $hashtag): string
    {
        return preg_replace('/\.mp4$/i', '-cover.jpg', self::relativePath($userId, $hashtag)) ?? '';
    }

    public static function cacheKey(int $userId, string $hashtag): string
    {
        $hashtag = strtolower(preg_replace('/[^a-z0-9_]/', '', ltrim($hashtag, '#')) ?: 'fyp');

        return "apify:tiktok:results:{$userId}:{$hashtag}";
    }

    public static function lockKey(int $userId, string $hashtag): string
    {
        $hashtag = strtolower(preg_replace('/[^a-z0-9_]/', '', ltrim($hashtag, '#')) ?: 'fyp');

        return "apify:tiktok:lock:{$userId}:{$hashtag}";
    }
}
