<?php

namespace App\Services;

use App\Models\FacebookQueueItem;
use App\Support\FacebookSettings;
use App\Support\PublicStorage;
use Illuminate\Support\Facades\Http;

class FacebookPostMediaService
{
    public ?string $lastError = null;

    /**
     * Ensure queue item has a JPEG on public disk; return storage-relative path.
     */
    public function ensureStoredJpegForItem(FacebookQueueItem $item): string
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

    public function signedPublicUrl(FacebookQueueItem $item): ?string
    {
        if (filled($item->video_path)) {
            PublicStorage::syncUploadedPath((string) $item->video_path);
        } else {
            $this->ensureStoredJpegForItem($item);
        }

        $base = FacebookSettings::publicBaseUrl();
        if ($base === null) {
            $this->lastError = 'APP_URL đang là localhost — Meta không tải được ảnh. '
                .'Vào Cài đặt hệ thống → Facebook → nhập «URL công khai» (domain HTTPS hoặc ngrok).';

            return null;
        }

        $token = $this->mediaAccessToken($item);

        return rtrim($base, '/').'/facebook/media/'.$item->id.'?t='.$token;
    }

    public function mediaAccessToken(FacebookQueueItem $item): string
    {
        return hash_hmac('sha256', 'facebook-media:'.$item->id, (string) config('app.key'));
    }

    public function absolutePath(string $storagePath): string
    {
        PublicStorage::syncUploadedPath($storagePath);

        return PublicStorage::absolutePath($storagePath);
    }

    public function validatePublicImageUrl(string $url): ?string
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; InstagramBot/1.0)'])
                ->head($url);

            if (! $response->successful()) {
                $response = Http::timeout(20)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; InstagramBot/1.0)'])
                    ->get($url);
            }

            if (! $response->successful()) {
                return 'Meta không truy cập được URL ảnh (HTTP '.$response->status().'). Kiểm tra URL công khai HTTPS.';
            }

            $contentType = strtolower((string) $response->header('Content-Type', ''));
            if ($contentType !== '' && ! str_contains($contentType, 'image/jpeg') && ! str_contains($contentType, 'image/jpg')) {
                return 'URL ảnh trả về Content-Type không phải JPEG ('.$contentType.'). Instagram yêu cầu ảnh JPG/PNG hợp lệ.';
            }
        } catch (\Throwable $e) {
            return 'Không kiểm tra được URL ảnh: '.$e->getMessage();
        }

        return null;
    }

    public function validatePublicVideoUrl(string $url): ?string
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; InstagramBot/1.0)'])
                ->head($url);

            if (! $response->successful()) {
                $response = Http::timeout(30)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; InstagramBot/1.0)'])
                    ->withOptions(['stream' => true])
                    ->get($url);
            }

            if (! $response->successful()) {
                return 'Meta không truy cập được URL video (HTTP '.$response->status().'). Kiểm tra URL công khai HTTPS.';
            }

            $contentType = strtolower((string) $response->header('Content-Type', ''));
            if ($contentType !== '' && ! str_contains($contentType, 'video/')) {
                return 'URL video trả về Content-Type không phải video ('.$contentType.').';
            }
        } catch (\Throwable $e) {
            return 'Không kiểm tra được URL video: '.$e->getMessage();
        }

        return null;
    }

    public function resolveMediaAbsolutePath(FacebookQueueItem $item): string
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

    public function mediaContentType(FacebookQueueItem $item): string
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

    public function deleteStoredVideo(FacebookQueueItem $item): void
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
            return $this->copyDefaultJpeg("facebook-generated/item-{$itemId}.jpg");
        }

        $absolute = $this->absolutePath($sourcePath);
        $image = $this->loadImage($absolute);
        if ($image === null) {
            return null;
        }

        $dest = "facebook-generated/item-{$itemId}.jpg";
        PublicStorage::ensureDirectory('facebook-generated');
        $destAbsolute = $this->absolutePath($dest);

        imagejpeg($image, $destAbsolute, 90);
        imagedestroy($image);

        return $dest;
    }

    protected function generatePlaceholderJpeg(FacebookQueueItem $item): string
    {
        return app(SocialMediaImageSourceService::class)->generatePlaceholderJpeg(
            $item->brand_domain,
            $item->user_id,
            "facebook-generated/item-{$item->id}.jpg",
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
