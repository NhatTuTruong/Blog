<?php

namespace App\Services;

use App\Models\Blog;
use App\Models\BlogDeal;

class BlogDealsSyncService
{
    /**
     * @param  array<int, mixed>  $dealsData
     * @return array<int, array<string, mixed>>
     */
    public function normalizeDealsData(array $dealsData): array
    {
        return collect($dealsData)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item): array {
                return [
                    'id' => isset($item['id']) && filled($item['id']) ? (int) $item['id'] : null,
                    'title' => trim((string) ($item['title'] ?? '')),
                    'description' => filled($item['description'] ?? null)
                        ? trim((string) $item['description'])
                        : null,
                    'coupon_code' => filled($item['coupon_code'] ?? null)
                        ? trim((string) $item['coupon_code'])
                        : null,
                    'shop_url' => trim((string) ($item['shop_url'] ?? '')),
                ];
            })
            ->filter(fn (array $item): bool => $item['title'] !== ''
                || $item['shop_url'] !== ''
                || filled($item['coupon_code']))
            ->values()
            ->all();
    }

    /**
     * Gộp cột coupon_codes (Excel / Auto blog) thành deals_data để sync pivot blog_blog_deal.
     *
     * @param  array<int, mixed>  $dealsData
     * @param  array<int, mixed>  $couponCodes
     * @return array<int, array<string, mixed>>
     */
    public function buildDealsPayload(
        array $dealsData,
        array $couponCodes,
        ?string $affLink = null,
        ?string $brandDomain = null,
    ): array {
        $normalized = $this->normalizeDealsData($dealsData);
        $shopUrl = filled($affLink) ? trim($affLink) : '';

        if ($shopUrl === '' && filled($brandDomain)) {
            $domain = trim($brandDomain);
            $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
            $shopUrl = 'https://'.ltrim($domain, '/');
        }

        $existingCodes = collect($normalized)
            ->map(fn (array $deal): string => mb_strtoupper(trim((string) ($deal['coupon_code'] ?? ''))))
            ->filter()
            ->all();

        $codes = collect($couponCodes)
            ->map(fn (mixed $code): string => trim((string) $code))
            ->filter()
            ->unique()
            ->values();

        foreach ($codes as $index => $code) {
            $upper = mb_strtoupper($code);
            if (in_array($upper, $existingCodes, true)) {
                continue;
            }

            $title = $codes->count() > 1
                ? 'Coupon Code '.($index + 1)
                : 'Coupon Code';

            $normalized[] = [
                'id' => null,
                'title' => $title,
                'description' => null,
                'coupon_code' => $code,
                'shop_url' => $shopUrl,
            ];

            $existingCodes[] = $upper;
        }

        return $this->normalizeDealsData($normalized);
    }

    /**
     * @param  array<int, mixed>  $dealsData
     */
    public function sync(Blog $blog, array $dealsData): void
    {
        $sync = [];

        foreach ($this->normalizeDealsData($dealsData) as $item) {
            $attributes = [
                'title' => $item['title'],
                'description' => $item['description'],
                'coupon_code' => $item['coupon_code'],
                'shop_url' => $item['shop_url'],
                'sort_order' => 0,
            ];

            $deal = $item['id'] ? BlogDeal::query()->find($item['id']) : null;

            if ($deal) {
                $deal->update($attributes);
            } else {
                $deal = BlogDeal::query()->create($attributes);
            }

            $sync[$deal->id] = ['sort_order' => 0];
        }

        $blog->deals()->sync($sync);
    }
}
