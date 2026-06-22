<?php

namespace App\Services;

use App\Support\ApifySettings;
use App\Support\BrandDomain;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApifyTikTokService
{
    public ?string $lastError = null;

    public function downloadFirstVideoForHashtag(?string $hashtag, ?int $userId, string $destAbsolute): bool
    {
        $this->lastError = null;
        $hashtag = $this->normalizeHashtag($hashtag);

        if ($hashtag === '') {
            $this->lastError = 'Thiếu hashtag TikTok.';

            return false;
        }

        $token = ApifySettings::apiToken($userId);
        if ($token === null) {
            $this->lastError = 'Chưa cấu hình Apify API token.';

            return false;
        }

        $items = $this->fetchVideoResults($token, $hashtag);
        if ($items === []) {
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
                'video_meta_keys' => is_array(data_get($items[0], 'videoMeta')) ? array_keys(data_get($items[0], 'videoMeta')) : [],
            ]);

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

        return true;
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
     * @return array<int, array<string, mixed>>
     */
    protected function fetchVideoResults(string $token, string $hashtag): array
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
                'body' => $response->body(),
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

        return $this->findVideoUrlRecursive($item);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function findVideoUrlRecursive(array $data): ?string
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && $this->isDownloadableVideoUrl($value)) {
                return trim($value);
            }

            if (is_array($value)) {
                $found = $this->findVideoUrlRecursive($value);
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
