<?php

namespace App\Services;

use App\Support\ApifySettings;
use App\Support\ApifyTokenRotator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApifyGoogleImagesService
{
    public ?string $lastError = null;

    public function downloadLargestImageForQuery(?string $query, ?int $userId, string $destAbsolute): bool
    {
        return $this->downloadBestImageForQuery($query, $userId, $destAbsolute);
    }

    public function downloadBestImageForQuery(?string $query, ?int $userId, string $destAbsolute): bool
    {
        $this->lastError = null;
        $query = trim((string) $query);

        if ($query === '') {
            $this->lastError = 'Thiếu query tìm ảnh.';

            return false;
        }

        $tokenError = null;
        $items = ApifyTokenRotator::attempt(
            $userId,
            fn (string $token): array => $this->fetchImageResultsForToken($token, $query),
            $tokenError,
            'ApifyGoogleImages',
        );

        if (! is_array($items) || $items === []) {
            if ($tokenError !== null && $this->lastError === null) {
                $this->lastError = $tokenError;
            }

            return false;
        }

        $imageUrls = $this->pickBestImageUrls($items);
        if ($imageUrls === []) {
            $this->lastError = 'Apify không trả về URL ảnh hợp lệ.';

            return false;
        }

        $downloader = app(SocialMediaImageSourceService::class);

        foreach ($imageUrls as $index => $imageUrl) {
            if ($downloader->downloadRemoteImageAsJpeg($imageUrl, $destAbsolute)) {
                Log::info('ApifyGoogleImagesService selected image', [
                    'query' => $query,
                    'candidate' => $index + 1,
                    'url' => $imageUrl,
                ]);

                return true;
            }
        }

        $this->lastError = 'Không tải được ảnh phù hợp từ Apify (thử '.count($imageUrls).' ảnh).';

        return false;
    }

    /**
     * @return array{value: mixed, token_failed: bool, error?: string}
     */
    protected function fetchImageResultsForToken(string $token, string $query): array
    {
        $items = $this->fetchImageResults($token, $query);

        if ($items !== []) {
            return ApifyTokenRotator::result($items);
        }

        $error = $this->lastError ?? 'Apify không trả về ảnh.';
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
    protected function fetchImageResults(string $token, string $query): array
    {
        $actorId = (string) config('apify.google_images_actor_id', '1zP0mfnAf2xvIwvJu');
        $waitSeconds = max(30, (int) config('apify.run_wait_seconds', 180));

        $url = sprintf(
            'https://api.apify.com/v2/acts/%s/run-sync-get-dataset-items?token=%s&waitForFinish=%d',
            rawurlencode($actorId),
            rawurlencode($token),
            $waitSeconds,
        );

        try {
            $response = Http::timeout($waitSeconds + 30)
                ->acceptJson()
                ->post($url, $this->buildRunInput($query));
        } catch (\Throwable $e) {
            $this->lastError = 'Apify timeout/lỗi mạng: '.$e->getMessage();
            Log::warning('ApifyGoogleImagesService request failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            $this->lastError = 'Apify HTTP '.$response->status().': '.$response->body();
            Log::warning('ApifyGoogleImagesService HTTP error', [
                'query' => $query,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $items = $response->json();
        if (! is_array($items)) {
            $this->lastError = 'Apify trả về dữ liệu không hợp lệ.';

            return [];
        }

        $normalized = collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values()
            ->all();

        if ($normalized === []) {
            $this->lastError = 'Apify không tìm thấy ảnh cho «'.$query.'».';
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRunInput(string $query): array
    {
        $maxResults = max(1, min(10, (int) config('apify.max_results_per_query', 3)));

        return [
            'queries' => [$query],
            'searchUrls' => [],
            'maxResultsPerQuery' => $maxResults,
            'imageSize' => (string) config('apify.google_images.image_size', 'large'),
            'imageColor' => 'any',
            'imageType' => (string) config('apify.google_images.image_type', 'photo'),
            'aspectRatio' => 'any',
            'timePeriod' => 'anytime',
            'usageRights' => 'any',
            'safeSearch' => 'off',
            'language' => (string) config('apify.google_images.language', 'en'),
            'country' => (string) config('apify.google_images.country', 'us'),
            'includeRelatedQueries' => false,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, string>
     */
    protected function pickBestImageUrls(array $items): array
    {
        $scored = [];

        foreach ($items as $item) {
            $meta = $this->extractImageMeta($item);
            if ($meta === null) {
                continue;
            }

            $score = $this->scoreImageCandidate($meta['width'], $meta['height']);
            if ($score <= 0) {
                continue;
            }

            $scored[] = [
                'url' => $meta['url'],
                'score' => $score,
                'width' => $meta['width'],
                'height' => $meta['height'],
            ];
        }

        if ($scored === []) {
            return [];
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return collect($scored)
            ->pluck('url')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{url: string, width: int, height: int}|null
     */
    protected function extractImageMeta(array $item): ?array
    {
        $url = trim((string) (
            $item['imageUrl']
            ?? $item['image_url']
            ?? $item['originalUrl']
            ?? $item['original_url']
            ?? $item['url']
            ?? $item['contentUrl']
            ?? $item['content_url']
            ?? $item['link']
            ?? ''
        ));

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $width = (int) (
            $item['imageWidth']
            ?? $item['image_width']
            ?? $item['width']
            ?? ($item['image']['width'] ?? 0)
        );
        $height = (int) (
            $item['imageHeight']
            ?? $item['image_height']
            ?? $item['height']
            ?? ($item['image']['height'] ?? 0)
        );

        return [
            'url' => $url,
            'width' => max(0, $width),
            'height' => max(0, $height),
        ];
    }

    protected function scoreImageCandidate(int $width, int $height): float
    {
        if ($width > 0 && $height > 0) {
            if ($width < 320 || $height < 320) {
                return 0.0;
            }

            $area = $width * $height;
            $ratio = $width / $height;
            $ratioScore = $this->aspectRatioScore($ratio);

            return ($area / 1_000_000) * 100 * $ratioScore;
        }

        return 1.0;
    }

    protected function aspectRatioScore(float $ratio): float
    {
        if ($ratio <= 0) {
            return 0.1;
        }

        if ($ratio >= 0.75 && $ratio <= 1.91) {
            return 1.0;
        }

        if ($ratio >= 0.55 && $ratio <= 2.2) {
            return 0.85;
        }

        if ($ratio >= 0.4 && $ratio <= 3.0) {
            return 0.6;
        }

        return 0.25;
    }
}
