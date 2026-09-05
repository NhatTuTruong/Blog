<?php

namespace App\Services;

use App\Models\AutoBlogQueueItem;
use App\Models\Blog;
use App\Models\User;
use App\Support\AdminSettings;
use App\Support\BlogCategorySelection;
use App\Support\GeminiKeyScope;
use App\Support\GeminiSettings;
use App\Support\IntegrationSettingsStore;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AutoBlogQueueService
{
    public ?string $lastError = null;

    public function intervalMinutes(): int
    {
        return max(1, min(1440, (int) AdminSettings::get('auto_blog_queue_interval_minutes', 10)));
    }

    public function hasActiveQueue(): bool
    {
        return AutoBlogQueueItem::query()
            ->whereIn('status', [
                AutoBlogQueueItem::STATUS_PENDING,
                AutoBlogQueueItem::STATUS_PROCESSING,
            ])
            ->exists();
    }

    public function activeQueueSummary(): string
    {
        $stats = $this->queueStats();
        $parts = [];

        if ($stats['pending'] > 0) {
            $parts[] = $stats['pending'].' chờ';
        }

        if ($stats['processing'] > 0) {
            $parts[] = $stats['processing'].' đang tạo';
        }

        return $parts !== [] ? implode(', ', $parts) : 'không có bài đang chạy';
    }

    protected function staleProcessingMinutes(): int
    {
        $geminiTimeout = max(60, (int) AdminSettings::get('gemini_timeout', 120));
        $modelCount = max(1, count(GeminiSettings::availableModels()));
        $apifyWait = max(30, (int) config('apify.run_wait_seconds', 180));

        // Gemini (fallback nhiều model) + Apify sync + buffer tải ảnh + start overhead
        $seconds = ($geminiTimeout * $modelCount) + $apifyWait + 600;

        return max(20, min(90, (int) ceil($seconds / 60)));
    }

    public function recoverStaleProcessingItems(): int
    {
        $minutes = $this->staleProcessingMinutes();

        return app(QueueStaleRecoveryService::class)->failStaleItems(
            AutoBlogQueueItem::class,
            $minutes,
            'Quá '.$minutes.' phút ở trạng thái «Đang tạo» — có thể timeout Gemini/Apify. Bài đã chuyển sang Lỗi; hàng đợi tiếp tục.',
            failStalePending: false,
        );
    }

    protected function hasActiveProcessing(): bool
    {
        return AutoBlogQueueItem::query()
            ->where('status', AutoBlogQueueItem::STATUS_PROCESSING)
            ->exists();
    }

    protected function claimQueueItem(AutoBlogQueueItem $item): bool
    {
        $now = now();

        return AutoBlogQueueItem::query()
            ->where('id', $item->id)
            ->where('status', AutoBlogQueueItem::STATUS_PENDING)
            ->update([
                'status' => AutoBlogQueueItem::STATUS_PROCESSING,
                'updated_at' => $now,
            ]) === 1;
    }

    public function releaseStuckProcessingItems(): int
    {
        $message = 'Đã gỡ kẹt thủ công — tiến trình tạo bài có thể bị timeout hoặc dừng giữa chừng.';

        $items = AutoBlogQueueItem::query()
            ->where('status', AutoBlogQueueItem::STATUS_PROCESSING)
            ->get();

        foreach ($items as $item) {
            $item->update([
                'status' => AutoBlogQueueItem::STATUS_FAILED,
                'processed_at' => now(),
                'error_message' => $message,
            ]);
        }

        if ($items->isNotEmpty()) {
            Log::warning('AutoBlogQueueService manually released stuck processing items', [
                'count' => $items->count(),
                'queue_item_ids' => $items->pluck('id')->all(),
            ]);
        }

        return $items->count();
    }

    public function abortQueueOnError(string $reason): int
    {
        $cancelled = $this->cancelPendingQueue();

        if ($cancelled > 0) {
            Log::warning('AutoBlogQueueService aborted queue after error', [
                'reason' => $reason,
                'cancelled_pending' => $cancelled,
            ]);
        }

        return $cancelled;
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    public function enqueue(array $records, ?User $user = null, ?Carbon $startAt = null): ?string
    {
        $this->lastError = null;

        $validRecords = collect($records)
            ->filter(fn (array $record): bool => filled($record['brand_domain'] ?? null))
            ->values();

        if ($validRecords->isEmpty()) {
            $this->lastError = 'Chưa có bản ghi hợp lệ (cần ít nhất Domain brand).';

            return null;
        }

        $ownerUserId = $user?->id ?? IntegrationSettingsStore::fallbackAdminUserId();

        if (! GeminiSettings::hasApiKey(GeminiKeyScope::AUTO_BLOG, $ownerUserId)) {
            $this->lastError = 'Gemini API key cho Đăng bài viết tự động chưa được cấu hình trong Cài đặt tích hợp.';

            return null;
        }

        $batchId = (string) Str::uuid();
        $interval = $this->intervalMinutes();
        $baseTime = ($startAt ?? now())->copy();

        $validRecords->each(function (array $record, int $index) use ($batchId, $user, $interval, $baseTime): void {
            $categoryIds = BlogCategorySelection::normalizeIds(
                $record['blog_category_ids'] ?? $record['blog_category_id'] ?? null
            );
            $primaryCategoryId = $categoryIds[0] ?? null;
            $categoryName = BlogCategorySelection::labelForIds($categoryIds);

            $couponCodes = collect($record['coupon_codes'] ?? [])
                ->map(fn (mixed $code): string => trim((string) $code))
                ->filter()
                ->unique()
                ->values()
                ->all();

            AutoBlogQueueItem::query()->create([
                'batch_id' => $batchId,
                'user_id' => $user?->id,
                'sort_order' => $index,
                'brand_domain' => trim((string) $record['brand_domain']),
                'blog_category_id' => $primaryCategoryId,
                'blog_category_ids' => $categoryIds !== [] ? $categoryIds : null,
                'category_name' => $categoryName,
                'content_idea' => filled($record['content_idea'] ?? null) ? trim((string) $record['content_idea']) : null,
                'aff_link' => filled($record['aff_link'] ?? null) ? trim((string) $record['aff_link']) : null,
                'coupon_codes' => $couponCodes !== [] ? $couponCodes : null,
                'image_path' => $this->normalizeRecordImagePath($record['featured_image'] ?? $record['image_path'] ?? null),
                'status' => AutoBlogQueueItem::STATUS_PENDING,
                'scheduled_at' => $baseTime->copy()->addMinutes($index * $interval),
            ]);
        });

        return $batchId;
    }

    /**
     * @return array{processed: bool, item: ?AutoBlogQueueItem, blog: ?Blog}
     */
    public function processNextDue(): array
    {
        $this->lastError = null;

        $this->recoverStaleProcessingItems();

        if ($this->hasActiveProcessing()) {
            return ['processed' => false, 'item' => null, 'blog' => null];
        }

        /** @var AutoBlogQueueItem|null $item */
        $item = AutoBlogQueueItem::query()
            ->where('status', AutoBlogQueueItem::STATUS_PENDING)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->orderBy('sort_order')
            ->first();

        if (! $item) {
            return ['processed' => false, 'item' => null, 'blog' => null];
        }

        if (! $this->claimQueueItem($item)) {
            return $this->processNextDue();
        }

        $item->refresh();

        try {
            @set_time_limit(900);

            $item->touch();

            $blog = $this->generateBlogFromQueueItem($item);

            $item->update([
                'status' => AutoBlogQueueItem::STATUS_COMPLETED,
                'blog_id' => $blog->id,
                'processed_at' => now(),
                'error_message' => null,
            ]);

            return ['processed' => true, 'item' => $item->fresh(), 'blog' => $blog];
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $this->lastError = $message;

            $item->update([
                'status' => AutoBlogQueueItem::STATUS_FAILED,
                'processed_at' => now(),
                'error_message' => $message,
            ]);

            Log::warning('AutoBlogQueueService item failed — queue continues', [
                'queue_item_id' => $item->id,
                'error' => $message,
            ]);

            return ['processed' => true, 'item' => $item->fresh(), 'blog' => null];
        }
    }

    public function generateBlogFromQueueItem(AutoBlogQueueItem $item): Blog
    {
        $gemini = app(GeminiBlogService::class);

        $categoryIds = $item->resolvedCategoryIds();
        $categoryLabel = BlogCategorySelection::labelForIds($categoryIds)
            ?? $item->category_name
            ?? $item->blogCategory?->name
            ?? 'General';

        $result = $gemini->generateBrandPromoBlog(
            $item->brand_domain,
            $item->content_idea,
            $item->aff_link,
            is_array($item->coupon_codes) ? $item->coupon_codes : [],
            $item->user_id ?? IntegrationSettingsStore::fallbackAdminUserId(),
        );

        if (! $result) {
            throw new \RuntimeException($gemini->lastError ?? 'Không thể tạo nội dung từ AI.');
        }

        $author = User::where('is_admin', true)->first() ?? User::first();
        $featuredImageService = app(AutoBlogFeaturedImageService::class);

        $imageResult = app(AutoBlogContentImageService::class)->enrichBlogWithApifyImages(
            $result['content'],
            $item->brand_domain,
            $item->user_id,
            $item->id,
            $featuredImageService->resolveUploadedPath($item),
        );

        $featuredImage = $imageResult['featured_image']
            ?? $featuredImageService->resolveApifyFallback($item);

        $blog = Blog::create([
            'user_id' => $item->user_id ?? $author?->id,
            'blog_category_id' => $categoryIds[0] ?? $item->blog_category_id,
            'title' => $result['title'],
            'category' => $categoryLabel,
            'content' => $imageResult['content'],
            'featured_image' => $featuredImage,
            'is_published' => true,
            'views_count' => 0,
        ]);

        if ($categoryIds !== []) {
            $blog->syncBlogCategories($categoryIds);
        }

        return $blog;
    }

    /**
     * @param  mixed  $path
     */
    protected function normalizeRecordImagePath(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = $path[array_key_first($path)] ?? null;
        }

        $path = trim((string) $path);

        return $path !== '' ? $path : null;
    }

    /**
     * @return Collection<int, AutoBlogQueueItem>
     */
    public function recentQueueItems(int $limit = 40): Collection
    {
        return AutoBlogQueueItem::query()
            ->with(['blog', 'blogCategory'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{pending: int, processing: int, completed: int, failed: int}
     */
    public function queueStats(): array
    {
        return [
            'pending' => AutoBlogQueueItem::query()->where('status', AutoBlogQueueItem::STATUS_PENDING)->count(),
            'processing' => AutoBlogQueueItem::query()->where('status', AutoBlogQueueItem::STATUS_PROCESSING)->count(),
            'completed' => AutoBlogQueueItem::query()->where('status', AutoBlogQueueItem::STATUS_COMPLETED)->count(),
            'failed' => AutoBlogQueueItem::query()->where('status', AutoBlogQueueItem::STATUS_FAILED)->count(),
        ];
    }

    public function hasPendingQueue(): bool
    {
        return AutoBlogQueueItem::query()
            ->where('status', AutoBlogQueueItem::STATUS_PENDING)
            ->exists();
    }

    public function cancelPendingQueue(): int
    {
        $this->lastError = null;

        return AutoBlogQueueItem::query()
            ->where('status', AutoBlogQueueItem::STATUS_PENDING)
            ->delete();
    }
}
