<?php

namespace App\Services;

use App\Models\FacebookQueueItem;
use App\Models\InstagramQueueItem;
use App\Support\PublicStorage;
use App\Support\SocialMediaMediaType;

class SocialMediaVideoSourceService
{
    public ?string $lastError = null;

    public function ensureStoredVideoForInstagramItem(InstagramQueueItem $item): string
    {
        return $this->ensureStoredVideoForItem($item, 'instagram-videos');
    }

    public function ensureStoredVideoForFacebookItem(FacebookQueueItem $item): string
    {
        return $this->ensureStoredVideoForItem($item, 'facebook-videos');
    }

    /**
     * @param  InstagramQueueItem|FacebookQueueItem  $item
     */
    protected function ensureStoredVideoForItem(object $item, string $directory): string
    {
        $this->lastError = null;

        $existing = $this->normalizeStoragePath($item->video_path ?? null);
        if ($existing !== null) {
            $this->syncVideoCoverPath($item, $existing);

            return $this->prepareVideoIfNeeded($item, $directory, $existing);
        }

        if (! $this->itemWantsAutoVideo($item)) {
            throw new \RuntimeException('Bài này không chọn loại media video.');
        }

        PublicStorage::ensureDirectory($directory);
        $dest = "{$directory}/item-{$item->id}.mp4";
        $destAbsolute = PublicStorage::absolutePath($dest);

        $apify = app(ApifyTikTokService::class);
        $hashtag = $apify->hashtagFromBrandDomain($item->brand_domain ?? null);
        $userId = (int) ($item->user_id ?? 0);

        PublicStorage::ensureDirectory('apify-videos/user-'.$userId);

        if (! $apify->downloadFirstVideoForHashtag($hashtag, $userId, $destAbsolute)) {
            $message = $apify->lastError
                ?? app(SocialMediaVideoDownloadService::class)->lastError
                ?? 'Không tải được video TikTok.';
            $this->lastError = $message;

            throw new \RuntimeException($message);
        }

        $coverRelative = $this->coverRelativePathBesideVideo($dest);
        $updates = ['video_path' => $dest];

        if ($coverRelative !== null && PublicStorage::exists($coverRelative)) {
            $updates['image_path'] = $coverRelative;
        }

        $item->update($updates);

        return $this->prepareVideoIfNeeded($item, $directory, $dest);
    }

    protected function coverRelativePathBesideVideo(string $videoRelative): ?string
    {
        $videoRelative = PublicStorage::normalizePath($videoRelative);

        if (! str_ends_with(strtolower($videoRelative), '.mp4')) {
            return null;
        }

        return preg_replace('/\.mp4$/i', '-cover.jpg', $videoRelative);
    }

    /**
     * @param  InstagramQueueItem|FacebookQueueItem  $item
     */
    protected function syncVideoCoverPath(object $item, string $videoRelative): void
    {
        $coverRelative = $this->coverRelativePathBesideVideo($videoRelative);

        if ($coverRelative === null || ! PublicStorage::exists($coverRelative)) {
            return;
        }

        if ((string) ($item->image_path ?? '') === $coverRelative) {
            return;
        }

        $item->update(['image_path' => $coverRelative]);
    }

    protected function prepareVideoIfNeeded(object $item, string $directory, string $path): string
    {
        $prepare = app(SocialMediaVideoPrepareService::class);

        if (! $prepare->isEnabled()) {
            return $path;
        }

        return $prepare->prepareForQueueItem($item, $directory);
    }

    /**
     * @param  InstagramQueueItem|FacebookQueueItem  $item
     */
    public function itemWantsAutoVideo(object $item): bool
    {
        if (filled($item->video_path ?? null)) {
            return true;
        }

        if (SocialMediaMediaType::normalize($item->media_type ?? null) === SocialMediaMediaType::VIDEO) {
            return true;
        }

        if (filled($item->image_path ?? null)) {
            return false;
        }

        return false;
    }

    protected function normalizeStoragePath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        $path = str_replace('\\', '/', $path);

        return PublicStorage::exists($path) ? $path : null;
    }
}
