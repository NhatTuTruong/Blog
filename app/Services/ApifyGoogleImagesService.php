<?php

namespace App\Services;

use App\Support\ApifySettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApifyGoogleImagesService
{
    public ?string $lastError = null;

    public function downloadLargestImageForQuery(?string $query, ?int $userId, string $destAbsolute): bool
    {
        $this->lastError = null;
        $query = trim((string) $query);

        if ($query === '') {
            $this->lastError = 'Thiếu query tìm ảnh.';

            return false;
        }

        $token = ApifySettings::apiToken($userId);
        if ($token === null) {
            $this->lastError = 'Chưa cấu hình Apify API token.';

            return false;
        }

        $items = $this->fetchImageResults($token, $query);
        if ($items === []) {
            return false;
        }

        $imageUrl = $this->pickLargestImageUrl($items);
        if ($imageUrl === null) {
            $this->lastError = 'Apify không trả về URL ảnh hợp lệ.';

            return false;
        }

        return app(SocialMediaImageSourceService::class)->downloadRemoteImageAsJpeg($imageUrl, $destAbsolute);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchImageResults(string $token, string $query): array
    {
        $actorId = (string) config('apify.google_images_actor_id', 'tnudF2IxzORPhg4r8');
        $waitSeconds = max(30, (int) config('apify.run_wait_seconds', 180));
        $maxResults = max(1, min(10, (int) config('apify.max_results_per_query', 3)));

        $url = sprintf(
            'https://api.apify.com/v2/acts/%s/run-sync-get-dataset-items?token=%s&waitForFinish=%d',
            rawurlencode($actorId),
            rawurlencode($token),
            $waitSeconds,
        );

        try {
            $response = Http::timeout($waitSeconds + 30)
                ->acceptJson()
                ->post($url, [
                    'queries' => [$query],
                    'maxResultsPerQuery' => $maxResults,
                ]);
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
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function pickLargestImageUrl(array $items): ?string
    {
        $bestUrl = null;
        $bestArea = -1;

        foreach ($items as $item) {
            $url = trim((string) ($item['imageUrl'] ?? $item['image_url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $width = (int) ($item['imageWidth'] ?? $item['image_width'] ?? 0);
            $height = (int) ($item['imageHeight'] ?? $item['image_height'] ?? 0);
            $area = $width > 0 && $height > 0
                ? $width * $height
                : max($width, $height, 1);

            if ($area > $bestArea) {
                $bestArea = $area;
                $bestUrl = $url;
            }
        }

        return $bestUrl;
    }
}
