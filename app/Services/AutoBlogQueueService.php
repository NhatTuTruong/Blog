<?php

namespace App\Services;

use App\Models\AutoBlogQueueItem;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\User;
use App\Support\AdminSettings;
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

        return max(15, (int) ceil($geminiTimeout / 60) + 5);
    }

    public function recoverStaleProcessingItems(): int
    {
        $staleItems = AutoBlogQueueItem::query()
            ->where('status', AutoBlogQueueItem::STATUS_PROCESSING)
            ->where('updated_at', '<', now()->subMinutes($this->staleProcessingMinutes()))
            ->get();

        if ($staleItems->isEmpty()) {
            return 0;
        }

        foreach ($staleItems as $item) {
            $item->update([
                'status' => AutoBlogQueueItem::STATUS_FAILED,
                'processed_at' => now(),
                'error_message' => 'Quá thời gian xử lý — hàng đợi đã dừng để tránh kẹt.',
            ]);
        }

        $this->cancelPendingQueue();

        Log::warning('AutoBlogQueueService recovered stale processing items', [
            'count' => $staleItems->count(),
            'queue_item_ids' => $staleItems->pluck('id')->all(),
        ]);

        return $staleItems->count();
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

        if (! AdminSettings::hasGeminiApiKey()) {
            $this->lastError = 'Gemini API key chưa được cấu hình trong Cài đặt hệ thống.';

            return null;
        }

        $batchId = (string) Str::uuid();
        $interval = $this->intervalMinutes();
        $baseTime = ($startAt ?? now())->copy();

        $validRecords->each(function (array $record, int $index) use ($batchId, $user, $interval, $baseTime): void {
            $categoryId = filled($record['blog_category_id'] ?? null) ? (int) $record['blog_category_id'] : null;
            $categoryName = null;

            if ($categoryId) {
                $categoryName = BlogCategory::query()->find($categoryId)?->name;
            }

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
                'blog_category_id' => $categoryId,
                'category_name' => $categoryName,
                'content_idea' => filled($record['content_idea'] ?? null) ? trim((string) $record['content_idea']) : null,
                'aff_link' => filled($record['aff_link'] ?? null) ? trim((string) $record['aff_link']) : null,
                'coupon_codes' => $couponCodes !== [] ? $couponCodes : null,
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

        if (AutoBlogQueueItem::query()->where('status', AutoBlogQueueItem::STATUS_PROCESSING)->exists()) {
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

        $item->update(['status' => AutoBlogQueueItem::STATUS_PROCESSING]);

        try {
            @set_time_limit(600);

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

            $cancelled = $this->abortQueueOnError($message);

            Log::warning('AutoBlogQueueService process failed — queue aborted', [
                'queue_item_id' => $item->id,
                'error' => $message,
                'cancelled_pending' => $cancelled,
            ]);

            return ['processed' => true, 'item' => $item->fresh(), 'blog' => null];
        }
    }

    public function generateBlogFromQueueItem(AutoBlogQueueItem $item): Blog
    {
        $gemini = app(GeminiBlogService::class);

        $categoryLabel = $item->category_name
            ?? $item->blogCategory?->name
            ?? 'General';

        $result = $gemini->generateBrandPromoBlog(
            $item->brand_domain,
            $item->content_idea,
            $item->aff_link,
            is_array($item->coupon_codes) ? $item->coupon_codes : [],
        );

        if (! $result) {
            throw new \RuntimeException($gemini->lastError ?? 'Không thể tạo nội dung từ AI.');
        }

        $author = User::where('is_admin', true)->first() ?? User::first();

        return Blog::create([
            'user_id' => $item->user_id ?? $author?->id,
            'blog_category_id' => $item->blog_category_id,
            'title' => $result['title'],
            'category' => $categoryLabel,
            'content' => $result['content'],
            'featured_image' => $result['featured_image'] ?? null,
            'is_published' => true,
            'views_count' => 0,
        ]);
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
