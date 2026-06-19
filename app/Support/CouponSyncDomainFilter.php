<?php

namespace App\Support;

use App\Models\AutoBlogQueueItem;
use App\Models\FacebookQueueItem;
use App\Models\InstagramQueueItem;

class CouponSyncDomainFilter
{
    public const PLATFORM_BLOG = 'blog';

    public const PLATFORM_INSTAGRAM = 'instagram';

    public const PLATFORM_FACEBOOK = 'facebook';

    public static function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
        $domain = preg_replace('#^www\.#i', '', $domain) ?? $domain;
        $domain = (string) preg_replace('#/.*$#', '', $domain);

        return rtrim($domain, '/');
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    public static function filterRecords(array $records, string $platform, ?int $userId = null): array
    {
        if (! CouponSyncSettings::dedupeDomainEnabled()) {
            return [$records, []];
        }

        $blocked = array_flip(static::blockedDomainKeys($platform, $userId));
        $filtered = [];
        $skipped = [];

        foreach ($records as $record) {
            $domain = trim((string) ($record['brand_domain'] ?? ''));

            if ($domain === '') {
                continue;
            }

            $key = static::normalizeDomain($domain);

            if ($key !== '' && isset($blocked[$key])) {
                $skipped[] = $domain;

                continue;
            }

            $filtered[] = $record;
        }

        return [$filtered, array_values(array_unique($skipped))];
    }

    public static function isDomainBlocked(string $platform, string $domain, ?int $userId = null): bool
    {
        if (! CouponSyncSettings::dedupeDomainEnabled()) {
            return false;
        }

        $key = static::normalizeDomain($domain);

        if ($key === '') {
            return false;
        }

        return in_array($key, static::blockedDomainKeys($platform, $userId), true);
    }

    /**
     * Domain đang chờ / đang xử lý / hoàn thành → chặn. Chỉ thất bại → cho phép đẩy lại.
     *
     * @return array<int, string>
     */
    public static function blockedDomainKeys(string $platform, ?int $userId = null): array
    {
        $blockingStatuses = match ($platform) {
            self::PLATFORM_BLOG => [
                AutoBlogQueueItem::STATUS_PENDING,
                AutoBlogQueueItem::STATUS_PROCESSING,
                AutoBlogQueueItem::STATUS_COMPLETED,
            ],
            self::PLATFORM_INSTAGRAM => [
                InstagramQueueItem::STATUS_PENDING,
                InstagramQueueItem::STATUS_PROCESSING,
                InstagramQueueItem::STATUS_COMPLETED,
            ],
            self::PLATFORM_FACEBOOK => [
                FacebookQueueItem::STATUS_PENDING,
                FacebookQueueItem::STATUS_PROCESSING,
                FacebookQueueItem::STATUS_COMPLETED,
            ],
            default => [],
        };

        if ($blockingStatuses === []) {
            return [];
        }

        $query = match ($platform) {
            self::PLATFORM_BLOG => AutoBlogQueueItem::query(),
            self::PLATFORM_INSTAGRAM => InstagramQueueItem::query(),
            self::PLATFORM_FACEBOOK => FacebookQueueItem::query(),
            default => null,
        };

        if ($query === null) {
            return [];
        }

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query
            ->whereIn('status', $blockingStatuses)
            ->whereNotNull('brand_domain')
            ->where('brand_domain', '!=', '')
            ->pluck('brand_domain')
            ->map(fn (mixed $domain): string => static::normalizeDomain((string) $domain))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
