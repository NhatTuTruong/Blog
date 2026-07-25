<?php

namespace App\Services;

use App\Support\ApifyImageOrientation;
use App\Support\ApifyTokenRotator;
use App\Support\ApifySettings;
use App\Support\PublicStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AutoBlogContentImageService
{
    protected const MAX_IMAGES = 2;
    protected const IMAGE_PARAGRAPH_SEPARATOR = '<p>&nbsp;</p>';

    protected ?string $lastError = null;

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Insert quality images into blog content.
     * Uses the best images from Apify (different from featured image).
     * Returns modified content or original if images cannot be fetched.
     */
    public function insertImagesIntoContent(
        string $content,
        ?string $brandDomain,
        ?int $userId,
        int $queueItemId,
    ): string {
        $this->lastError = null;

        // Get image URLs from Apify using "best" mode
        $imageUrls = $this->fetchBestImageUrls($brandDomain, $userId);
        if ($imageUrls === []) {
            Log::info('AutoBlogContentImageService: No images from Apify, skipping inline images', [
                'queue_item_id' => $queueItemId,
                'brand_domain' => $brandDomain,
                'error' => $this->lastError,
            ]);

            return $content;
        }

        // Limit to MAX_IMAGES
        $imageUrls = array_slice($imageUrls, 0, self::MAX_IMAGES);

        // Download images and build HTML
        $imageHtmlArray = [];
        foreach ($imageUrls as $index => $imageUrl) {
            $storagePath = $this->getStoragePath($queueItemId, $index);
            $absolutePath = PublicStorage::absolutePath($storagePath);

            PublicStorage::ensureDirectory(dirname(str_replace('\\', '/', $storagePath)));

            $downloader = app(SocialMediaImageSourceService::class);
            if ($downloader->downloadRemoteImageAsJpeg($imageUrl, $absolutePath)) {
                // Check if it's a valid image (not blocked placeholder)
                if (! $downloader->isBlockedPlaceholderImage($absolutePath)) {
                    $imageHtmlArray[] = $this->buildImageHtml($storagePath, $index);
                    Log::info('AutoBlogContentImageService: Downloaded inline image', [
                        'queue_item_id' => $queueItemId,
                        'image_index' => $index,
                        'url' => $imageUrl,
                    ]);
                } else {
                    @unlink($absolutePath);
                    Log::info('AutoBlogContentImageService: Skipped blocked placeholder image', [
                        'queue_item_id' => $queueItemId,
                        'url' => $imageUrl,
                    ]);
                }
            } else {
                Log::info('AutoBlogContentImageService: Failed to download image', [
                    'queue_item_id' => $queueItemId,
                    'url' => $imageUrl,
                ]);
            }
        }

        if ($imageHtmlArray === []) {
            return $content;
        }

        // Insert images into content
        return $this->insertImagesIntoHtmlContent($content, $imageHtmlArray);
    }

    /**
     * Fetch best quality images from Apify (not random).
     * @return array<int, string>
     */
    protected function fetchBestImageUrls(?string $brandDomain, ?int $userId): array
    {
        $query = app(SocialMediaImageSourceService::class)->buildSearchQuery($brandDomain);
        if ($query === null) {
            $this->lastError = 'No search query for brand domain.';

            return [];
        }

        $tokenError = null;
        $items = ApifyTokenRotator::attempt(
            $userId,
            fn (string $token): array => $this->fetchImageResults($token, $query),
            $tokenError,
            'ApifyGoogleImages',
        );

        if (! is_array($items) || $items === []) {
            $this->lastError = $tokenError ?? 'Apify returned no images.';

            return [];
        }

        // Pick best images (sorted by quality score)
        $apify = app(ApifyGoogleImagesService::class);
        return $apify->pickBestImageUrls($items, ApifyImageOrientation::LANDSCAPE, strict: true)
            ?: ($apify->pickBestImageUrls($items, ApifyImageOrientation::LANDSCAPE, strict: false) ?? []);
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
            Log::warning('AutoBlogContentImageService: Apify request failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            $this->lastError = 'Apify HTTP '.$response->status().': '.$response->body();

            return [];
        }

        $items = $response->json();
        if (! is_array($items)) {
            $this->lastError = 'Apify trả về dữ liệu không hợp lệ.';

            return [];
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values()
            ->all();
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
            'num' => '10',
            'max_pages' => 1,
            'date_range' => 'anytime',
        ];
    }

    protected function getStoragePath(int $queueItemId, int $index): string
    {
        return "blogs/auto-generated/content-{$queueItemId}-{$index}.jpg";
    }

    protected function buildImageHtml(string $storagePath, int $index): string
    {
        $url = PublicStorage::url($storagePath);
        $alt = "Blog image " . ($index + 1);

        return sprintf(
            '<figure class="wp-block-image"><img src="%s" alt="%s" loading="lazy" /></figure>',
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($alt, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Insert images into HTML content, spread across the content.
     */
    protected function insertImagesIntoHtmlContent(string $content, array $imageHtmlArray): string
    {
        // Split content by paragraphs
        $parts = preg_split('/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (count($parts) < 3) {
            // Content too short, append images at the end
            return $content . self::IMAGE_PARAGRAPH_SEPARATOR . implode(self::IMAGE_PARAGRAPH_SEPARATOR, $imageHtmlArray);
        }

        // Calculate insertion points
        $totalSegments = count($parts) - 1;
        $insertionPoints = $this->calculateInsertionPoints($totalSegments, count($imageHtmlArray));

        // Build a map of insertion points to images
        $insertedImages = [];
        foreach (array_values($insertionPoints) as $i => $point) {
            if ($i < count($imageHtmlArray)) {
                $insertedImages[$point] = $imageHtmlArray[$i];
            }
        }

        // Build new content
        $newContent = '';
        $currentPoint = 0;

        for ($i = 0; $i < count($parts); $i++) {
            $newContent .= $parts[$i];

            // After each </p>, check if we should insert an image here
            if ($i % 2 === 1 && isset($insertedImages[$currentPoint])) {
                $newContent .= self::IMAGE_PARAGRAPH_SEPARATOR . $insertedImages[$currentPoint];
            }

            if ($i % 2 === 1) {
                $currentPoint++;
            }
        }

        return $newContent;
    }

    /**
     * Calculate optimal paragraph indices to insert images.
     */
    protected function calculateInsertionPoints(int $totalParagraphs, int $imageCount): array
    {
        if ($totalParagraphs <= 0 || $imageCount <= 0) {
            return [];
        }

        $points = [];
        $step = $totalParagraphs / ($imageCount + 1);

        for ($i = 0; $i < $imageCount; $i++) {
            $point = (int) round($step * ($i + 1));
            $points[] = max(0, min($point, $totalParagraphs - 1));
        }

        return $points;
    }
}
