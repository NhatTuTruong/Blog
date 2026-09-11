<?php

namespace App\Filament\Admin\Resources\BlogResource\Concerns;

use App\Models\BlogDeal;

trait SyncsBlogDeals
{
    /** @var array<int, array<string, mixed>> */
    protected array $pendingDealsData = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (! $this->record) {
            return $data;
        }

        $data['deals_data'] = $this->record->deals()
            ->reorder()
            ->orderBy('blog_blog_deal.sort_order')
            ->orderBy('blog_deals.id')
            ->get()
            ->map(fn (BlogDeal $deal): array => [
                'id' => $deal->id,
                'title' => $deal->title,
                'description' => $deal->description,
                'coupon_code' => $deal->coupon_code,
                'shop_url' => $deal->shop_url,
            ])
            ->values()
            ->all();

        return $data;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingDealsData = $data['deals_data'] ?? [];

        return $this->stripDealsDataFromBlogSave($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingDealsData = $data['deals_data'] ?? [];

        return $this->stripDealsDataFromBlogSave($data);
    }

    protected function stripDealsDataFromBlogSave(array $data): array
    {
        unset($data['deals_data']);

        return $data;
    }

    protected function syncBlogDeals(): void
    {
        $dealsData = $this->pendingDealsData;

        $blog = $this->record?->fresh();

        if (! $blog) {
            return;
        }

        $sync = [];
        $sortOrder = 0;

        foreach ($dealsData as $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['title'] ?? ''));
            $shopUrl = trim((string) ($item['shop_url'] ?? ''));

            if ($title === '' && $shopUrl === '') {
                continue;
            }

            $attributes = [
                'title' => $title,
                'description' => filled($item['description'] ?? null)
                    ? trim((string) $item['description'])
                    : null,
                'coupon_code' => filled($item['coupon_code'] ?? null)
                    ? trim((string) $item['coupon_code'])
                    : null,
                'shop_url' => $shopUrl,
                'sort_order' => $sortOrder,
            ];

            $dealId = isset($item['id']) && filled($item['id']) ? (int) $item['id'] : null;
            $deal = $dealId ? BlogDeal::query()->find($dealId) : null;

            if ($deal) {
                $deal->update($attributes);
            } else {
                $deal = BlogDeal::query()->create($attributes);
            }

            $sync[$deal->id] = ['sort_order' => $sortOrder];
            $sortOrder++;
        }

        $blog->deals()->sync($sync);
    }
}
