<?php

namespace App\Services;

use App\Support\ApifyImageOrientation;
use App\Support\ApifySettings;
use App\Support\ApifyTokenRotator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApifyGoogleImagesService
{
    public ?string $lastError = null;

    public function downloadLargestImageForQuery(
        ?string $query,
        ?int $userId,
        string $destAbsolute,
        string $orientation = ApifyImageOrientation::LANDSCAPE,
    ): bool {
        return $this->downloadBestImageForQuery($query, $userId, $destAbsolute, $orientation);
    }

    public function downloadBestImageForQuery(
        ?string $query,
        ?int $userId,
        string $destAbsolute,
        string $orientation = ApifyImageOrientation::LANDSCAPE,
    ): bool {
        return $this->downloadFromCandidates($query, $userId, $destAbsolute, $orientation, 'best');
    }

    /**
     * Download a random high-quality image from Apify results.
     * Picks from top candidates to ensure quality while providing variety.
     */
    public function downloadRandomQualityImageForQuery(
        ?string $query,
        ?int $userId,
        string $destAbsolute,
        string $orientation = ApifyImageOrientation::LANDSCAPE,
        int $topCandidates = 5,
    ): bool {
        return $this->downloadFromCandidates($query, $userId, $destAbsolute, $orientation, 'random', $topCandidates);
    }

    /**
     * Internal method to download image(s) from Apify candidates.
     */
    protected function downloadFromCandidates(
        ?string $query,
        ?int $userId,
        string $destAbsolute,
        string $orientation,
        string $mode = 'best',
        int $topCandidates = 5,
    ): bool {
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

        $imageUrls = $this->pickBestImageUrls($items, $orientation, strict: true);
        if ($imageUrls === []) {
            $imageUrls = $this->pickBestImageUrls($items, $orientation, strict: false);
        }

        if ($imageUrls === []) {
            $this->lastError = 'Apify không trả về URL ảnh hợp lệ.';

            return false;
        }

        // For random mode: pick from top N candidates, shuffled
        if ($mode === 'random' && count($imageUrls) > 1) {
            $shuffled = collect($imageUrls)->shuffle()->values()->all();
            $imageUrls = array_slice($shuffled, 0, min($topCandidates, count($shuffled)));
            // Re-shuffle so we pick a random one from top candidates
            $imageUrls = collect($imageUrls)->shuffle()->values()->all();
        }

        $downloader = app(SocialMediaImageSourceService::class);

        foreach ($imageUrls as $index => $imageUrl) {
            if ($downloader->downloadRemoteImageAsJpeg($imageUrl, $destAbsolute)) {
                Log::info('ApifyGoogleImagesService selected image', [
                    'query' => $query,
                    'orientation' => $orientation,
                    'mode' => $mode,
                    'candidate' => $index + 1,
                    'total_candidates' => count($imageUrls),
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
        $actorId = (string) config('apify.google_images_actor_id', 'MrbqFgdpNTQcRW0Vt');
        $waitSeconds = max(30, (int) config('apify.run_wait_seconds', 180));

        $url = sprintf(
            'https://api.apify.com/v2/acts/%s/run-sync-get-dataset-items?token=%s&waitForFinish=%d',
            rawurlencode($actorId),
            rawurlencode($token),
            $waitSeconds,
        );

        try {
            $response = Http::asJson()
                ->timeout($waitSeconds + 30)
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
        return [
            'query' => $query,
            'country' => config('apify.google_images.country', 'us'),
            'language' => config('apify.google_images.language', 'en'),
            'num' => '10',  // actor chỉ chấp nhận "10" hoặc "100"
            'max_pages' => 1,
            'date_range' => 'anytime',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, string>
     */
    public function pickBestImageUrls(array $items, string $orientation, bool $strict = true): array
    {
        $scored = [];

        foreach ($items as $item) {
            $meta = $this->extractImageMeta($item);
            if ($meta === null) {
                continue;
            }

            $score = $this->scoreImageCandidate(
                $meta['width'],
                $meta['height'],
                $orientation,
                $strict,
            );
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
        // New actor (IOrPh0bOfzJiGxsvk) output format: imageUrl, thumbnailUrl, title, link, googleUrl
        // Old actor (MrbqFgdpNTQcRW0Vt) output format: original, image_url, width, height
        $url = trim((string) (
            $item['imageUrl']      // new actor
            ?? $item['url']        // generic
            ?? $item['image_url']
            ?? $item['imageUrl']
            ?? $item['originalUrl']
            ?? $item['original_url']
            ?? $item['original']
            ?? $item['contentUrl']
            ?? $item['content_url']
            ?? ''
        ));

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        if ($this->isThumbnailUrl($url)) {
            return null;
        }

        // Try to extract width/height from URL query params (e.g., &width=1440&quality=75)
        $urlWidth = 0;
        $urlHeight = 0;
        if (preg_match('/[?&]width=(\d+)/i', $url, $wm)) {
            $urlWidth = (int) $wm[1];
        }
        if (preg_match('/[?&]height=(\d+)/i', $url, $hm)) {
            $urlHeight = (int) $hm[1];
        }

        $width = (int) (
            $item['width'] ?? 0
        ) ?: $urlWidth;
        $height = (int) (
            $item['height'] ?? 0
        ) ?: $urlHeight;

        return [
            'url' => $url,
            'width' => max(0, $width),
            'height' => max(0, $height),
        ];
    }

    protected function isThumbnailUrl(string $url): bool
    {
        $lower = strtolower($url);

        return str_contains($lower, 'encrypted-tbn0.gstatic.com')
            || str_contains($lower, 'gstatic.com/images?q=tbn');
    }

    protected function scoreImageCandidate(
        int $width,
        int $height,
        string $orientation,
        bool $strict = true,
    ): float {
        if ($width > 0 && $height > 0) {
            if ($width < 320 || $height < 320) {
                return 0.0;
            }

            $ratio = $width / $height;

            if ($orientation === ApifyImageOrientation::PORTRAIT_SQUARE) {
                if ($strict && $ratio > 1.05) {
                    return 0.0;
                }

                $area = $width * $height;
                $ratioScore = $this->portraitSquareAspectRatioScore($ratio);

                return ($area / 1_000_000) * 100 * $ratioScore;
            }

            if ($strict && $ratio < 1.05) {
                return 0.0;
            }

            $area = $width * $height;
            $ratioScore = $this->landscapeAspectRatioScore($ratio);

            return ($area / 1_000_000) * 100 * $ratioScore;
        }

        return $strict ? 0.0 : 0.5;
    }

    protected function portraitSquareAspectRatioScore(float $ratio): float
    {
        if ($ratio <= 0) {
            return 0.1;
        }

        if ($ratio >= 0.85 && $ratio <= 1.05) {
            return 1.0;
        }

        if ($ratio >= 0.65 && $ratio <= 1.15) {
            return 0.9;
        }

        if ($ratio >= 0.5 && $ratio <= 1.25) {
            return 0.75;
        }

        if ($ratio > 1.05) {
            return 0.25;
        }

        return 0.5;
    }

    protected function landscapeAspectRatioScore(float $ratio): float
    {
        if ($ratio <= 0) {
            return 0.1;
        }

        if ($ratio >= 1.4 && $ratio <= 2.0) {
            return 1.0;
        }

        if ($ratio >= 1.15 && $ratio <= 2.5) {
            return 0.85;
        }

        if ($ratio >= 1.05 && $ratio <= 3.0) {
            return 0.65;
        }

        if ($ratio < 1.05) {
            return 0.35;
        }

        return 0.4;
    }
}
