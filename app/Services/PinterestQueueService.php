<?php

namespace App\Services;

use App\Models\PinterestAccount;
use App\Models\PinterestQueueItem;
use App\Models\User;
use App\Support\AdminSettings;
use App\Support\GeminiKeyScope;
use App\Support\PinterestSettings;
use App\Support\SocialMediaQueueSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PinterestQueueService
{
    public ?string $lastError = null;

    public function intervalMinutes(?int $userId = null): int
    {
        return PinterestSettings::queueIntervalMinutes($userId);
    }

    public function hasActiveQueue(): bool
    {
        return PinterestQueueItem::query()
            ->whereIn('status', [
                PinterestQueueItem::STATUS_PENDING,
                PinterestQueueItem::STATUS_PROCESSING,
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
        return PinterestQueueItem::query()
            ->where('status', PinterestQueueItem::STATUS_PROCESSING)
            ->exists();
    }

    public function releaseStuckProcessingItems(bool $force = false): int
    {
        $query = PinterestQueueItem::query()
            ->where('status', PinterestQueueItem::STATUS_PROCESSING);

        if (! $force) {
            $query->where('updated_at', '<', now()->subMinutes($this->staleProcessingMinutes()));
        }

        $staleItems = $query->get();

        if ($staleItems->isEmpty()) {
            return 0;
        }

        foreach ($staleItems as $item) {
            $item->update([
                'status' => PinterestQueueItem::STATUS_PENDING,
                'processed_at' => null,
                'error_message' => null,
            ]);
        }

        Log::warning('PinterestQueueService released stuck processing items', [
            'force' => $force,
            'count' => $staleItems->count(),
            'queue_item_ids' => $staleItems->pluck('id')->all(),
        ]);

        return $staleItems->count();
    }

    public function recoverStaleProcessingItems(): int
    {
        return $this->releaseStuckProcessingItems(force: false);
    }

    public function abortQueueOnError(string $reason): int
    {
        $cancelled = $this->cancelPendingQueue();

        if ($cancelled > 0) {
            Log::warning('PinterestQueueService aborted queue after error', [
                'reason' => $reason,
                'cancelled_pending' => $cancelled,
            ]);
        }

        return $cancelled;
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<int, array{account_id: int, board_id: string, board_name?: string|null}>  $targets
     */
    public function enqueue(
        array $records,
        ?User $user = null,
        ?Carbon $startAt = null,
        array $targets = [],
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

        $configurationError = PinterestSettings::configurationErrorMessage($ownerUserId);
        if ($configurationError !== null) {
            $this->lastError = $configurationError;

            return null;
        }

        $targets = collect($targets)
            ->map(function (mixed $target): ?array {
                if (! is_array($target)) {
                    return null;
                }

                $accountId = (int) ($target['account_id'] ?? 0);
                $boardId = trim((string) ($target['board_id'] ?? ''));

                if ($accountId <= 0 || $boardId === '') {
                    return null;
                }

                return [
                    'account_id' => $accountId,
                    'board_id' => $boardId,
                    'board_name' => filled($target['board_name'] ?? null) ? trim((string) $target['board_name']) : null,
                ];
            })
            ->filter()
            ->unique(fn (array $target): string => $target['account_id'].':'.$target['board_id'])
            ->values()
            ->all();

        if ($targets === []) {
            $this->lastError = 'Chưa chọn Board Pinterest để đăng Pin.';

            return null;
        }

        $accountIds = collect($targets)->pluck('account_id')->unique()->values()->all();
        $accounts = PinterestAccount::query()
            ->where('owner_user_id', \App\Support\IntegrationSettingsStore::for($ownerUserId)->userId())
            ->whereIn('id', $accountIds)
            ->where('enabled', true)
            ->get()
            ->filter(fn (PinterestAccount $account): bool => $account->isConfigured())
            ->keyBy('id');

        if ($accounts->isEmpty()) {
            $this->lastError = 'Tài khoản Pinterest không hợp lệ hoặc chưa có token.';

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

            foreach ($targets as $target) {
                $account = $accounts->get($target['account_id']);
                if ($account === null) {
                    continue;
                }

                PinterestQueueItem::query()->create([
                    'batch_id' => $batchId,
                    'queue_source' => $queueSource,
                    'user_id' => $user?->id,
                    'pinterest_account_id' => $account->id,
                    'board_id' => $target['board_id'],
                    'board_name' => $target['board_name'],
                    'sort_order' => $queueIndex,
                    'brand_domain' => $brandDomain,
                    'content_idea' => $contentIdea,
                    'aff_link' => $affLink,
                    'coupon_codes' => $couponCodes !== [] ? $couponCodes : null,
                    'image_path' => filled($record['image'] ?? null) ? trim((string) $record['image']) : null,
                    'video_path' => filled($record['video'] ?? null) ? trim((string) $record['video']) : null,
                    'caption' => null,
                    'used_default_caption' => false,
                    'status' => PinterestQueueItem::STATUS_PENDING,
                    'scheduled_at' => $baseTime->copy()->addMinutes($queueIndex * $interval),
                ]);

                $queueIndex++;
            }
        }

        return $batchId;
    }

    /**
     * @return array{processed: bool, item: ?PinterestQueueItem, media_id: ?string}
     */
    public function processNextDue(): array
    {
        $this->lastError = null;

        $this->recoverStaleProcessingItems();

        if (PinterestQueueItem::query()->where('status', PinterestQueueItem::STATUS_PROCESSING)->exists()) {
            return ['processed' => false, 'item' => null, 'media_id' => null];
        }

        $hasManualActive = PinterestQueueItem::query()
            ->where(function ($query): void {
                $query->where('queue_source', SocialMediaQueueSource::MANUAL)
                    ->orWhereNull('queue_source');
            })
            ->whereIn('status', [
                PinterestQueueItem::STATUS_PENDING,
                PinterestQueueItem::STATUS_PROCESSING,
            ])
            ->exists();

        $pendingQuery = PinterestQueueItem::query()
            ->where('status', PinterestQueueItem::STATUS_PENDING)
            ->where('scheduled_at', '<=', now());

        if ($hasManualActive) {
            $pendingQuery->where(function ($query): void {
                $query->where('queue_source', SocialMediaQueueSource::MANUAL)
                    ->orWhereNull('queue_source');
            });
        }

        /** @var PinterestQueueItem|null $item */
        $item = $pendingQuery
            ->orderBy('scheduled_at')
            ->orderBy('sort_order')
            ->first();

        if (! $item) {
            return ['processed' => false, 'item' => null, 'media_id' => null];
        }

        $item->update(['status' => PinterestQueueItem::STATUS_PROCESSING]);

        try {
            @set_time_limit(300);

            $mediaId = $this->publishQueueItem($item);

            $item->update([
                'status' => PinterestQueueItem::STATUS_COMPLETED,
                'pinterest_pin_id' => $mediaId,
                'processed_at' => now(),
                'error_message' => null,
            ]);

            $item = $item->fresh();
            if ($item instanceof PinterestQueueItem && filled($item->video_path)) {
                app(PinterestPostMediaService::class)->deleteStoredVideo($item);
            }

            return ['processed' => true, 'item' => $item, 'media_id' => $mediaId];
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $this->lastError = $message;

            $item->update([
                'status' => PinterestQueueItem::STATUS_FAILED,
                'processed_at' => now(),
                'error_message' => $message,
            ]);

            Log::warning('PinterestQueueService item failed — queue continues', [
                'queue_item_id' => $item->id,
                'error' => $message,
            ]);

            return ['processed' => true, 'item' => $item->fresh(), 'media_id' => null];
        }
    }

    public function publishQueueItem(PinterestQueueItem $item): string
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
                GeminiKeyScope::PINTEREST,
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

        $item->loadMissing('pinterestAccount');

        /** @var PinterestAccount|null $account */
        $account = $item->pinterestAccount;
        if ($account === null || ! $account->isConfigured()) {
            $account = PinterestSettings::primaryAccount($item->user_id);
        }
        if ($account === null || ! $account->isConfigured()) {
            throw new \RuntimeException('Tài khoản Pinterest không hợp lệ hoặc đã bị xóa.');
        }

        $boardId = trim((string) ($item->board_id ?? ''));
        if ($boardId === '') {
            $boardId = trim((string) ($account->board_id ?? ''));
        }
        if ($boardId === '') {
            throw new \RuntimeException('Thiếu Board Pinterest cho bài trong hàng đợi.');
        }

        $media = app(PinterestPostMediaService::class);
        $api = app(PinterestApiService::class)->forAccount($account)->forBoard($boardId);
        $title = filled($item->brand_domain) ? (string) $item->brand_domain : Str::before((string) $caption, "\n");
        $description = (string) $caption;
        $link = filled($item->aff_link) ? (string) $item->aff_link : null;

        $imageUrl = $media->signedPublicImageUrl($item);
        if ($imageUrl === null) {
            throw new \RuntimeException($media->lastError ?? 'Không tạo được URL ảnh công khai.');
        }

        $urlError = $media->validatePublicImageUrl($imageUrl);
        if ($urlError !== null) {
            throw new \RuntimeException($urlError);
        }

        if (filled($item->video_path)) {
            $videoPath = $media->resolveMediaAbsolutePath($item);
            $pinId = $api->publishVideoPin($videoPath, $imageUrl, $title, $description, $link);
        } else {
            $pinId = $api->publishImagePin($imageUrl, $title, $description, $link);
        }

        if ($pinId === null) {
            throw new \RuntimeException($api->lastError ?? 'Không thể đăng Pin lên Pinterest.');
        }

        return $pinId;
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
            'pending' => PinterestQueueItem::query()->where('status', PinterestQueueItem::STATUS_PENDING)->count(),
            'processing' => PinterestQueueItem::query()->where('status', PinterestQueueItem::STATUS_PROCESSING)->count(),
            'completed' => PinterestQueueItem::query()->where('status', PinterestQueueItem::STATUS_COMPLETED)->count(),
            'failed' => PinterestQueueItem::query()->where('status', PinterestQueueItem::STATUS_FAILED)->count(),
            'auto_pending' => PinterestQueueItem::query()
                ->where('queue_source', SocialMediaQueueSource::AUTO)
                ->where('status', PinterestQueueItem::STATUS_PENDING)
                ->count(),
            'manual_pending' => PinterestQueueItem::query()
                ->where(function ($query): void {
                    $query->where('queue_source', SocialMediaQueueSource::MANUAL)
                        ->orWhereNull('queue_source');
                })
                ->where('status', PinterestQueueItem::STATUS_PENDING)
                ->count(),
            'auto_processing' => PinterestQueueItem::query()
                ->where('queue_source', SocialMediaQueueSource::AUTO)
                ->where('status', PinterestQueueItem::STATUS_PROCESSING)
                ->count(),
            'manual_processing' => PinterestQueueItem::query()
                ->where(function ($query): void {
                    $query->where('queue_source', SocialMediaQueueSource::MANUAL)
                        ->orWhereNull('queue_source');
                })
                ->where('status', PinterestQueueItem::STATUS_PROCESSING)
                ->count(),
        ];
    }

    public function cancelPendingQueue(): int
    {
        $this->lastError = null;

        return PinterestQueueItem::query()
            ->where('status', PinterestQueueItem::STATUS_PENDING)
            ->delete();
    }

    public function hasPendingQueue(): bool
    {
        return PinterestQueueItem::query()
            ->where('status', PinterestQueueItem::STATUS_PENDING)
            ->exists();
    }
}
