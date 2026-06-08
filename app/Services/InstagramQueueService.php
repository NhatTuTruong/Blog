<?php

namespace App\Services;

use App\Models\InstagramQueueItem;
use App\Models\User;
use App\Support\AdminSettings;
use App\Support\InstagramSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InstagramQueueService
{
    public ?string $lastError = null;

    public function intervalMinutes(): int
    {
        return InstagramSettings::queueIntervalMinutes();
    }

    public function hasActiveQueue(): bool
    {
        return InstagramQueueItem::query()
            ->whereIn('status', [
                InstagramQueueItem::STATUS_PENDING,
                InstagramQueueItem::STATUS_PROCESSING,
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
            $parts[] = $stats['processing'].' đang đăng';
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
        $staleItems = InstagramQueueItem::query()
            ->where('status', InstagramQueueItem::STATUS_PROCESSING)
            ->where('updated_at', '<', now()->subMinutes($this->staleProcessingMinutes()))
            ->get();

        if ($staleItems->isEmpty()) {
            return 0;
        }

        foreach ($staleItems as $item) {
            $item->update([
                'status' => InstagramQueueItem::STATUS_FAILED,
                'processed_at' => now(),
                'error_message' => 'Quá thời gian xử lý — hàng đợi đã dừng để tránh kẹt.',
            ]);
        }

        $this->cancelPendingQueue();

        Log::warning('InstagramQueueService recovered stale processing items', [
            'count' => $staleItems->count(),
            'queue_item_ids' => $staleItems->pluck('id')->all(),
        ]);

        return $staleItems->count();
    }

    public function abortQueueOnError(string $reason): int
    {
        $cancelled = $this->cancelPendingQueue();

        if ($cancelled > 0) {
            Log::warning('InstagramQueueService aborted queue after error', [
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
            ->filter(fn (array $record): bool => $this->recordHasContent($record))
            ->values();

        if ($validRecords->isEmpty()) {
            $this->lastError = 'Chưa có bản ghi hợp lệ (nhập domain, ý tưởng, tải ảnh/video, hoặc link AFF/coupon).';

            return null;
        }

        if (! InstagramSettings::isConfigured()) {
            $this->lastError = 'Instagram chưa được cấu hình (token + User ID) trong Cài đặt hệ thống.';

            return null;
        }

        $batchId = (string) Str::uuid();
        $interval = $this->intervalMinutes();
        $baseTime = ($startAt ?? now())->copy();

        $validRecords->each(function (array $record, int $index) use ($batchId, $user, $interval, $baseTime): void {
            $couponCodes = collect($record['coupon_codes'] ?? [])
                ->map(fn (mixed $code): string => trim((string) $code))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $brandDomain = filled($record['brand_domain'] ?? null) ? trim((string) $record['brand_domain']) : null;
            $contentIdea = filled($record['content_idea'] ?? null) ? trim((string) $record['content_idea']) : null;
            $affLink = filled($record['aff_link'] ?? null) ? trim((string) $record['aff_link']) : null;

            $gemini = app(GeminiInstagramService::class);
            $caption = $gemini->generateCaption(
                $brandDomain,
                $contentIdea,
                $affLink,
                $couponCodes,
            );

            InstagramQueueItem::query()->create([
                'batch_id' => $batchId,
                'user_id' => $user?->id,
                'sort_order' => $index,
                'brand_domain' => $brandDomain,
                'content_idea' => $contentIdea,
                'aff_link' => $affLink,
                'coupon_codes' => $couponCodes !== [] ? $couponCodes : null,
                'image_path' => filled($record['image'] ?? null) ? trim((string) $record['image']) : null,
                'video_path' => filled($record['video'] ?? null) ? trim((string) $record['video']) : null,
                'caption' => $caption,
                'used_default_caption' => $gemini->usedDefaultCaption,
                'status' => InstagramQueueItem::STATUS_PENDING,
                'scheduled_at' => $baseTime->copy()->addMinutes($index * $interval),
            ]);
        });

        return $batchId;
    }

    /**
     * @return array{processed: bool, item: ?InstagramQueueItem, media_id: ?string}
     */
    public function processNextDue(): array
    {
        $this->lastError = null;

        $this->recoverStaleProcessingItems();

        if (InstagramQueueItem::query()->where('status', InstagramQueueItem::STATUS_PROCESSING)->exists()) {
            return ['processed' => false, 'item' => null, 'media_id' => null];
        }

        /** @var InstagramQueueItem|null $item */
        $item = InstagramQueueItem::query()
            ->where('status', InstagramQueueItem::STATUS_PENDING)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->orderBy('sort_order')
            ->first();

        if (! $item) {
            return ['processed' => false, 'item' => null, 'media_id' => null];
        }

        $item->update(['status' => InstagramQueueItem::STATUS_PROCESSING]);

        try {
            @set_time_limit(300);

            $mediaId = $this->publishQueueItem($item);

            $item->update([
                'status' => InstagramQueueItem::STATUS_COMPLETED,
                'instagram_media_id' => $mediaId,
                'processed_at' => now(),
                'error_message' => null,
            ]);

            $item = $item->fresh();
            if ($item instanceof InstagramQueueItem && filled($item->video_path)) {
                app(InstagramPostImageService::class)->deleteStoredVideo($item);
            }

            return ['processed' => true, 'item' => $item, 'media_id' => $mediaId];
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $this->lastError = $message;

            $item->update([
                'status' => InstagramQueueItem::STATUS_FAILED,
                'processed_at' => now(),
                'error_message' => $message,
            ]);

            $cancelled = $this->abortQueueOnError($message);

            Log::warning('InstagramQueueService process failed — queue aborted', [
                'queue_item_id' => $item->id,
                'error' => $message,
                'cancelled_pending' => $cancelled,
            ]);

            return ['processed' => true, 'item' => $item->fresh(), 'media_id' => null];
        }
    }

    public function publishQueueItem(InstagramQueueItem $item): string
    {
        $caption = $item->caption;
        $usedDefaultCaption = (bool) $item->used_default_caption;

        if (! filled($caption)) {
            $gemini = app(GeminiInstagramService::class);
            $caption = $gemini->generateCaption(
                $item->brand_domain,
                $item->content_idea,
                $item->aff_link,
                is_array($item->coupon_codes) ? $item->coupon_codes : [],
            );
            $usedDefaultCaption = $gemini->usedDefaultCaption;

            $item->update([
                'caption' => $caption,
                'used_default_caption' => $usedDefaultCaption,
            ]);
        }

        $media = app(InstagramPostImageService::class);
        $mediaUrl = $media->signedPublicUrl($item);

        if ($mediaUrl === null) {
            throw new \RuntimeException($media->lastError ?? 'Không tạo được URL media công khai.');
        }

        $graph = app(InstagramGraphService::class);

        if (filled($item->video_path)) {
            $urlError = $media->validatePublicVideoUrl($mediaUrl);
            if ($urlError !== null) {
                throw new \RuntimeException($urlError);
            }

            $mediaId = $graph->publishVideo($mediaUrl, (string) $caption);
        } else {
            $urlError = $media->validatePublicImageUrl($mediaUrl);
            if ($urlError !== null) {
                throw new \RuntimeException($urlError);
            }

            $mediaId = $graph->publishImage($mediaUrl, (string) $caption);
        }

        if ($mediaId === null) {
            throw new \RuntimeException($graph->lastError ?? 'Không thể đăng lên Instagram.');
        }

        return $mediaId;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function recordHasContent(array $record): bool
    {
        if (filled($record['media'] ?? null)) {
            return true;
        }

        if (filled($record['image'] ?? null)) {
            return true;
        }

        if (filled($record['video'] ?? null)) {
            return true;
        }

        if (filled($record['brand_domain'] ?? null)) {
            return true;
        }

        if (filled($record['content_idea'] ?? null)) {
            return true;
        }

        if (filled($record['aff_link'] ?? null)) {
            return true;
        }

        $coupons = $record['coupon_codes'] ?? [];
        if (is_array($coupons) && collect($coupons)->filter(fn (mixed $c): bool => filled($c))->isNotEmpty()) {
            return true;
        }

        return false;
    }

    /**
     * @return array{pending: int, processing: int, completed: int, failed: int}
     */
    public function queueStats(): array
    {
        return [
            'pending' => InstagramQueueItem::query()->where('status', InstagramQueueItem::STATUS_PENDING)->count(),
            'processing' => InstagramQueueItem::query()->where('status', InstagramQueueItem::STATUS_PROCESSING)->count(),
            'completed' => InstagramQueueItem::query()->where('status', InstagramQueueItem::STATUS_COMPLETED)->count(),
            'failed' => InstagramQueueItem::query()->where('status', InstagramQueueItem::STATUS_FAILED)->count(),
        ];
    }

    public function cancelPendingQueue(): int
    {
        $this->lastError = null;

        return InstagramQueueItem::query()
            ->where('status', InstagramQueueItem::STATUS_PENDING)
            ->delete();
    }

    public function hasPendingQueue(): bool
    {
        return InstagramQueueItem::query()
            ->where('status', InstagramQueueItem::STATUS_PENDING)
            ->exists();
    }
}
