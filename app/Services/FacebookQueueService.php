<?php

namespace App\Services;

use App\Models\FacebookAccount;
use App\Models\FacebookQueueItem;
use App\Models\User;
use App\Support\AdminSettings;
use App\Support\FacebookSettings;
use App\Support\GeminiKeyScope;
use App\Support\SocialMediaQueueSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FacebookQueueService
{
    public ?string $lastError = null;

    public function intervalMinutes(?int $userId = null): int
    {
        return FacebookSettings::queueIntervalMinutes($userId);
    }

    public function hasActiveQueue(): bool
    {
        return FacebookQueueItem::query()
            ->whereIn('status', [
                FacebookQueueItem::STATUS_PENDING,
                FacebookQueueItem::STATUS_PROCESSING,
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
        return FacebookQueueItem::query()
            ->where('status', FacebookQueueItem::STATUS_PROCESSING)
            ->exists();
    }

    public function releaseStuckProcessingItems(bool $force = false): int
    {
        $query = FacebookQueueItem::query()
            ->where('status', FacebookQueueItem::STATUS_PROCESSING);

        if (! $force) {
            $query->where('updated_at', '<', now()->subMinutes($this->staleProcessingMinutes()));
        }

        $staleItems = $query->get();

        if ($staleItems->isEmpty()) {
            return 0;
        }

        foreach ($staleItems as $item) {
            $item->update([
                'status' => FacebookQueueItem::STATUS_PENDING,
                'processed_at' => null,
                'error_message' => null,
            ]);
        }

        Log::warning('FacebookQueueService released stuck processing items', [
            'force' => $force,
            'count' => $staleItems->count(),
            'queue_item_ids' => $staleItems->pluck('id')->all(),
        ]);

        return $staleItems->count();
    }

    public function recoverStaleProcessingItems(): int
    {
        return app(QueueStaleRecoveryService::class)->failStaleItems(FacebookQueueItem::class);
    }

    public function abortQueueOnError(string $reason): int
    {
        $cancelled = $this->cancelPendingQueue();

        if ($cancelled > 0) {
            Log::warning('FacebookQueueService aborted queue after error', [
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

        if (! FacebookSettings::isConfigured($ownerUserId)) {
            $this->lastError = 'Facebook chưa được cấu hình — thêm ít nhất một tài khoản trong Cài đặt tích hợp.';

            return null;
        }

        $accountIds = array_values(array_unique(array_map('intval', $accountIds)));
        if ($accountIds === []) {
            $accountIds = FacebookAccount::enabledConfiguredIds($ownerUserId);
        }

        $accounts = FacebookAccount::query()
            ->where('owner_user_id', \App\Support\IntegrationSettingsStore::for($ownerUserId)->userId())
            ->whereIn('id', $accountIds)
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (FacebookAccount $account): bool => $account->isConfigured())
            ->values();

        if ($accounts->isEmpty()) {
            $this->lastError = 'Chưa chọn tài khoản Facebook hợp lệ.';

            return null;
        }

        $batchId = (string) Str::uuid();
        $interval = $this->intervalMinutes($ownerUserId);
        $baseTime = ($startAt ?? now())->copy();
        $queueIndex = 0;

        foreach ($validRecords as $record) {
            $couponCodes = collect($record['coupon_codes'] ?? [])
                ->map(fn (mixed $code): string => trim((string) $code))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $brandDomain = filled($record['brand_domain'] ?? null) ? trim((string) $record['brand_domain']) : null;
            $contentIdea = filled($record['content_idea'] ?? null) ? trim((string) $record['content_idea']) : null;
            $affLink = filled($record['aff_link'] ?? null) ? trim((string) $record['aff_link']) : null;

            foreach ($accounts as $account) {
                FacebookQueueItem::query()->create([
                    'batch_id' => $batchId,
                    'queue_source' => $queueSource,
                    'user_id' => $user?->id,
                    'facebook_account_id' => $account->id,
                    'sort_order' => $queueIndex,
                    'brand_domain' => $brandDomain,
                    'content_idea' => $contentIdea,
                    'aff_link' => $affLink,
                    'coupon_codes' => $couponCodes !== [] ? $couponCodes : null,
                    'image_path' => filled($record['image'] ?? null) ? trim((string) $record['image']) : null,
                    'video_path' => filled($record['video'] ?? null) ? trim((string) $record['video']) : null,
                    'caption' => null,
                    'used_default_caption' => false,
                    'status' => FacebookQueueItem::STATUS_PENDING,
                    'scheduled_at' => $baseTime->copy()->addMinutes($queueIndex * $interval),
                ]);

                $queueIndex++;
            }
        }

        return $batchId;
    }

    /**
     * @return array{processed: bool, item: ?FacebookQueueItem, media_id: ?string}
     */
    public function processNextDue(): array
    {
        $this->lastError = null;

        $this->recoverStaleProcessingItems();

        if (FacebookQueueItem::query()->where('status', FacebookQueueItem::STATUS_PROCESSING)->exists()) {
            return ['processed' => false, 'item' => null, 'media_id' => null];
        }

        $hasManualActive = FacebookQueueItem::query()
            ->where(function ($query): void {
                $query->where('queue_source', SocialMediaQueueSource::MANUAL)
                    ->orWhereNull('queue_source');
            })
            ->whereIn('status', [
                FacebookQueueItem::STATUS_PENDING,
                FacebookQueueItem::STATUS_PROCESSING,
            ])
            ->exists();

        $pendingQuery = FacebookQueueItem::query()
            ->where('status', FacebookQueueItem::STATUS_PENDING)
            ->where('scheduled_at', '<=', now());

        if ($hasManualActive) {
            $pendingQuery->where(function ($query): void {
                $query->where('queue_source', SocialMediaQueueSource::MANUAL)
                    ->orWhereNull('queue_source');
            });
        }

        /** @var FacebookQueueItem|null $item */
        $item = $pendingQuery
            ->orderBy('scheduled_at')
            ->orderBy('sort_order')
            ->first();

        if (! $item) {
            return ['processed' => false, 'item' => null, 'media_id' => null];
        }

        $item->update(['status' => FacebookQueueItem::STATUS_PROCESSING]);

        try {
            @set_time_limit(300);

            $mediaId = $this->publishQueueItem($item);

            $item->update([
                'status' => FacebookQueueItem::STATUS_COMPLETED,
                'facebook_post_id' => $mediaId,
                'processed_at' => now(),
                'error_message' => null,
            ]);

            $item = $item->fresh();
            if ($item instanceof FacebookQueueItem && filled($item->video_path)) {
                app(FacebookPostMediaService::class)->deleteStoredVideo($item);
            }

            return ['processed' => true, 'item' => $item, 'media_id' => $mediaId];
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $this->lastError = $message;

            $item->update([
                'status' => FacebookQueueItem::STATUS_FAILED,
                'processed_at' => now(),
                'error_message' => $message,
            ]);

            Log::warning('FacebookQueueService item failed — queue continues', [
                'queue_item_id' => $item->id,
                'error' => $message,
            ]);

            return ['processed' => true, 'item' => $item->fresh(), 'media_id' => null];
        }
    }

    public function publishQueueItem(FacebookQueueItem $item): string
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
                GeminiKeyScope::FACEBOOK,
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

        $item->loadMissing('facebookAccount');

        /** @var FacebookAccount|null $account */
        $account = $item->facebookAccount;
        if ($account === null || ! $account->isConfigured()) {
            $account = FacebookSettings::primaryAccount($item->user_id);
        }
        if ($account === null || ! $account->isConfigured()) {
            throw new \RuntimeException('Tài khoản Facebook không hợp lệ hoặc đã bị xóa.');
        }

        $media = app(FacebookPostMediaService::class);
        $graph = app(FacebookGraphService::class)->forAccount($account);

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
            $mediaUrl = $media->signedPublicUrl($item);
            if ($mediaUrl === null) {
                throw new \RuntimeException($media->lastError ?? 'Không tạo được URL media công khai.');
            }

            $urlError = $media->validatePublicImageUrl($mediaUrl);
            if ($urlError !== null) {
                throw new \RuntimeException($urlError);
            }

            $mediaId = $graph->publishPhoto($mediaUrl, (string) $caption);
        }

        if ($mediaId === null) {
            throw new \RuntimeException($graph->lastError ?? 'Không thể đăng lên Facebook.');
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
            'pending' => FacebookQueueItem::query()->where('status', FacebookQueueItem::STATUS_PENDING)->count(),
            'processing' => FacebookQueueItem::query()->where('status', FacebookQueueItem::STATUS_PROCESSING)->count(),
            'completed' => FacebookQueueItem::query()->where('status', FacebookQueueItem::STATUS_COMPLETED)->count(),
            'failed' => FacebookQueueItem::query()->where('status', FacebookQueueItem::STATUS_FAILED)->count(),
            'auto_pending' => FacebookQueueItem::query()
                ->where('queue_source', SocialMediaQueueSource::AUTO)
                ->where('status', FacebookQueueItem::STATUS_PENDING)
                ->count(),
            'manual_pending' => FacebookQueueItem::query()
                ->where(function ($query): void {
                    $query->where('queue_source', SocialMediaQueueSource::MANUAL)
                        ->orWhereNull('queue_source');
                })
                ->where('status', FacebookQueueItem::STATUS_PENDING)
                ->count(),
            'auto_processing' => FacebookQueueItem::query()
                ->where('queue_source', SocialMediaQueueSource::AUTO)
                ->where('status', FacebookQueueItem::STATUS_PROCESSING)
                ->count(),
            'manual_processing' => FacebookQueueItem::query()
                ->where(function ($query): void {
                    $query->where('queue_source', SocialMediaQueueSource::MANUAL)
                        ->orWhereNull('queue_source');
                })
                ->where('status', FacebookQueueItem::STATUS_PROCESSING)
                ->count(),
        ];
    }

    public function cancelPendingQueue(): int
    {
        $this->lastError = null;

        return FacebookQueueItem::query()
            ->where('status', FacebookQueueItem::STATUS_PENDING)
            ->delete();
    }

    public function hasPendingQueue(): bool
    {
        return FacebookQueueItem::query()
            ->where('status', FacebookQueueItem::STATUS_PENDING)
            ->exists();
    }
}
