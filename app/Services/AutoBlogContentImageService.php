<?php

namespace App\Services;

use App\Support\ApifyImageOrientation;
use App\Support\ApifyTokenRotator;
use App\Support\BlogContentSanitizer;
use App\Support\PublicStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AutoBlogContentImageService
{
    protected const MIN_INLINE_IMAGES = 2;

    protected const TARGET_INLINE_IMAGES = 3;

    protected const URL_CANDIDATE_LIMIT = 20;

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
        $needFeaturedSlot = $featuredImage === null;
        $minDownloads = self::MIN_INLINE_IMAGES + ($needFeaturedSlot ? 1 : 0);
        $targetDownloads = self::TARGET_INLINE_IMAGES + ($needFeaturedSlot ? 1 : 0);

        $savedPaths = [];
        $attemptIndex = 0;

        foreach ($imageUrls as $url) {
            if (count($savedPaths) >= $targetDownloads) {
                break;
            }

            $storagePath = $this->getAttemptStoragePath($queueItemId, $attemptIndex);
            $savedPath = $this->downloadAndValidate($downloader, $url, $storagePath, $queueItemId, 'candidate', $attemptIndex);
            $attemptIndex++;

            if ($savedPath !== null) {
                $savedPaths[] = $savedPath;
            }
        }

        if ($needFeaturedSlot && $savedPaths !== []) {
            $featuredImage = array_shift($savedPaths);
        }

        $inlinePaths = array_slice($savedPaths, 0, self::TARGET_INLINE_IMAGES);
        $inlineHtml = [];

        foreach ($inlinePaths as $index => $path) {
            $inlineHtml[] = $this->buildImageHtml($path, $index);
        }

        if (count($inlineHtml) >= self::MIN_INLINE_IMAGES) {
            $content = $this->insertImagesIntoHtmlContent($content, $inlineHtml);
        } elseif ($inlineHtml !== []) {
            Log::warning('AutoBlogContentImageService: Fewer inline images than minimum', [
                'queue_item_id' => $queueItemId,
                'inline_count' => count($inlineHtml),
                'minimum' => self::MIN_INLINE_IMAGES,
                'downloaded_total' => count($savedPaths) + ($featuredImage !== null && $needFeaturedSlot ? 1 : 0),
            ]);
            $content = $this->insertImagesIntoHtmlContent($content, $inlineHtml);
        } else {
            Log::info('AutoBlogContentImageService: Apify returned URLs but no inline images downloaded', [
                'queue_item_id' => $queueItemId,
                'candidate_count' => count($imageUrls),
                'successful_downloads' => count($savedPaths),
                'needed_minimum' => $minDownloads,
            ]);
        }

        return [
            'featured_image' => $featuredImage,
            'content' => BlogContentSanitizer::sanitize($content),
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
        $urls = $apify->pickBestImageUrls($items, ApifyImageOrientation::LANDSCAPE, strict: true);

        if ($urls === []) {
            $urls = $apify->pickBestImageUrls($items, ApifyImageOrientation::LANDSCAPE, strict: false) ?? [];
        }

        if (count($urls) < self::MIN_INLINE_IMAGES + 1) {
            $portrait = $apify->pickBestImageUrls($items, ApifyImageOrientation::PORTRAIT_SQUARE, strict: false) ?? [];
            $urls = array_values(array_unique([...$urls, ...$portrait]));
        }

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

        $saved = false;

        if ($downloader->downloadRemoteImageAsWebp($imageUrl, $absolutePath)) {
            $saved = true;
        } else {
            $jpegAbsolute = preg_replace('/\.webp$/i', '.jpg', $absolutePath);
            if (is_string($jpegAbsolute) && $jpegAbsolute !== ''
                && $downloader->downloadRemoteImageAsJpeg($imageUrl, $jpegAbsolute)) {
                $saved = true;
            }
        }

        if (! $saved) {
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

    protected function getAttemptStoragePath(int $queueItemId, int $attemptIndex): string
    {
        return "blogs/auto-generated/content-{$queueItemId}-{$attemptIndex}.webp";
    }

    protected function getFeaturedStoragePath(int $queueItemId): string
    {
        return "blogs/auto-generated/item-{$queueItemId}.webp";
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

        $content = $this->prepareContentForImageInsertion($content);
        $anchors = $this->collectInsertionAnchors($content);

        if ($anchors === []) {
            return $this->distributeImagesInPlainContent($content, $imageHtmlArray);
        }

        $anchorCount = count($anchors);

        if ($anchorCount < 2) {
            return $this->distributeImagesInPlainContent($content, $imageHtmlArray);
        }

        $imageCount = count($imageHtmlArray);
        $usableAnchors = $this->skipLeadingPromoAnchors($anchors);
        $anchorIndices = $this->calculateAnchorIndices(count($usableAnchors), $imageCount);
        $insertions = [];

        foreach ($anchorIndices as $imageIndex => $relativeAnchorIndex) {
            $anchor = $usableAnchors[$relativeAnchorIndex] ?? null;
            if ($anchor === null) {
                continue;
            }

            $offset = $anchor[1] + strlen($anchor[0]);
            $insertions[$offset][] = $imageHtmlArray[$imageIndex];
        }

        if ($insertions === []) {
            return $this->distributeImagesInPlainContent($content, $imageHtmlArray);
        }

        krsort($insertions, SORT_NUMERIC);

        foreach ($insertions as $offset => $htmlChunks) {
            $content = substr($content, 0, $offset)
                .implode('', $htmlChunks)
                .substr($content, $offset);
        }

        return $content;
    }

    protected function prepareContentForImageInsertion(string $content): string
    {
        $content = BlogContentSanitizer::sanitize($content);

        if ($this->countParagraphAnchors($content) >= self::MIN_INLINE_IMAGES + 2) {
            return $content;
        }

        return $this->wrapLooseTextBlocksInParagraphs($content);
    }

    protected function countParagraphAnchors(string $content): int
    {
        return preg_match_all('/<\/p>/i', $content) ?: 0;
    }

    protected function wrapLooseTextBlocksInParagraphs(string $html): string
    {
        $parts = preg_split('/(<(?:h[1-6]|ul|ol|li|blockquote|figure|hr|pre|table)\b[^>]*>|<\/(?:h[1-6]|ul|ol|li|blockquote|figure|table)>)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if (! is_array($parts)) {
            return $html;
        }

        $result = '';

        foreach ($parts as $part) {
            $trimmed = trim($part);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^<\/?(?:h[1-6]|ul|ol|li|blockquote|figure|hr|pre|table|p)\b/i', $trimmed)) {
                $result .= $trimmed;

                continue;
            }

            if (preg_match('/^<p\b/i', $trimmed)) {
                $result .= $trimmed;

                continue;
            }

            $text = trim(strip_tags($trimmed));
            if ($text === '' || mb_strlen($text) < 40) {
                $result .= $trimmed;

                continue;
            }

            $result .= '<p>'.preg_replace('/\s+/', ' ', $text).'</p>';
        }

        return $result !== '' ? $result : $html;
    }

    /**
     * @return array<int, array{0: string, 1: int}>
     */
    protected function collectInsertionAnchors(string $content): array
    {
        if (preg_match_all('/<\/p>/i', $content, $paragraphMatches, PREG_OFFSET_CAPTURE)) {
            $paragraphAnchors = $paragraphMatches[0];
            if (count($paragraphAnchors) >= 2) {
                return $paragraphAnchors;
            }
        }

        if (! preg_match_all('/<\/(?:p|h[2-4]|ul|ol|blockquote|figure)>/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        return $matches[0];
    }

    /**
     * Bỏ qua 1–2 đoạn đầu (CTA sau h1) để ảnh nằm giữa thân bài.
     *
     * @param  array<int, array{0: string, 1: int}>  $anchors
     * @return array<int, array{0: string, 1: int}>
     */
    protected function skipLeadingPromoAnchors(array $anchors): array
    {
        $maxSkip = max(0, count($anchors) - (self::MIN_INLINE_IMAGES + 1));
        $skip = min(2, $maxSkip);

        return array_values(array_slice($anchors, $skip));
    }

    /**
     * @param  array<int, string>  $imageHtmlArray
     */
    protected function distributeImagesInPlainContent(string $content, array $imageHtmlArray): string
    {
        $paragraphs = preg_split('/\n{2,}/', trim(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n\n", $content)))) ?: [];
        $paragraphs = array_values(array_filter(array_map('trim', $paragraphs), fn (string $p): bool => mb_strlen($p) > 30));

        if (count($paragraphs) < 2) {
            $mid = (int) floor(strlen($content) / 2);
            $first = array_slice($imageHtmlArray, 0, (int) ceil(count($imageHtmlArray) / 2));
            $second = array_slice($imageHtmlArray, (int) ceil(count($imageHtmlArray) / 2));

            return substr($content, 0, $mid).implode('', $first).substr($content, $mid).implode('', $second);
        }

        $wrapped = '';
        foreach ($paragraphs as $paragraph) {
            $wrapped .= '<p>'.htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8').'</p>'."\n\n";
        }

        return $this->insertImagesIntoHtmlContent($wrapped, $imageHtmlArray);
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
