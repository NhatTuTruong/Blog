<?php

namespace App\Services;

use App\Models\AutoBlogQueueItem;
use App\Models\Blog;
use Illuminate\Support\Str;

class SocialMediaVideoTitleService
{
    public ?string $lastError = null;

    /**
     * Tiêu đề overlay — không gọi AI thêm.
     * Ưu tiên: tiêu đề bài blog cùng brand → dòng hook caption (đã tạo cùng lần gọi AI đăng bài) → random.
     */
    public function resolveForQueueItem(object $item): string
    {
        $this->lastError = null;

        $blogTitle = $this->findLatestBlogTitle($item->brand_domain ?? null);
        if ($blogTitle !== null) {
            return $blogTitle;
        }

        $captionTitle = $this->extractTitleFromCaption($item->caption ?? null);
        if ($captionTitle !== null) {
            return $captionTitle;
        }

        return $this->randomFallbackTitle();
    }

    public function randomFallbackTitle(): string
    {
        /** @var array<int, string> $titles */
        $titles = config('social_media_video.title_fallbacks', []);

        if ($titles === []) {
            return 'Watch This Before You Buy';
        }

        return $titles[array_rand($titles)];
    }

    public function extractTitleFromCaption(?string $caption): ?string
    {
        $caption = trim((string) $caption);
        if ($caption === '') {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', $caption) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '#') || str_starts_with($line, '🛒') || str_starts_with($line, '🎟️')) {
                continue;
            }

            $title = $this->normalizeTitle($this->stripCaptionDecorations($line));
            if ($title !== null) {
                return $title;
            }
        }

        return null;
    }

    protected function stripCaptionDecorations(string $line): string
    {
        $line = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $line) ?? $line;

        return trim(preg_replace('/\s+/u', ' ', $line) ?? $line);
    }

    protected function findLatestBlogTitle(?string $brandDomain): ?string
    {
        $brandDomain = trim((string) $brandDomain);
        if ($brandDomain === '') {
            return null;
        }

        $host = strtolower(parse_url(
            str_starts_with($brandDomain, 'http') ? $brandDomain : 'https://'.$brandDomain,
            PHP_URL_HOST
        ) ?? $brandDomain);
        $host = preg_replace('#^www\.#', '', $host) ?? $host;

        if ($host === '') {
            return null;
        }

        /** @var AutoBlogQueueItem|null $queueItem */
        $queueItem = AutoBlogQueueItem::query()
            ->whereNotNull('blog_id')
            ->where(function ($query) use ($brandDomain, $host): void {
                $query->where('brand_domain', $brandDomain)
                    ->orWhere('brand_domain', 'like', '%'.$host.'%');
            })
            ->latest('processed_at')
            ->first();

        if ($queueItem?->blog_id) {
            $title = Blog::query()->whereKey($queueItem->blog_id)->value('title');
            $normalized = $this->normalizeTitle(is_string($title) ? $title : null);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    protected function normalizeTitle(?string $title): ?string
    {
        $title = trim(preg_replace('/\s+/u', ' ', (string) $title) ?? '');
        $title = trim($title, " \t\n\r\0\x0B\"'“”‘’`*#");
        $title = preg_replace('/^title\s*:\s*/i', '', $title) ?? $title;

        if ($title === '') {
            return null;
        }

        return Str::limit($title, 90, '');
    }
}
