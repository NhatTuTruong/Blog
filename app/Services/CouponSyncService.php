<?php

namespace App\Services;

use App\Models\FacebookAccount;
use App\Models\InstagramAccount;
use App\Models\User;
use App\Support\CouponSyncDomainFilter;
use App\Support\CouponSyncPlatforms;
use App\Support\CouponSyncSettings;
use App\Support\IntegrationSettingsStore;
use App\Support\SocialMediaMediaType;
use App\Support\SocialMediaQueueSource;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CouponSyncService
{
    public ?string $lastError = null;

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<int, string>  $platforms
     * @return array<string, mixed>
     */
    public function sync(array $records, array $platforms = []): array
    {
        $this->lastError = null;

        $platforms = $platforms !== [] ? $platforms : CouponSyncPlatforms::defaultsFromConfig();

        $user = $this->resolveOwnerUser();
        $userId = $user?->id;
        $syncId = (string) Str::uuid();

        $results = [
            'sync_id' => $syncId,
            'platforms' => $platforms,
            'items_count' => count($records),
            'items' => collect($records)->map(fn (array $record): array => [
                'brand_domain' => $record['brand_domain'] ?? null,
                'media_type' => $record['media_type'] ?? SocialMediaMediaType::IMAGE,
            ])->values()->all(),
            'dedupe_domain_enabled' => CouponSyncSettings::dedupeDomainEnabled(),
            'blog' => $this->emptyPlatformResult(),
            'instagram' => $this->emptyPlatformResult(),
            'facebook' => $this->emptyPlatformResult(),
        ];

        if ($user === null) {
            $ownerError = 'Không tìm thấy tài khoản sở hữu hàng đợi. Cấu hình COUPON_SYNC_USER_ID trong .env.';
            foreach (['blog', 'instagram', 'facebook'] as $platform) {
                if ($this->platformRequested($platforms, $platform)) {
                    $results[$platform]['error'] = $ownerError;
                } else {
                    $results[$platform] = $this->skippedPlatformResult(
                        'Không chọn nền tảng '.CouponSyncPlatforms::label($platform).'.',
                    );
                }
            }
            $this->lastError = $ownerError;

            return $results;
        }

        if ($this->platformRequested($platforms, CouponSyncPlatforms::BLOG)) {
            if (config('coupon_sync.enqueue_blog', true)) {
                $results['blog'] = $this->enqueueBlog($records, $user, $userId);
            } else {
                $results['blog']['error'] = 'Hàng đợi blog bị tắt trong cấu hình.';
            }
        } else {
            $results['blog'] = $this->skippedPlatformResult('Không chọn nền tảng blog trong platforms.');
        }

        $socialStart = $this->socialStartAt(
            $this->platformRequested($platforms, CouponSyncPlatforms::BLOG)
            && ($results['blog']['enqueued'] ?? false),
        );

        if ($this->platformRequested($platforms, CouponSyncPlatforms::INSTAGRAM)) {
            if (config('coupon_sync.enqueue_instagram', true)) {
                $results['instagram'] = $this->enqueueInstagram($records, $user, $userId, $socialStart);
            } else {
                $results['instagram']['error'] = 'Hàng đợi Instagram bị tắt trong cấu hình.';
            }
        } else {
            $results['instagram'] = $this->skippedPlatformResult('Không chọn nền tảng Instagram trong platforms.');
        }

        if ($this->platformRequested($platforms, CouponSyncPlatforms::FACEBOOK)) {
            if (config('coupon_sync.enqueue_facebook', true)) {
                $results['facebook'] = $this->enqueueFacebook($records, $user, $userId, $socialStart);
            } else {
                $results['facebook']['error'] = 'Hàng đợi Facebook bị tắt trong cấu hình.';
            }
        } else {
            $results['facebook'] = $this->skippedPlatformResult('Không chọn nền tảng Facebook trong platforms.');
        }

        $anyEnqueued = $results['blog']['enqueued']
            || $results['instagram']['enqueued']
            || $results['facebook']['enqueued'];

        $allSkippedByDedupe = CouponSyncSettings::dedupeDomainEnabled()
            && ! $anyEnqueued
            && $records !== []
            && $this->allRecordsSkippedAcrossPlatforms($results, count($records), $platforms);

        if (! $anyEnqueued && ! $allSkippedByDedupe) {
            $this->lastError = $this->buildFailureMessage($results)
                ?? 'Không xếp hàng được nền tảng nào. Kiểm tra cấu hình Gemini / Instagram / Facebook.';
        }

        $results['all_skipped_duplicate'] = $allSkippedByDedupe;

        return $results;
    }

    /**
     * @param  array<string, mixed>  $results
     * @return array<string, string>
     */
    public function platformErrors(array $results): array
    {
        $errors = [];

        foreach (['blog', 'instagram', 'facebook'] as $key) {
            if (($results[$key]['skipped'] ?? false) === true) {
                continue;
            }

            $error = trim((string) ($results[$key]['error'] ?? ''));

            if ($error !== '') {
                $errors[$key] = $error;
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    protected function enqueueBlog(array $records, ?User $user, ?int $userId): array
    {
        $result = $this->emptyPlatformResult();

        [$filtered, $skipped] = CouponSyncDomainFilter::filterRecords(
            $records,
            CouponSyncDomainFilter::PLATFORM_BLOG,
            $userId,
        );

        $result['skipped_domains'] = $skipped;
        $result['enqueued_count'] = count($filtered);

        if ($filtered === []) {
            $result['error'] = $skipped !== []
                ? 'Tất cả domain đã có trong hàng đợi blog (chỉ bỏ qua khi đang chờ/đang xử lý/hoàn thành).'
                : 'Không có bản ghi hợp lệ.';

            return $result;
        }

        $blogService = app(AutoBlogQueueService::class);
        $batchId = $blogService->enqueue($filtered, $user, now());

        if ($batchId !== null) {
            $result['enqueued'] = true;
            $result['batch_id'] = $batchId;
        } else {
            $result['error'] = $blogService->lastError ?? 'Không thể xếp hàng blog.';
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    protected function enqueueInstagram(array $records, ?User $user, ?int $userId, Carbon $startAt): array
    {
        $result = $this->emptyPlatformResult();

        $accountId = InstagramAccount::firstEnabledConfiguredId($userId);
        if ($accountId === null) {
            $result['error'] = 'Chưa có tài khoản Instagram hợp lệ (cần ít nhất một tài khoản bật và cấu hình đúng).';

            return $result;
        }

        $result['account_id'] = $accountId;

        [$filtered, $skipped] = CouponSyncDomainFilter::filterRecords(
            $records,
            CouponSyncDomainFilter::PLATFORM_INSTAGRAM,
            $userId,
        );

        $result['skipped_domains'] = $skipped;
        $result['enqueued_count'] = count($filtered);

        if ($filtered === []) {
            $result['error'] = $skipped !== []
                ? 'Tất cả domain đã có trong hàng đợi Instagram.'
                : 'Không có bản ghi hợp lệ.';

            return $result;
        }

        $igService = app(InstagramQueueService::class);
        $batchId = $igService->enqueue(
            $this->socialRecords($filtered),
            $user,
            $startAt,
            [$accountId],
            SocialMediaQueueSource::COUPON_SYNC,
        );

        if ($batchId !== null) {
            $result['enqueued'] = true;
            $result['batch_id'] = $batchId;
        } else {
            $result['error'] = $igService->lastError ?? 'Không thể xếp hàng Instagram.';
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    protected function enqueueFacebook(array $records, ?User $user, ?int $userId, Carbon $startAt): array
    {
        $result = $this->emptyPlatformResult();

        $accountId = FacebookAccount::firstEnabledConfiguredId($userId);
        if ($accountId === null) {
            $result['error'] = 'Chưa có tài khoản Facebook hợp lệ (cần ít nhất một tài khoản bật và cấu hình đúng).';

            return $result;
        }

        $result['account_id'] = $accountId;

        [$filtered, $skipped] = CouponSyncDomainFilter::filterRecords(
            $records,
            CouponSyncDomainFilter::PLATFORM_FACEBOOK,
            $userId,
        );

        $result['skipped_domains'] = $skipped;
        $result['enqueued_count'] = count($filtered);

        if ($filtered === []) {
            $result['error'] = $skipped !== []
                ? 'Tất cả domain đã có trong hàng đợi Facebook.'
                : 'Không có bản ghi hợp lệ.';

            return $result;
        }

        $fbService = app(FacebookQueueService::class);
        $batchId = $fbService->enqueue(
            $this->socialRecords($filtered),
            $user,
            $startAt,
            [$accountId],
            SocialMediaQueueSource::COUPON_SYNC,
        );

        if ($batchId !== null) {
            $result['enqueued'] = true;
            $result['batch_id'] = $batchId;
        } else {
            $result['error'] = $fbService->lastError ?? 'Không thể xếp hàng Facebook.';
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyPlatformResult(): array
    {
        return [
            'enqueued' => false,
            'batch_id' => null,
            'error' => null,
            'skipped' => false,
            'skip_reason' => null,
            'account_id' => null,
            'skipped_domains' => [],
            'enqueued_count' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function skippedPlatformResult(string $reason): array
    {
        return array_merge($this->emptyPlatformResult(), [
            'skipped' => true,
            'skip_reason' => $reason,
        ]);
    }

    /**
     * @param  array<int, string>  $platforms
     */
    protected function platformRequested(array $platforms, string $platform): bool
    {
        return in_array($platform, $platforms, true);
    }

    /**
     * @param  array<string, mixed>  $results
     * @param  array<int, string>  $requestedPlatforms
     */
    protected function allRecordsSkippedAcrossPlatforms(array $results, int $inputCount, array $requestedPlatforms): bool
    {
        if ($inputCount === 0) {
            return false;
        }

        $skippedUnion = collect();
        $anyAttempted = false;

        foreach ($requestedPlatforms as $platform) {
            if (($results[$platform]['skipped'] ?? false) === true) {
                continue;
            }

            $anyAttempted = true;
            $skippedUnion = $skippedUnion->merge($results[$platform]['skipped_domains'] ?? []);
        }

        if (! $anyAttempted) {
            return false;
        }

        return $skippedUnion->isNotEmpty()
            && collect($requestedPlatforms)->every(
                fn (string $platform): bool => ($results[$platform]['skipped'] ?? false) === true
                    || ($results[$platform]['enqueued_count'] ?? 0) === 0,
            );
    }

    protected function resolveOwnerUser(): ?User
    {
        $userId = config('coupon_sync.user_id');

        if ($userId !== null) {
            return User::query()->find((int) $userId);
        }

        $fallbackId = IntegrationSettingsStore::fallbackAdminUserId();

        return $fallbackId !== null ? User::query()->find($fallbackId) : null;
    }

    protected function socialStartAt(bool $blogEnqueued): Carbon
    {
        $delay = max(0, (int) config('coupon_sync.social_delay_minutes', 0));

        if ($blogEnqueued && $delay > 0) {
            return now()->addMinutes($delay);
        }

        return now();
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    protected function socialRecords(array $records): array
    {
        return collect($records)
            ->map(fn (array $record): array => [
                'brand_domain' => $record['brand_domain'] ?? null,
                'content_idea' => $record['content_idea'] ?? null,
                'aff_link' => $record['aff_link'] ?? null,
                'coupon_codes' => $record['coupon_codes'] ?? [],
                'media_type' => SocialMediaMediaType::normalize($record['media_type'] ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $results
     */
    protected function buildFailureMessage(array $results): ?string
    {
        $errors = $this->platformErrors($results);

        if ($errors === []) {
            return null;
        }

        return collect($errors)
            ->map(fn (string $message, string $platform): string => match ($platform) {
                'blog' => 'Blog: '.$message,
                'instagram' => 'Instagram: '.$message,
                'facebook' => 'Facebook: '.$message,
                default => $message,
            })
            ->implode(' | ');
    }
}
