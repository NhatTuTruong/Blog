<?php

namespace App\Services;

use App\Models\InstagramQueueItem;
use App\Support\InstagramSettings;
use App\Support\PublicStorage;
use Illuminate\Support\Facades\Http;

class InstagramPostImageService
{
    public ?string $lastError = null;

    /**
     * Ensure queue item has a JPEG on public disk; return storage-relative path.
     */
    public function ensureStoredJpegForItem(InstagramQueueItem $item): string
    {
        $existing = $this->normalizeStoragePath($item->image_path);
        if ($existing !== null && $this->isInstagramFeedReady($existing)) {
            $absolute = $this->absolutePath($existing);
            if (! app(SocialMediaImageSourceService::class)->isBlockedPlaceholderImage($absolute)) {
                return $existing;
            }
        }

        if ($existing !== null) {
            $converted = $this->convertUploadToInstagramFeed($existing, $item->id);
            if ($converted !== null) {
                $item->update(['image_path' => $converted]);

                return $converted;
            }
        }

        $generated = $this->generatePlaceholderJpeg($item);
        $ready = $this->convertUploadToInstagramFeed($generated, $item->id) ?? $generated;
        $item->update(['image_path' => $ready]);

        return $ready;
    }

    public function signedPublicUrl(InstagramQueueItem $item): ?string
    {
        if (filled($item->video_path)) {
            PublicStorage::syncUploadedPath((string) $item->video_path);
        } else {
            $this->ensureStoredJpegForItem($item);
        }

        $base = InstagramSettings::publicBaseUrl();
        if ($base === null) {
            $this->lastError = 'APP_URL đang là localhost — Meta không tải được ảnh. '
                .'Vào Cài đặt hệ thống → Instagram → nhập «URL công khai» (domain HTTPS hoặc ngrok).';

            return null;
        }

        $token = $this->mediaAccessToken($item);

        return rtrim($base, '/').'/instagram/media/'.$item->id.'?t='.$token;
    }

    /**
     * Thay ảnh queue item bằng ảnh mặc định (URL cài đặt / default1–3) và chuẩn hóa feed.
     */
    public function applyDefaultImageForItem(InstagramQueueItem $item): bool
    {
        $this->lastError = null;

        try {
            $dest = "instagram-generated/item-{$item->id}-fallback.jpg";
            $path = app(SocialMediaImageSourceService::class)->applyDefaultJpeg($item->user_id, $dest);
            $ready = $this->convertUploadToInstagramFeed($path, $item->id) ?? $path;

            $item->update(['image_path' => $ready]);

            return PublicStorage::exists($ready);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    public function mediaAccessToken(InstagramQueueItem $item): string
    {
        return hash_hmac('sha256', 'instagram-media:'.$item->id, (string) config('app.key'));
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

    public function resolveMediaAbsolutePath(InstagramQueueItem $item): string
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

    public function mediaContentType(InstagramQueueItem $item): string
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

    public function deleteStoredVideo(InstagramQueueItem $item): void
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

    protected function isInstagramFeedReady(string $path): bool
    {
        if (! PublicStorage::exists($path)) {
            return false;
        }

        return app(InstagramFeedImageNormalizer::class)->isNormalized($this->absolutePath($path));
    }

    protected function convertUploadToInstagramFeed(string $sourcePath, int $itemId): ?string
    {
        $dest = "instagram-generated/item-{$itemId}.jpg";
        PublicStorage::ensureDirectory('instagram-generated');
        $destAbsolute = $this->absolutePath($dest);

        $normalizer = app(InstagramFeedImageNormalizer::class);
        if ($normalizer->normalizeFile($this->absolutePath($sourcePath), $destAbsolute)) {
            return $dest;
        }

        if (! extension_loaded('gd')) {
            return $this->copyDefaultJpeg($dest);
        }

        return null;
    }

    protected function generatePlaceholderJpeg(InstagramQueueItem $item): string
    {
        return app(SocialMediaImageSourceService::class)->generatePlaceholderJpeg(
            $item->brand_domain,
            $item->user_id,
            "instagram-generated/item-{$item->id}.jpg",
        );
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
