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
            ->filter(fn (array $item): bool => $item['title'] !== '' || $item['shop_url'] !== '')
            ->values()
            ->all();
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
