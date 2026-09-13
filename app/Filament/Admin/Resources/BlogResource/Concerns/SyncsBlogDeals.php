<?php

namespace App\Filament\Admin\Resources\BlogResource\Concerns;

use App\Models\BlogDeal;
use App\Services\BlogDealsSyncService;

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
        $blog = $this->record?->fresh();

        if (! $blog) {
            return;
        }

        app(BlogDealsSyncService::class)->sync($blog, $this->pendingDealsData);
    }
}
