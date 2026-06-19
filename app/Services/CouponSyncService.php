<?php



namespace App\Services;



use App\Models\User;

use App\Support\CouponSyncDomainFilter;

use App\Support\CouponSyncSettings;

use App\Support\IntegrationSettingsStore;

use App\Support\SocialMediaQueueSource;

use Carbon\Carbon;

use Illuminate\Support\Str;



class CouponSyncService

{

    public ?string $lastError = null;



    /**

     * @param  array<int, array<string, mixed>>  $records

     * @return array<string, mixed>

     */

    public function sync(array $records): array

    {

        $this->lastError = null;



        $user = $this->resolveOwnerUser();

        $userId = $user?->id;

        $syncId = (string) Str::uuid();



        $results = [

            'sync_id' => $syncId,

            'items_count' => count($records),

            'dedupe_domain_enabled' => CouponSyncSettings::dedupeDomainEnabled(),

            'blog' => $this->emptyPlatformResult(),

            'instagram' => $this->emptyPlatformResult(),

            'facebook' => $this->emptyPlatformResult(),

        ];



        if (config('coupon_sync.enqueue_blog', true)) {

            $results['blog'] = $this->enqueueBlog($records, $user, $userId);

        } else {

            $results['blog']['error'] = 'Blog queue disabled in config.';

        }



        $socialStart = $this->socialStartAt((bool) ($results['blog']['enqueued'] ?? false));



        if (config('coupon_sync.enqueue_instagram', true)) {

            $results['instagram'] = $this->enqueueInstagram($records, $user, $userId, $socialStart);

        } else {

            $results['instagram']['error'] = 'Instagram queue disabled in config.';

        }



        if (config('coupon_sync.enqueue_facebook', true)) {

            $results['facebook'] = $this->enqueueFacebook($records, $user, $userId, $socialStart);

        } else {

            $results['facebook']['error'] = 'Facebook queue disabled in config.';

        }



        $anyEnqueued = $results['blog']['enqueued']

            || $results['instagram']['enqueued']

            || $results['facebook']['enqueued'];



        $allSkippedByDedupe = CouponSyncSettings::dedupeDomainEnabled()

            && ! $anyEnqueued

            && $records !== []

            && $this->allRecordsSkippedAcrossPlatforms($results, count($records));



        if (! $anyEnqueued && ! $allSkippedByDedupe) {

            $this->lastError = 'Không xếp hàng được nền tảng nào. Kiểm tra cấu hình Gemini / Instagram / Facebook.';

        }



        $results['all_skipped_duplicate'] = $allSkippedByDedupe;



        return $results;

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

            [],

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

            [],

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

            'skipped_domains' => [],

            'enqueued_count' => 0,

        ];

    }



    /**

     * @param  array<string, mixed>  $results

     */

    protected function allRecordsSkippedAcrossPlatforms(array $results, int $inputCount): bool

    {

        if ($inputCount === 0) {

            return false;

        }



        $skippedUnion = collect([

            ...($results['blog']['skipped_domains'] ?? []),

            ...($results['instagram']['skipped_domains'] ?? []),

            ...($results['facebook']['skipped_domains'] ?? []),

        ])->unique();



        return $skippedUnion->isNotEmpty()

            && ($results['blog']['enqueued_count'] ?? 0) === 0

            && ($results['instagram']['enqueued_count'] ?? 0) === 0

            && ($results['facebook']['enqueued_count'] ?? 0) === 0;

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

            ])

            ->values()

            ->all();

    }

}


