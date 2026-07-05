<?php

namespace App\Services;

use App\Support\ApifySettings;
use App\Support\ApifyTokenRotator;
use App\Support\ApifyTikTokSharedVideo;
use App\Support\BrandDomain;
use App\Support\PublicStorage;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApifyTikTokService
{
    public ?string $lastError = null;

    /**
     * Tải video Apify 1 lần theo hashtag/user — các queue item cùng brand dùng chung file.
     */
    public function ensureSharedVideoForHashtag(?string $hashtag, ?int $userId, string $sharedAbsolute): bool
    {
        $this->lastError = null;
        $hashtag = $this->normalizeHashtag($hashtag);
        $userId = (int) ($userId ?? 0);

        if ($hashtag === '') {
            $this->lastError = 'Thiếu hashtag TikTok.';

            return false;
        }

        if ($this->isValidVideoFile($sharedAbsolute)) {
            return true;
        }

        $lock = Cache::lock(ApifyTikTokSharedVideo::lockKey($userId, $hashtag), 600);

        try {
            $lock->block(600);

            if ($this->isValidVideoFile($sharedAbsolute)) {
                return true;
            }

            return $this->downloadVideoToPath($hashtag, $userId, $sharedAbsolute);
        } catch (LockTimeoutException $e) {
            if ($this->isValidVideoFile($sharedAbsolute)) {
                return true;
            }

            $this->lastError = 'Đang chờ tải video TikTok từ Apify (timeout). Thử lại sau vài phút.';

            return false;
        } finally {
            optional($lock)->release();
        }
    }

    public function downloadFirstVideoForHashtag(?string $hashtag, ?int $userId, string $destAbsolute): bool
    {
        $userId = (int) ($userId ?? 0);
        $hashtag = $this->normalizeHashtag($hashtag);
        $sharedAbsolute = PublicStorage::absolutePath(ApifyTikTokSharedVideo::relativePath($userId, $hashtag));

        if (! $this->ensureSharedVideoForHashtag($hashtag, $userId, $sharedAbsolute)) {
            return false;
        }

        return $this->copySharedVideoTo($sharedAbsolute, $destAbsolute);
    }

    protected function downloadVideoToPath(string $hashtag, int $userId, string $destAbsolute): bool
    {
        $this->lastError = null;

        $tokenError = null;
        $items = ApifyTokenRotator::attempt(
            $userId,
            fn (string $token): array => $this->fetchVideoResultsForToken($token, $hashtag, $userId),
            $tokenError,
            'ApifyTikTok',
        );

        if (! is_array($items) || $items === []) {
            if ($tokenError !== null && $this->lastError === null) {
                $this->lastError = $tokenError;
            }

            return false;
        }

        $videoUrl = null;
        $selectedItem = null;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if ((bool) ($item['isSlideshow'] ?? false)) {
                continue;
            }

            $candidateUrl = $this->pickVideoDownloadUrl($item);
            if ($candidateUrl !== null) {
                $videoUrl = $candidateUrl;
                $selectedItem = $item;
                break;
            }
        }

        if ($videoUrl === null) {
            $this->lastError = 'Apify TikTok không trả về URL tải video hợp lệ.';
            Log::warning('ApifyTikTokService no video URL in items', [
                'hashtag' => $hashtag,
                'item_keys' => is_array($items[0] ?? null) ? array_keys($items[0]) : [],
            ]);

            return false;
        }

        $directory = dirname(str_replace('\\', '/', $destAbsolute));
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->lastError = 'Không tạo được thư mục lưu video Apify.';

            return false;
        }

        $downloader = app(SocialMediaVideoDownloadService::class);

        if (! $downloader->downloadToFile($videoUrl, $destAbsolute, $userId)) {
            $this->lastError = $downloader->lastError ?? 'Không tải được video TikTok.';

            return false;
        }

        if ($selectedItem !== null) {
            $coverUrl = $this->pickCoverUrl($selectedItem);
            $coverAbsolute = $this->coverPathBesideVideo($destAbsolute);

            if ($coverUrl !== null && $coverAbsolute !== null
                && ! $downloader->downloadImageToFile($coverUrl, $coverAbsolute, $userId)) {
                Log::info('ApifyTikTokService cover download skipped', [
                    'error' => $downloader->lastError,
                ]);
            }
        }

        Log::info('ApifyTikTokService shared video downloaded', [
            'hashtag' => $hashtag,
            'user_id' => $userId,
            'path' => $destAbsolute,
            'bytes' => is_file($destAbsolute) ? filesize($destAbsolute) : null,
        ]);

        return true;
    }

    protected function copySharedVideoTo(string $sharedAbsolute, string $destAbsolute): bool
    {
        if (! $this->isValidVideoFile($sharedAbsolute)) {
            $this->lastError = 'File video Apify dùng chung chưa sẵn sàng.';

            return false;
        }

        $directory = dirname(str_replace('\\', '/', $destAbsolute));
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->lastError = 'Không tạo được thư mục lưu video.';

            return false;
        }

        if ($this->isValidVideoFile($destAbsolute) && filesize($destAbsolute) === filesize($sharedAbsolute)) {
            return true;
        }

        if (is_file($destAbsolute)) {
            @unlink($destAbsolute);
        }

        if (! @copy($sharedAbsolute, $destAbsolute)) {
            $this->lastError = 'Không copy được video Apify sang file queue item.';

            return false;
        }

        @chmod($destAbsolute, 0644);

        $sharedCover = $this->coverPathBesideVideo($sharedAbsolute);
        $destCover = $this->coverPathBesideVideo($destAbsolute);

        if ($sharedCover !== null && $destCover !== null && is_file($sharedCover) && ! is_file($destCover)) {
            @copy($sharedCover, $destCover);
        }

        return true;
    }

    protected function isValidVideoFile(string $absolutePath): bool
    {
        if (! is_file($absolutePath)) {
            return false;
        }

        $size = filesize($absolutePath);

        return is_int($size) && $size > 10_000;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function pickCoverUrl(array $item): ?string
    {
        $candidates = [
            data_get($item, 'videoMeta.coverUrl'),
            data_get($item, 'videoMeta.originalCoverUrl'),
            data_get($item, 'videoMeta.dynamicCover'),
            data_get($item, 'video.cover'),
            data_get($item, 'video.originCover'),
            $item['coverUrl'] ?? null,
            $item['cover'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $url = trim((string) $candidate);
            if ($url !== '' && str_starts_with(strtolower($url), 'http')) {
                return $url;
            }
        }

        return null;
    }

    protected function coverPathBesideVideo(string $videoAbsolute): ?string
    {
        $videoAbsolute = str_replace('\\', '/', $videoAbsolute);

        if (! preg_match('/\.mp4$/i', $videoAbsolute)) {
            return null;
        }

        return preg_replace('/\.mp4$/i', '-cover.jpg', $videoAbsolute);
    }

    public function hashtagFromBrandDomain(?string $brandDomain): string
    {
        $name = BrandDomain::brandName($brandDomain);

        if ($name === null || $name === '') {
            return $this->defaultHashtag();
        }

        return $this->normalizeHashtag($name);
    }

    protected function defaultHashtag(): string
    {
        $tag = trim((string) config('apify.tiktok_default_hashtag', 'fyp'));

        return $this->normalizeHashtag($tag !== '' ? $tag : 'fyp');
    }

    protected function normalizeHashtag(?string $hashtag): string
    {
        $hashtag = ltrim(trim((string) $hashtag), '#');
        $hashtag = preg_replace('/[^a-zA-Z0-9_]/', '', $hashtag) ?? '';

        return strtolower($hashtag);
    }

    /**
     * @return array{value: mixed, token_failed: bool, error?: string}
     */
    protected function fetchVideoResultsForToken(string $token, string $hashtag, int $userId): array
    {
        $items = $this->fetchVideoResults($token, $hashtag, $userId);

        if ($items !== []) {
            return ApifyTokenRotator::result($items);
        }

        $error = $this->lastError ?? 'Apify TikTok không trả về video.';
        $httpStatus = $this->extractHttpStatusFromError($error);

        if (ApifySettings::shouldRotateToken($httpStatus, $error)) {
            return ApifyTokenRotator::result([], true, $error);
        }

        return ApifyTokenRotator::result($items);
    }

    protected function extractHttpStatusFromError(string $error): ?int
    {
        if (preg_match('/HTTP\s+(\d{3})/', $error, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchVideoResults(string $token, string $hashtag, int $userId): array
    {
        $cacheKey = ApifyTikTokSharedVideo::cacheKey($userId, $hashtag);
        $cacheSeconds = max(60, (int) config('apify.tiktok_results_cache_seconds', 3600));

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $lock = Cache::lock($cacheKey.':fetch', 300);

        try {
            $lock->block(300);

            $cached = Cache::get($cacheKey);
            if (is_array($cached) && $cached !== []) {
                return $cached;
            }

            $items = $this->fetchVideoResultsFromApi($token, $hashtag);
            if ($items !== []) {
                Cache::put($cacheKey, $items, $cacheSeconds);
            }

            return $items;
        } catch (LockTimeoutException $e) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && $cached !== []) {
                return $cached;
            }

            $this->lastError = 'Đang chờ Apify TikTok API (timeout).';

            return [];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchVideoResultsFromApi(string $token, string $hashtag): array
    {
        $actorId = (string) config('apify.tiktok_actor_id', 'GdWCkxBtKWOsKjdch');
        $waitSeconds = max(30, (int) config('apify.run_wait_seconds', 180));

        $url = sprintf(
            'https://api.apify.com/v2/acts/%s/run-sync-get-dataset-items?token=%s&waitForFinish=%d',
            rawurlencode($actorId),
            rawurlencode($token),
            $waitSeconds,
        );

        try {
            $response = Http::timeout($waitSeconds + 60)
                ->acceptJson()
                ->post($url, $this->actorInput($hashtag));
        } catch (\Throwable $e) {
            $this->lastError = 'Apify TikTok timeout/lỗi mạng: '.$e->getMessage();
            Log::warning('ApifyTikTokService request failed', [
                'hashtag' => $hashtag,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            $this->lastError = 'Apify TikTok HTTP '.$response->status().': '.$response->body();
            Log::warning('ApifyTikTokService HTTP error', [
                'hashtag' => $hashtag,
                'status' => $response->status(),
            ]);

            return [];
        }

        $items = $response->json();
        if (! is_array($items)) {
            $this->lastError = 'Apify TikTok trả về dữ liệu không hợp lệ.';

            return [];
        }

        $normalized = collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values()
            ->all();

        if ($normalized === []) {
            $this->lastError = 'Apify TikTok không tìm thấy video cho #'.$hashtag.'.';
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    protected function actorInput(string $hashtag): array
    {
        $downloadVideos = (bool) config('apify.tiktok_download_videos', true);

        return [
            'hashtags' => [$hashtag],
            'resultsPerPage' => max(1, min(10, (int) config('apify.tiktok_results_per_page', 1))),
            'profileScrapeSections' => ['videos'],
            'profileSorting' => 'latest',
            'excludePinnedPosts' => false,
            'maxFollowersPerProfile' => 0,
            'maxFollowingPerProfile' => 0,
            'searchSection' => '',
            'maxProfilesPerQuery' => 10,
            'videoSearchSorting' => 'MOST_RELEVANT',
            'videoSearchDateFilter' => 'ALL_TIME',
            'scrapeRelatedVideos' => false,
            'shouldDownloadVideos' => $downloadVideos,
            'shouldDownloadCovers' => false,
            'shouldDownloadSlideshowImages' => false,
            'shouldDownloadAvatars' => false,
            'shouldDownloadMusicCovers' => false,
            'downloadSubtitlesOptions' => 'NEVER_DOWNLOAD_SUBTITLES',
            'commentsPerPost' => 0,
            'topLevelCommentsPerPost' => 0,
            'maxRepliesPerComment' => 0,
            'proxyCountryCode' => 'US',
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function pickVideoDownloadUrl(array $item): ?string
    {
        $candidates = [
            data_get($item, 'videoMeta.downloadAddr'),
            data_get($item, 'videoMeta.originalDownloadAddr'),
            data_get($item, 'videoMeta.playAddr'),
            data_get($item, 'video.downloadAddr'),
            data_get($item, 'video.playAddr'),
            $item['videoDownloadNoWatermarkUrl'] ?? null,
            $item['videoDownloadUrl'] ?? null,
            $item['downloadUrl'] ?? null,
            $item['videoPlayUrl'] ?? null,
            $item['playUrl'] ?? null,
            $item['downloadedVideoUrl'] ?? null,
        ];

        foreach ($this->asList($item['mediaUrls'] ?? null) as $mediaUrl) {
            $candidates[] = $mediaUrl;
        }

        foreach ($this->asList(data_get($item, 'videoMeta.subtitleLinks')) as $subtitleLink) {
            if (! is_array($subtitleLink)) {
                continue;
            }

            $candidates[] = $subtitleLink['downloadLink'] ?? null;
            $candidates[] = $subtitleLink['tiktokLink'] ?? null;
        }

        foreach ($candidates as $candidate) {
            if ($this->isDownloadableVideoUrl($candidate)) {
                return trim((string) $candidate);
            }
        }

        return $this->findVideoUrlRecursive($item, 0);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function findVideoUrlRecursive(array $data, int $depth = 0): ?string
    {
        if ($depth > 32) {
            return null;
        }

        foreach ($data as $value) {
            if (is_string($value) && $this->isDownloadableVideoUrl($value)) {
                return trim($value);
            }

            if (is_array($value)) {
                $found = $this->findVideoUrlRecursive($value, $depth + 1);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    protected function isDownloadableVideoUrl(mixed $url): bool
    {
        $url = trim((string) $url);
        if ($url === '' || ! str_starts_with(strtolower($url), 'http')) {
            return false;
        }

        $lower = strtolower($url);

        if (str_contains($lower, 'api.apify.com') && str_contains($lower, '.mp4')) {
            return true;
        }

        if (str_contains($lower, 'tiktok.com/@') && ! str_contains($lower, 'tiktokcdn')) {
            return false;
        }

        if (str_contains($lower, 'mime_type=video_mp4') || str_contains($lower, 'mime_type=video%2fmp4')) {
            return true;
        }

        if (str_contains($lower, 'tiktokcdn') && str_contains($lower, '/video/')) {
            return true;
        }

        return str_ends_with($lower, '.mp4') || str_contains($lower, '.mp4?');
    }

    /**
     * @return array<int, mixed>
     */
    protected function asList(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
