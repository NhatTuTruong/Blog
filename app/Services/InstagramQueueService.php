<?php

namespace App\Services;

use App\Models\InstagramAccount;
use App\Models\InstagramQueueItem;
use App\Models\User;
use App\Support\AdminSettings;
use App\Support\GeminiKeyScope;
use App\Support\InstagramSettings;
use App\Support\SocialMediaMediaType;
use App\Support\SocialMediaQueueSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InstagramQueueService
{
    public ?string $lastError = null;

    public ?string $lastPublishNote = null;

    public function intervalMinutes(?int $userId = null): int
    {
        return InstagramSettings::queueIntervalMinutes($userId);
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

        // Gemini + upload video Meta có thể mất vài phút; sau đó coi là kẹt.
        return max(8, (int) ceil($geminiTimeout / 60) + 3);
    }

    public function hasStuckProcessing(): bool
    {
        return InstagramQueueItem::query()
            ->where('status', InstagramQueueItem::STATUS_PROCESSING)
            ->exists();
    }

    public function releaseStuckProcessingItems(bool $force = false): int
    {
        $query = InstagramQueueItem::query()
            ->where('status', InstagramQueueItem::STATUS_PROCESSING);

        if (! $force) {
            $query->where('updated_at', '<', now()->subMinutes($this->staleProcessingMinutes()));
        }

        $staleItems = $query->get();

        if ($staleItems->isEmpty()) {
            return 0;
        }

        foreach ($staleItems as $item) {
            $item->update([
                'status' => InstagramQueueItem::STATUS_PENDING,
                'processed_at' => null,
                'error_message' => null,
            ]);
        }

        Log::warning('InstagramQueueService released stuck processing items', [
            'force' => $force,
            'count' => $staleItems->count(),
            'queue_item_ids' => $staleItems->pluck('id')->all(),
        ]);

        return $staleItems->count();
    }

    public function recoverStaleProcessingItems(): int
    {
        return app(QueueStaleRecoveryService::class)->failStaleItems(InstagramQueueItem::class);
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
     * @param  array<int, int>  $accountIds
     */
    public function enqueue(
        array $records,
        ?User $user = null,
        ?Carbon $startAt = null,
        array $accountIds = [],
        string $queueSource = SocialMediaQueueSource::MANUAL,
    ): ?string {
        $this->lastError = null;

        $validRecords = collect($records)
            ->filter(fn (array $record): bool => $this->recordHasContent($record))
            ->values();

        if ($validRecords->isEmpty()) {
            $this->lastError = 'Chưa có bản ghi hợp lệ (nhập domain, ý tưởng, tải ảnh/video, hoặc Link Affiliate/coupon).';

            return null;
        }

        $ownerUserId = $user?->id;

        if (! InstagramSettings::isConfigured($ownerUserId)) {
            $this->lastError = 'Instagram chưa được cấu hình — thêm ít nhất một tài khoản trong Cài đặt tích hợp.';

            return null;
        }

        $accountIds = array_values(array_unique(array_map('intval', $accountIds)));
        if ($accountIds === []) {
            $accountIds = InstagramAccount::enabledConfiguredIds($ownerUserId);
        }

        $accounts = InstagramAccount::query()
            ->where('owner_user_id', \App\Support\IntegrationSettingsStore::for($ownerUserId)->userId())
            ->whereIn('id', $accountIds)
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (InstagramAccount $account): bool => $account->isConfigured())
            ->values();

        if ($accounts->isEmpty()) {
            $this->lastError = 'Chưa chọn tài khoản Instagram hợp lệ.';

            return null;
        }

        $batchId = (string) Str::uuid();
        $interval = $this->intervalMinutes($ownerUserId);
        $baseTime = ($startAt ?? now())->copy();
        $queueIndex = 0;

        foreach ($validRecords as $recordIndex => $record) {
            $couponCodes = collect($record['coupon_codes'] ?? [])
                ->map(fn (mixed $code): string => trim((string) $code))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $brandDomain = filled($record['brand_domain'] ?? null) ? trim((string) $record['brand_domain']) : null;
            $contentIdea = filled($record['content_idea'] ?? null) ? trim((string) $record['content_idea']) : null;
            $affLink = filled($record['aff_link'] ?? null) ? trim((string) $record['aff_link']) : null;
            $scheduledAt = $baseTime->copy()->addMinutes($recordIndex * $interval);

            foreach ($accounts as $account) {
                InstagramQueueItem::query()->create([
                    'batch_id' => $batchId,
                    'queue_source' => $queueSource,
                    'user_id' => $user?->id,
                    'instagram_account_id' => $account->id,
                    'sort_order' => $queueIndex,
                    'brand_domain' => $brandDomain,
                    'content_idea' => $contentIdea,
                    'aff_link' => $affLink,
                    'coupon_codes' => $couponCodes !== [] ? $couponCodes : null,
                    'image_path' => filled($record['image'] ?? null) ? trim((string) $record['image']) : null,
                    'video_path' => filled($record['video'] ?? null) ? trim((string) $record['video']) : null,
                    'media_type' => $this->resolveMediaType($record),
                    'caption' => null,
                    'used_default_caption' => false,
                    'status' => InstagramQueueItem::STATUS_PENDING,
                    'scheduled_at' => $scheduledAt,
                ]);

                $queueIndex++;
            }
        }

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

        $hasManualActive = InstagramQueueItem::query()
            ->where(function ($query): void {
                $query->where('queue_source', SocialMediaQueueSource::MANUAL)
                    ->orWhereNull('queue_source');
            })
            ->whereIn('status', [
                InstagramQueueItem::STATUS_PENDING,
                InstagramQueueItem::STATUS_PROCESSING,
            ])
            ->exists();

        $pendingQuery = InstagramQueueItem::query()
            ->where('status', InstagramQueueItem::STATUS_PENDING)
            ->where('scheduled_at', '<=', now());

        if ($hasManualActive) {
            $pendingQuery->where(function ($query): void {
                $query->where('queue_source', SocialMediaQueueSource::MANUAL)
                    ->orWhereNull('queue_source');
            });
        }

        /** @var InstagramQueueItem|null $item */
        $item = $pendingQuery
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
                'error_message' => $this->lastPublishNote,
            ]);

            $this->lastPublishNote = null;

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

            Log::warning('InstagramQueueService item failed — queue continues', [
                'queue_item_id' => $item->id,
                'error' => $message,
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
                $item->user_id,
                GeminiKeyScope::INSTAGRAM,
            );
            $usedDefaultCaption = $gemini->usedDefaultCaption;

            $item->update([
                'caption' => $caption,
                'used_default_caption' => $usedDefaultCaption,
                'error_message' => $usedDefaultCaption && filled($gemini->lastError)
                    ? 'AI: '.$gemini->lastError
                    : null,
            ]);
        }

        $item->loadMissing('instagramAccount');

        $videoSource = app(SocialMediaVideoSourceService::class);
        if ($videoSource->itemWantsAutoVideo($item)) {
            $videoSource->ensureStoredVideoForInstagramItem($item);
            $item = $item->fresh() ?? $item;
        }

        /** @var InstagramAccount|null $account */
        $account = $item->instagramAccount;
        if ($account === null || ! $account->isConfigured()) {
            $account = InstagramSettings::primaryAccount($item->user_id);
        }
        if ($account === null || ! $account->isConfigured()) {
            throw new \RuntimeException('Tài khoản Instagram không hợp lệ hoặc đã bị xóa.');
        }

        $media = app(InstagramPostImageService::class);
        $graph = app(InstagramGraphService::class)->forAccount($account);

        if (filled($item->video_path)) {
            $mediaUrl = $media->signedPublicUrl($item);
            if ($mediaUrl === null) {
                throw new \RuntimeException($media->lastError ?? 'Không tạo được URL media công khai.');
            }

            $urlError = $media->validatePublicVideoUrl($mediaUrl);
            if ($urlError !== null) {
                throw new \RuntimeException($urlError);
            }

            $mediaId = $graph->publishVideo($mediaUrl, (string) $caption);
        } else {
            $mediaId = $this->publishImageWithDefaultFallback($item, $media, $graph, (string) $caption);
        }

        if ($mediaId === null) {
            throw new \RuntimeException($graph->lastError ?? 'Không thể đăng lên Instagram.');
        }

        return $mediaId;
    }

    protected function publishImageWithDefaultFallback(
        InstagramQueueItem $item,
        InstagramPostImageService $media,
        InstagramGraphService $graph,
        string $caption,
    ): ?string {
        $this->lastPublishNote = null;
        $originalError = null;

        $mediaUrl = $media->signedPublicUrl($item->fresh() ?? $item);
        if ($mediaUrl === null) {
            $originalError = $media->lastError ?? 'Không tạo được URL ảnh.';
        } else {
            $urlError = $media->validatePublicImageUrl($mediaUrl);
            if ($urlError !== null) {
                $originalError = $urlError;
            } else {
                $mediaId = $graph->publishImage($mediaUrl, $caption);
                if ($mediaId !== null) {
                    return $mediaId;
                }

                $originalError = $graph->lastError;
            }
        }

        if (! $this->isInstagramImageDeliveryError($originalError)) {
            return null;
        }

        if (! $media->applyDefaultImageForItem($item)) {
            $graph->lastError = $originalError ?? $media->lastError ?? $graph->lastError;

            return null;
        }

        $item = $item->fresh() ?? $item;
        $fallbackUrl = $media->signedPublicUrl($item);
        if ($fallbackUrl === null) {
            $graph->lastError = $originalError ?? $media->lastError;

            return null;
        }

        $fallbackUrlError = $media->validatePublicImageUrl($fallbackUrl);
        if ($fallbackUrlError !== null) {
            $graph->lastError = $fallbackUrlError;

            return null;
        }

        $mediaId = $graph->publishImage($fallbackUrl, $caption);
        if ($mediaId !== null) {
            $note = 'Đã đăng với ảnh mặc định.';
            if (filled($originalError)) {
                $note .= ' Ảnh gốc: '.trim((string) $originalError);
            }
            $this->lastPublishNote = $note;

            return $mediaId;
        }

        return null;
    }

    protected function isInstagramImageDeliveryError(?string $message): bool
    {
        if (! filled($message)) {
            return false;
        }

        $message = strtolower((string) $message);

        return str_contains($message, '9004')
            || str_contains($message, '2207052')
            || str_contains($message, 'only photo or video')
            || str_contains($message, 'meta không tải được ảnh')
            || str_contains($message, 'content-type không phải jpeg')
            || str_contains($message, 'không truy cập được url ảnh')
            || str_contains($message, 'không kiểm tra được url ảnh');
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
     * @param  array<string, mixed>  $record
     */
    protected function resolveMediaType(array $record): string
    {
        if (filled($record['video'] ?? null)) {
            return SocialMediaMediaType::VIDEO;
        }

        return SocialMediaMediaType::normalize($record['media_type'] ?? null);
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
            'auto_pending' => InstagramQueueItem::query()
                ->where('queue_source', SocialMediaQueueSource::AUTO)
                ->where('status', InstagramQueueItem::STATUS_PENDING)
                ->count(),
            'manual_pending' => InstagramQueueItem::query()
                ->where(function ($query): void {
                    $query->where('queue_source', SocialMediaQueueSource::MANUAL)
                        ->orWhereNull('queue_source');
                })
                ->where('status', InstagramQueueItem::STATUS_PENDING)
                ->count(),
            'auto_processing' => InstagramQueueItem::query()
                ->where('queue_source', SocialMediaQueueSource::AUTO)
                ->where('status', InstagramQueueItem::STATUS_PROCESSING)
                ->count(),
            'manual_processing' => InstagramQueueItem::query()
                ->where(function ($query): void {
                    $query->where('queue_source', SocialMediaQueueSource::MANUAL)
                        ->orWhereNull('queue_source');
                })
                ->where('status', InstagramQueueItem::STATUS_PROCESSING)
                ->count(),
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
