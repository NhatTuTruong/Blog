<?php

namespace App\Services;

use App\Models\AutoBlogQueueItem;
use App\Support\PublicStorage;

class AutoBlogFeaturedImageService
{
    public function resolveForQueueItem(AutoBlogQueueItem $item): ?string
    {
        $uploaded = $this->normalizeStoragePath($item->image_path);
        if ($uploaded !== null) {
            PublicStorage::syncUploadedPath($uploaded);

            return $uploaded;
        }

        $dest = "blogs/auto-generated/item-{$item->id}.jpg";

        return app(SocialMediaImageSourceService::class)->generatePlaceholderJpeg(
            $item->brand_domain,
            $item->user_id,
            $dest,
        );
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
