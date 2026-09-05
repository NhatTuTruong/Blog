<?php

namespace App\Services;

use App\Models\PinterestQueueItem;
use App\Support\PinterestSettings;
use App\Support\PublicStorage;
use App\Support\SocialMediaPublicUrl;
use App\Support\SocialMediaPublicUrlValidator;

class PinterestPostMediaService
{
    public ?string $lastError = null;

    /**
     * Ensure queue item has a JPEG on public disk; return storage-relative path.
     */
    public function ensureStoredJpegForItem(PinterestQueueItem $item): string
    {
        $existing = $this->normalizeStoragePath($item->image_path);
        if ($existing !== null && $this->isJpegAtPath($existing)) {
            $absolute = PublicStorage::absolutePath($existing);
            if (! app(SocialMediaImageSourceService::class)->isBlockedPlaceholderImage($absolute)) {
                return $existing;
            }
        }

        if ($existing !== null) {
            $converted = $this->convertUploadToJpeg($existing, $item->id);
            if ($converted !== null) {
                $item->update(['image_path' => $converted]);

                return $converted;
            }
        }

        $generated = $this->generatePlaceholderJpeg($item);
        $item->update(['image_path' => $generated]);

        return $generated;
    }

    public function signedPublicImageUrl(PinterestQueueItem $item): ?string
    {
        $storagePath = filled($item->video_path)
            ? $this->normalizeStoragePath($item->image_path) ?? $this->ensureStoredJpegForItem($item)
            : $this->ensureStoredJpegForItem($item);

        if ($storagePath === null) {
            $this->lastError = 'Không tìm thấy file ảnh trên máy chủ.';

            return null;
        }

        $base = PinterestSettings::publicBaseUrl($item->user_id);
        if ($base === null) {
            $this->lastError = 'APP_URL đang là localhost — Pinterest không tải được ảnh. '
                .'Vào Cài đặt hệ thống → Pinterest → nhập «URL công khai» (domain HTTPS hoặc ngrok).';

            return null;
        }

        return SocialMediaPublicUrl::build($base, $storagePath);
    }

    /**
     * Thay ảnh queue item bằng ảnh mặc định (URL cài đặt / default1–3).
     */
    public function applyDefaultImageForItem(PinterestQueueItem $item): bool
    {
        $this->lastError = null;

        try {
            $dest = "pinterest-generated/item-{$item->id}-fallback.jpg";
            $path = app(SocialMediaImageSourceService::class)->applyDefaultJpeg($item->user_id, $dest);
            $ready = $this->convertUploadToJpeg($path, $item->id) ?? $path;

            $item->update(['image_path' => $ready]);

            return PublicStorage::exists($ready);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    public function mediaAccessToken(PinterestQueueItem $item): string
    {
        return hash_hmac('sha256', 'pinterest-media:'.$item->id, (string) config('app.key'));
    }

    public function absolutePath(string $storagePath): string
    {
        PublicStorage::syncUploadedPath($storagePath);

        return PublicStorage::absolutePath($storagePath);
    }

    public function validatePublicImageUrl(string $url): ?string
    {
        [$error] = SocialMediaPublicUrlValidator::validateImageUrl($url);

        return $error;
    }

    public function validatePublicVideoUrl(string $url): ?string
    {
        [$error] = SocialMediaPublicUrlValidator::validateVideoUrl($url);

        return $error;
    }

    public function resolveMediaAbsolutePath(PinterestQueueItem $item): string
    {
        if (filled($item->video_path)) {
            $path = $this->normalizeStoragePath($item->video_path);
            if ($path === null) {
                throw new \RuntimeException('File video không tồn tại trên máy chủ.');
            }

            return $this->absolutePath($path);
        }

        $path = $this->ensureStoredJpegForItem($item);

        return $this->absolutePath($path);
    }

    public function mediaContentType(PinterestQueueItem $item): string
    {
        if (filled($item->video_path)) {
            $path = $this->normalizeStoragePath($item->video_path);
            if ($path !== null) {
                $mime = mime_content_type($this->absolutePath($path)) ?: 'video/mp4';

                return $mime;
            }
        }

        return 'image/jpeg';
    }

    public function deleteStoredVideo(PinterestQueueItem $item): void
    {
        $path = $this->normalizeStoragePath($item->video_path);
        if ($path === null) {
            return;
        }

        PublicStorage::delete($path);
        $item->update(['video_path' => null]);
    }

    protected function normalizeStoragePath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (is_array($path)) {
            return null;
        }

        $path = str_replace('\\', '/', $path);

        return PublicStorage::exists($path) ? $path : null;
    }

    protected function isJpegAtPath(string $path): bool
    {
        if (! PublicStorage::exists($path)) {
            return false;
        }

        $mime = mime_content_type($this->absolutePath($path));

        return in_array($mime, ['image/jpeg', 'image/jpg'], true);
    }

    protected function convertUploadToJpeg(string $sourcePath, int $itemId): ?string
    {
        if (! extension_loaded('gd')) {
            return $this->copyDefaultJpeg("pinterest-generated/item-{$itemId}.jpg");
        }

        $absolute = $this->absolutePath($sourcePath);
        $image = $this->loadImage($absolute);
        if ($image === null) {
            return null;
        }

        $dest = "pinterest-generated/item-{$itemId}.jpg";
        PublicStorage::ensureDirectory('pinterest-generated');
        $destAbsolute = $this->absolutePath($dest);

        imagejpeg($image, $destAbsolute, 90);
        imagedestroy($image);

        return $dest;
    }

    protected function generatePlaceholderJpeg(PinterestQueueItem $item): string
    {
        return app(SocialMediaImageSourceService::class)->generatePlaceholderJpeg(
            $item->brand_domain,
            $item->user_id,
            "pinterest-generated/item-{$item->id}.jpg",
            ApifyImageOrientation::LANDSCAPE,
            randomImage: true,
            topCandidates: 5,
        );
    }

    protected function loadImage(string $absolutePath): ?\GdImage
    {
        $mime = mime_content_type($absolutePath) ?: '';

        return match (true) {
            str_contains($mime, 'jpeg'), str_contains($mime, 'jpg') => @imagecreatefromjpeg($absolutePath) ?: null,
            str_contains($mime, 'png') => @imagecreatefrompng($absolutePath) ?: null,
            str_contains($mime, 'webp') && function_exists('imagecreatefromwebp') => @imagecreatefromwebp($absolutePath) ?: null,
            str_contains($mime, 'gif') => @imagecreatefromgif($absolutePath) ?: null,
            default => null,
        };
    }

    protected function copyDefaultJpeg(string $dest): string
    {
        // Minimal valid 1x1 JPEG — Instagram needs real photo; GD path preferred above.
        $jpeg = base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////wgARCAABAAEDAREAAhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAAAv/EABQBAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwEAAhADEAAAAU//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAn//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AX//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AX//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAYJAn//xAAUEAEAAAAAAAAAAAAAAAAAAAAQ/9oACAEBAAE/IX//2Q==',
            true,
        );

        PublicStorage::put($dest, $jpeg);

        return $dest;
    }
}
