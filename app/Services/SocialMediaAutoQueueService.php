<?php

namespace App\Services;

use App\Models\FacebookAccount;
use App\Models\FacebookQueueItem;
use App\Models\InstagramAccount;
use App\Models\InstagramQueueItem;
use App\Models\PinterestAccount;
use App\Models\PinterestQueueItem;
use App\Models\User;
use App\Support\FacebookSettings;
use App\Support\InstagramSettings;
use App\Support\PinterestSettings;
use App\Support\SocialMediaQueueSource;
use App\Support\SocialMediaRecordNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SocialMediaAutoQueueService
{
    public const PLATFORM_INSTAGRAM = 'instagram';

    public const PLATFORM_FACEBOOK = 'facebook';

    public const PLATFORM_PINTEREST = 'pinterest';

    public ?string $lastError = null;

    public function tickAll(): int
    {
        $total = 0;

        foreach ([self::PLATFORM_INSTAGRAM, self::PLATFORM_FACEBOOK, self::PLATFORM_PINTEREST] as $platform) {
            $total += $this->tickPlatform($platform);
        }

        return $total;
    }

    public function tickPlatform(string $platform): int
    {
        $enqueued = 0;

        foreach ($this->candidateUserIds($platform) as $userId) {
            if ($this->tickUserPlatform($platform, $userId)) {
                $enqueued++;
            }
        }

        return $enqueued;
    }

    public function tickUserPlatform(string $platform, int $userId): bool
    {
        $this->lastError = null;

        if (! $this->isAutoEnabled($platform, $userId)) {
            return false;
        }

        if (! $this->isPlatformConfigured($platform, $userId)) {
            return false;
        }

        if ($this->hasManualActiveQueue($platform, $userId)) {
            return false;
        }

        if ($this->hasAutoActiveQueue($platform, $userId)) {
            return false;
        }

        if (! $this->intervalElapsed($platform, $userId)) {
            return false;
        }

        $pool = $this->buildRecordPool($platform, $userId);

        if ($pool->isEmpty()) {
            $this->lastError = 'Chưa có bài «Hoàn thành» trong bảng Hàng đợi để lấy nội dung auto.';

            return false;
        }

        $state = $this->autoQueueState($platform, $userId);
        $selection = $this->pickNextRecord($pool, $state);

        if ($selection === null) {
            return false;
        }

        $record = $selection['record'];
        $recordIndex = $selection['index'];
        $enqueueRecord = $this->recordForEnqueue($record);

        $user = User::query()->find($userId);
        $batchId = match ($platform) {
            self::PLATFORM_FACEBOOK => $this->enqueueFacebook($user, $enqueueRecord, $state),
            self::PLATFORM_PINTEREST => $this->enqueuePinterest($user, $enqueueRecord, $state),
            default => $this->enqueueInstagram($user, $enqueueRecord, $state),
        };

        if ($batchId === null) {
            return false;
        }

        $this->saveAutoQueueState($platform, $userId, [
            'record_index' => $recordIndex + 1,
            'last_brand_key' => (string) ($record['_brand_key'] ?? ''),
            'account_index' => ((int) ($state['account_index'] ?? 0)) + 1,
        ]);

        Log::info('SocialMediaAutoQueueService enqueued auto post', [
            'platform' => $platform,
            'user_id' => $userId,
            'batch_id' => $batchId,
            'record_index' => $recordIndex,
            'brand_key' => $record['_brand_key'] ?? null,
            'source_queue_id' => $record['_source_queue_id'] ?? null,
        ]);

        return true;
    }

    public function hasManualActiveQueue(string $platform, int $userId): bool
    {
        return $this->queueQuery($platform, $userId)
            ->where(function ($query): void {
                $query->where('queue_source', SocialMediaQueueSource::MANUAL)
                    ->orWhereNull('queue_source');
            })
            ->whereIn('status', $this->activeStatuses())
            ->exists();
    }

    public function hasAutoActiveQueue(string $platform, int $userId): bool
    {
        return $this->queueQuery($platform, $userId)
            ->where('queue_source', SocialMediaQueueSource::AUTO)
            ->whereIn('status', $this->activeStatuses())
            ->exists();
    }

    public function isAutoPaused(string $platform, int $userId): bool
    {
        return $this->isAutoEnabled($platform, $userId)
            && $this->hasManualActiveQueue($platform, $userId);
    }

    /**
     * @return array<int, int>
     */
    protected function candidateUserIds(string $platform): array
    {
        $queueUserIds = $this->queueQuery($platform)
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        $accountOwnerIds = match ($platform) {
            self::PLATFORM_FACEBOOK => FacebookAccount::query()->distinct()->pluck('owner_user_id'),
            self::PLATFORM_PINTEREST => PinterestAccount::query()->distinct()->pluck('owner_user_id'),
            default => InstagramAccount::query()->distinct()->pluck('owner_user_id'),
        };

        return User::query()
            ->whereIn('id', $queueUserIds->merge($accountOwnerIds)->unique()->filter())
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    protected function isAutoEnabled(string $platform, int $userId): bool
    {
        return match ($platform) {
            self::PLATFORM_FACEBOOK => FacebookSettings::isAutoQueueEnabled($userId),
            self::PLATFORM_PINTEREST => PinterestSettings::isAutoQueueEnabled($userId),
            default => InstagramSettings::isAutoQueueEnabled($userId),
        };
    }

    protected function isPlatformConfigured(string $platform, int $userId): bool
    {
        return match ($platform) {
            self::PLATFORM_FACEBOOK => FacebookSettings::isConfigured($userId),
            self::PLATFORM_PINTEREST => PinterestSettings::isConfigured($userId),
            default => InstagramSettings::isConfigured($userId),
        };
    }

    protected function intervalElapsed(string $platform, int $userId): bool
    {
        $interval = match ($platform) {
            self::PLATFORM_FACEBOOK => FacebookSettings::autoQueueIntervalMinutes($userId),
            self::PLATFORM_PINTEREST => PinterestSettings::autoQueueIntervalMinutes($userId),
            default => InstagramSettings::autoQueueIntervalMinutes($userId),
        };

        /** @var InstagramQueueItem|FacebookQueueItem|PinterestQueueItem|null $last */
        $last = $this->queueQuery($platform, $userId)
            ->where('queue_source', SocialMediaQueueSource::AUTO)
            ->whereIn('status', [
                InstagramQueueItem::STATUS_COMPLETED,
                InstagramQueueItem::STATUS_FAILED,
            ])
            ->orderByDesc('processed_at')
            ->first();

        if ($last === null || $last->processed_at === null) {
            return true;
        }

        return $last->processed_at->copy()->addMinutes($interval)->lte(now());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildRecordPool(string $platform, int $userId): Collection
    {
        return $this->recordsFromCompletedQueueTable($platform, $userId);
    }

    /**
     * Lấy bài «Hoàn thành» trong bảng Hàng đợi, record cũ nhất trước.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function recordsFromCompletedQueueTable(string $platform, int $userId): Collection
    {
        return $this->queueQuery($platform, $userId)
            ->where('status', InstagramQueueItem::STATUS_COMPLETED)
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get()
            ->map(function (InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $item): array {
                $record = SocialMediaRecordNormalizer::forQueue([
                    'brand_domain' => $item->brand_domain,
                    'content_idea' => $item->content_idea,
                    'aff_link' => $item->aff_link,
                    'coupon_codes' => $item->coupon_codes,
                    'image' => $item->image_path,
                    'video' => $item->video_path,
                ]);

                return [
                    ...$record,
                    '_source_queue_id' => $item->id,
                    '_brand_key' => $this->brandKey($item->brand_domain),
                ];
            })
            ->filter(fn (array $record): bool => $this->recordHasContent($record))
            ->values();
    }

    /**
     * Chọn bài tiếp theo: xoay vòng từ record cũ nhất, mỗi lần phải khác brand lần trước.
     *
     * @param  Collection<int, array<string, mixed>>  $pool
     * @param  array<string, mixed>  $state
     * @return array{record: array<string, mixed>, index: int}|null
     */
    protected function pickNextRecord(Collection $pool, array $state): ?array
    {
        if ($pool->isEmpty()) {
            return null;
        }

        $startIndex = ((int) ($state['record_index'] ?? 0)) % $pool->count();
        $lastBrandKey = (string) ($state['last_brand_key'] ?? '');

        for ($offset = 0; $offset < $pool->count(); $offset++) {
            $index = ($startIndex + $offset) % $pool->count();
            /** @var array<string, mixed>|null $candidate */
            $candidate = $pool->get($index);

            if (! is_array($candidate)) {
                continue;
            }

            $brandKey = (string) ($candidate['_brand_key'] ?? '');

            if ($pool->count() > 1 && $brandKey !== '' && $brandKey === $lastBrandKey) {
                continue;
            }

            return [
                'record' => $candidate,
                'index' => $index,
            ];
        }

        /** @var array<string, mixed>|null $fallback */
        $fallback = $pool->get($startIndex);

        if (! is_array($fallback)) {
            return null;
        }

        return [
            'record' => $fallback,
            'index' => $startIndex,
        ];
    }

    protected function brandKey(?string $brandDomain): string
    {
        $value = strtolower(trim((string) $brandDomain));

        if ($value === '') {
            return '';
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            return $host;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function recordForEnqueue(array $record): array
    {
        return collect($record)
            ->except(['_source_queue_id', '_brand_key'])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function recordHasContent(array $record): bool
    {
        if (filled($record['image'] ?? null) || filled($record['video'] ?? null)) {
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

        return is_array($coupons)
            && collect($coupons)->filter(fn (mixed $code): bool => filled($code))->isNotEmpty();
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $state
     */
    protected function enqueueInstagram(?User $user, array $record, array $state): ?string
    {
        $userId = $user?->id;
        $accounts = InstagramAccount::query()
            ->where('owner_user_id', \App\Support\IntegrationSettingsStore::for($userId)->userId())
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (InstagramAccount $account): bool => $account->isConfigured())
            ->values();

        if ($accounts->isEmpty()) {
            $this->lastError = 'Không có tài khoản Instagram hợp lệ cho hàng đợi auto.';

            return null;
        }

        $accountIndex = ((int) ($state['account_index'] ?? 0)) % $accounts->count();
        $account = $accounts->get($accountIndex);

        if (! $account instanceof InstagramAccount) {
            return null;
        }

        return app(InstagramQueueService::class)->enqueue(
            [$record],
            $user,
            now(),
            [$account->id],
            SocialMediaQueueSource::AUTO,
        );
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $state
     */
    protected function enqueueFacebook(?User $user, array $record, array $state): ?string
    {
        $userId = $user?->id;
        $accounts = FacebookAccount::query()
            ->where('owner_user_id', \App\Support\IntegrationSettingsStore::for($userId)->userId())
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (FacebookAccount $account): bool => $account->isConfigured())
            ->values();

        if ($accounts->isEmpty()) {
            $this->lastError = 'Không có trang Facebook hợp lệ cho hàng đợi auto.';

            return null;
        }

        $accountIndex = ((int) ($state['account_index'] ?? 0)) % $accounts->count();
        $account = $accounts->get($accountIndex);

        if (! $account instanceof FacebookAccount) {
            return null;
        }

        return app(FacebookQueueService::class)->enqueue(
            [$record],
            $user,
            now(),
            [$account->id],
            SocialMediaQueueSource::AUTO,
        );
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $state
     */
    protected function enqueuePinterest(?User $user, array $record, array $state): ?string
    {
        $userId = $user?->id;
        $targets = PinterestSettings::autoQueueTargets($userId);

        if ($targets === []) {
            $this->lastError = 'Chưa cấu hình Board Pinterest cho hàng đợi auto.';

            return null;
        }

        $targetIndex = ((int) ($state['account_index'] ?? 0)) % count($targets);
        $target = $targets[$targetIndex] ?? $targets[0];

        return app(PinterestQueueService::class)->enqueue(
            [$record],
            $user,
            now(),
            [$target],
            SocialMediaQueueSource::AUTO,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function autoQueueState(string $platform, int $userId): array
    {
        return match ($platform) {
            self::PLATFORM_FACEBOOK => FacebookSettings::autoQueueState($userId),
            self::PLATFORM_PINTEREST => PinterestSettings::autoQueueState($userId),
            default => InstagramSettings::autoQueueState($userId),
        };
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function saveAutoQueueState(string $platform, int $userId, array $state): void
    {
        $current = $this->autoQueueState($platform, $userId);

        match ($platform) {
            self::PLATFORM_FACEBOOK => FacebookSettings::setAutoQueueState($userId, array_merge($current, $state)),
            self::PLATFORM_PINTEREST => PinterestSettings::setAutoQueueState($userId, array_merge($current, $state)),
            default => InstagramSettings::setAutoQueueState($userId, array_merge($current, $state)),
        };
    }

    /**
     * @return array<int, string>
     */
    protected function activeStatuses(): array
    {
        return [
            InstagramQueueItem::STATUS_PENDING,
            InstagramQueueItem::STATUS_PROCESSING,
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<InstagramQueueItem|FacebookQueueItem|PinterestQueueItem>
     */
    protected function queueQuery(string $platform, ?int $userId = null)
    {
        $query = match ($platform) {
            self::PLATFORM_FACEBOOK => FacebookQueueItem::query(),
            self::PLATFORM_PINTEREST => PinterestQueueItem::query(),
            default => InstagramQueueItem::query(),
        };

        if ($userId === null) {
            return $query;
        }

        $accountIds = match ($platform) {
            self::PLATFORM_FACEBOOK => FacebookAccount::query()->where('owner_user_id', $userId)->pluck('id'),
            self::PLATFORM_PINTEREST => PinterestAccount::query()->where('owner_user_id', $userId)->pluck('id'),
            default => InstagramAccount::query()->where('owner_user_id', $userId)->pluck('id'),
        };

        return $query->where(function ($inner) use ($userId, $platform, $accountIds): void {
            $inner->where('user_id', $userId);

            $foreignKey = match ($platform) {
                self::PLATFORM_FACEBOOK => 'facebook_account_id',
                self::PLATFORM_PINTEREST => 'pinterest_account_id',
                default => 'instagram_account_id',
            };

            if ($accountIds->isNotEmpty()) {
                $inner->orWhereIn($foreignKey, $accountIds);
            }
        });
    }
}
