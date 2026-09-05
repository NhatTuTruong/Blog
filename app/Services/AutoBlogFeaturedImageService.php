<?php

namespace App\Services;

use App\Models\AutoBlogQueueItem;
use App\Support\ApifyImageOrientation;
use App\Support\PublicStorage;
use Illuminate\Support\Facades\Log;

class AutoBlogFeaturedImageService
{
    public function resolveUploadedPath(AutoBlogQueueItem $item): ?string
    {
        $uploaded = $this->normalizeStoragePath($item->image_path);
        if ($uploaded !== null) {
            PublicStorage::syncUploadedPath($uploaded);
        }

        return $uploaded;
    }

    public function resolveForQueueItem(AutoBlogQueueItem $item): ?string
    {
        $uploaded = $this->resolveUploadedPath($item);
        if ($uploaded !== null) {
            $item->touch();

            return $uploaded;
        }

        return $this->resolveApifyFallback($item);
    }

    public function resolveApifyFallback(AutoBlogQueueItem $item): ?string
    {
        $dest = "blogs/auto-generated/item-{$item->id}.jpg";

        try {
            $path = app(SocialMediaImageSourceService::class)->generatePlaceholderJpeg(
                $item->brand_domain,
                $item->user_id,
                $dest,
                ApifyImageOrientation::LANDSCAPE,
                randomImage: true,
                topCandidates: 5,
            );

            $item->touch();

            return $path;
        } catch (\Throwable $e) {
            Log::error('AutoBlogFeaturedImageService failed — using 1×1 placeholder', [
                'queue_item_id' => $item->id,
                'brand_domain' => $item->brand_domain,
                'error' => $e->getMessage(),
            ]);

            return app(SocialMediaImageSourceService::class)->copyDefaultJpeg($dest);
        }
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
