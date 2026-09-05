<?php

namespace App\Services;

use App\Support\ApifyImageOrientation;
use App\Support\ApifyTokenRotator;
use App\Support\PublicStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AutoBlogContentImageService
{
    protected const MIN_INLINE_IMAGES = 2;

    protected const TARGET_INLINE_IMAGES = 3;

    protected const URL_CANDIDATE_LIMIT = 12;

    protected ?string $lastError = null;

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @return array{featured_image: ?string, content: string}
     */
    public function enrichBlogWithApifyImages(
        string $content,
        ?string $brandDomain,
        ?int $userId,
        int $queueItemId,
        ?string $uploadedFeaturedPath = null,
    ): array {
        $this->lastError = null;

        $featuredImage = $uploadedFeaturedPath;
        $imageUrls = $this->fetchBestImageUrls($brandDomain, $userId);

        if ($imageUrls === []) {
            Log::info('AutoBlogContentImageService: No Apify images available', [
                'queue_item_id' => $queueItemId,
                'brand_domain' => $brandDomain,
                'error' => $this->lastError,
            ]);

            return [
                'featured_image' => $featuredImage,
                'content' => $content,
            ];
        }

        $downloader = app(SocialMediaImageSourceService::class);
        $inlineHtml = [];
        $inlineIndex = 0;
        $needFeatured = $featuredImage === null;

        foreach ($imageUrls as $url) {
            if ($needFeatured) {
                $storagePath = $this->getFeaturedStoragePath($queueItemId);
                $savedPath = $this->downloadAndValidate($downloader, $url, $storagePath, $queueItemId, 'featured');
                if ($savedPath !== null) {
                    $featuredImage = $savedPath;
                    $needFeatured = false;

                    continue;
                }
            }

            if (count($inlineHtml) >= self::TARGET_INLINE_IMAGES) {
                continue;
            }

            $storagePath = $this->getInlineStoragePath($queueItemId, $inlineIndex);
            $savedPath = $this->downloadAndValidate($downloader, $url, $storagePath, $queueItemId, 'inline', $inlineIndex);
            if ($savedPath !== null) {
                $inlineHtml[] = $this->buildImageHtml($savedPath, $inlineIndex);
                $inlineIndex++;
            }

            if (! $needFeatured && count($inlineHtml) >= self::TARGET_INLINE_IMAGES) {
                break;
            }
        }

        if (count($inlineHtml) >= self::MIN_INLINE_IMAGES) {
            $content = $this->insertImagesIntoHtmlContent($content, $inlineHtml);
        } elseif ($inlineHtml !== []) {
            Log::warning('AutoBlogContentImageService: Fewer inline images than minimum', [
                'queue_item_id' => $queueItemId,
                'inline_count' => count($inlineHtml),
                'minimum' => self::MIN_INLINE_IMAGES,
            ]);
            $content = $this->insertImagesIntoHtmlContent($content, $inlineHtml);
        } else {
            Log::info('AutoBlogContentImageService: Apify returned URLs but no inline images downloaded', [
                'queue_item_id' => $queueItemId,
                'candidate_count' => count($imageUrls),
            ]);
        }

        return [
            'featured_image' => $featuredImage,
            'content' => $content,
        ];
    }

    /**
     * @deprecated Use enrichBlogWithApifyImages()
     */
    public function insertImagesIntoContent(
        string $content,
        ?string $brandDomain,
        ?int $userId,
        int $queueItemId,
    ): string {
        return $this->enrichBlogWithApifyImages($content, $brandDomain, $userId, $queueItemId)['content'];
    }

    /**
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

        $apify = app(ApifyGoogleImagesService::class);
        $urls = $apify->pickBestImageUrls($items, ApifyImageOrientation::LANDSCAPE, strict: true)
            ?: ($apify->pickBestImageUrls($items, ApifyImageOrientation::LANDSCAPE, strict: false) ?? []);

        return array_slice($urls, 0, self::URL_CANDIDATE_LIMIT);
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

    protected function downloadAndValidate(
        SocialMediaImageSourceService $downloader,
        string $imageUrl,
        string $storagePath,
        int $queueItemId,
        string $role,
        ?int $inlineIndex = null,
    ): ?string {
        $absolutePath = PublicStorage::absolutePath($storagePath);
        PublicStorage::ensureDirectory(dirname(str_replace('\\', '/', $storagePath)));

        if (! $downloader->downloadRemoteImageAsWebp($imageUrl, $absolutePath)) {
            Log::info('AutoBlogContentImageService: Failed to download image', [
                'queue_item_id' => $queueItemId,
                'role' => $role,
                'inline_index' => $inlineIndex,
                'url' => $imageUrl,
            ]);

            return null;
        }

        $savedPath = $this->resolveSavedStoragePath($storagePath);

        if ($savedPath === null) {
            return null;
        }

        Log::info('AutoBlogContentImageService: Downloaded image', [
            'queue_item_id' => $queueItemId,
            'role' => $role,
            'inline_index' => $inlineIndex,
            'path' => $savedPath,
            'url' => $imageUrl,
        ]);

        return $savedPath;
    }

    protected function resolveSavedStoragePath(string $storagePath): ?string
    {
        if (PublicStorage::exists($storagePath)) {
            return $storagePath;
        }

        $jpegPath = preg_replace('/\.webp$/i', '.jpg', $storagePath);
        if (is_string($jpegPath) && $jpegPath !== '' && PublicStorage::exists($jpegPath)) {
            return $jpegPath;
        }

        return null;
    }

    protected function getFeaturedStoragePath(int $queueItemId): string
    {
        return "blogs/auto-generated/item-{$queueItemId}.webp";
    }

    protected function getInlineStoragePath(int $queueItemId, int $index): string
    {
        return "blogs/auto-generated/content-{$queueItemId}-{$index}.webp";
    }

    protected function buildImageHtml(string $storagePath, int $index): string
    {
        $url = PublicStorage::url($storagePath);
        $alt = 'Blog image '.($index + 1);

        return sprintf(
            '<figure class="blog-inline-image"><img src="%s" alt="%s" loading="lazy" /></figure>',
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($alt, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * @param  array<int, string>  $imageHtmlArray
     */
    protected function insertImagesIntoHtmlContent(string $content, array $imageHtmlArray): string
    {
        if ($imageHtmlArray === []) {
            return $content;
        }

        if (! preg_match_all('/<\/(?:p|h[2-4]|ul|ol|blockquote|figure)>/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return $content.implode('', $imageHtmlArray);
        }

        $anchors = $matches[0];
        $anchorCount = count($anchors);

        if ($anchorCount < 2) {
            return $this->appendImagesEvenly($content, $imageHtmlArray);
        }

        $imageCount = count($imageHtmlArray);
        $anchorIndices = $this->calculateAnchorIndices($anchorCount, $imageCount);
        $insertions = [];

        foreach ($anchorIndices as $imageIndex => $anchorIndex) {
            $anchor = $anchors[$anchorIndex];
            $offset = $anchor[1] + strlen($anchor[0]);
            $insertions[$offset][] = $imageHtmlArray[$imageIndex];
        }

        krsort($insertions, SORT_NUMERIC);

        foreach ($insertions as $offset => $htmlChunks) {
            $content = substr($content, 0, $offset)
                .implode('', $htmlChunks)
                .substr($content, $offset);
        }

        return $content;
    }

    /**
     * @param  array<int, string>  $imageHtmlArray
     */
    protected function appendImagesEvenly(string $content, array $imageHtmlArray): string
    {
        $chunks = array_chunk($imageHtmlArray, max(1, (int) ceil(count($imageHtmlArray) / 2)));

        return $content.implode('', $chunks[0] ?? []).($chunks[1][0] ?? '');
    }

    /**
     * @return array<int, int>
     */
    protected function calculateAnchorIndices(int $anchorCount, int $imageCount): array
    {
        if ($anchorCount <= 0 || $imageCount <= 0) {
            return [];
        }

        $indices = [];
        $used = [];

        for ($i = 0; $i < $imageCount; $i++) {
            $index = (int) round(($anchorCount / ($imageCount + 1)) * ($i + 1)) - 1;
            $index = max(0, min($anchorCount - 1, $index));

            while (in_array($index, $used, true) && $index < $anchorCount - 1) {
                $index++;
            }

            if (in_array($index, $used, true)) {
                $index = max(0, $index - 1);
                while (in_array($index, $used, true) && $index > 0) {
                    $index--;
                }
            }

            $used[] = $index;
            $indices[$i] = $index;
        }

        return $indices;
    }
}
