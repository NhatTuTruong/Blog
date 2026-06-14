<?php

namespace App\Services;

use App\Support\PublicStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialMediaImageSourceService
{
    public function generatePlaceholderJpeg(?string $brandDomain, ?int $userId, string $destStoragePath): string
    {
        PublicStorage::ensureDirectory(dirname(str_replace('\\', '/', $destStoragePath)));
        $destAbsolute = PublicStorage::absolutePath($destStoragePath);

        $query = $this->buildSearchQuery($brandDomain);
        if ($query !== null) {
            $apify = app(ApifyGoogleImagesService::class);
            if ($apify->downloadLargestImageForQuery($query, $userId, $destAbsolute)) {
                return $destStoragePath;
            }

            Log::info('SocialMediaImageSource: Apify fallback to default images', [
                'query' => $query,
                'error' => $apify->lastError,
            ]);
        }

        $defaultSource = $this->pickRandomDefaultImage();
        if ($defaultSource !== null && $this->copyImageAsJpeg($defaultSource, $destAbsolute)) {
            return $destStoragePath;
        }

        return $this->copyDefaultJpeg($destStoragePath);
    }

    /**
     * Ảnh mặc định khi đăng lỗi: URL cài đặt → default1–3 → JPEG 1×1.
     */
    public function applyDefaultJpeg(?int $userId, string $destStoragePath): string
    {
        PublicStorage::ensureDirectory(dirname(str_replace('\\', '/', $destStoragePath)));
        $destAbsolute = PublicStorage::absolutePath($destStoragePath);

        $settingsUrl = \App\Support\InstagramSettings::defaultImageUrl($userId);
        if (filled($settingsUrl) && $this->downloadRemoteImageAsJpeg($settingsUrl, $destAbsolute)) {
            return $destStoragePath;
        }

        $defaultSource = $this->pickRandomDefaultImage();
        if ($defaultSource !== null && $this->copyImageAsJpeg($defaultSource, $destAbsolute)) {
            return $destStoragePath;
        }

        return $this->copyDefaultJpeg($destStoragePath);
    }

    public function buildSearchQuery(?string $brandDomain): ?string
    {
        $domain = trim((string) $brandDomain);
        if ($domain === '') {
            return null;
        }

        $lower = strtolower($domain);
        if (str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://')) {
            return $domain;
        }

        return 'https://'.$domain;
    }

    public function downloadRemoteImageAsJpeg(string $imageUrl, string $destAbsolute): bool
    {
        try {
            $response = Http::timeout(45)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SocialMediaBot/1.0)'])
                ->get($imageUrl);
        } catch (\Throwable $e) {
            Log::warning('SocialMediaImageSource: download image failed', [
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        $body = $response->body();
        if ($body === '') {
            return false;
        }

        $temp = tempnam(sys_get_temp_dir(), 'smp-img-');
        if ($temp === false) {
            return false;
        }

        try {
            file_put_contents($temp, $body);

            return $this->copyImageAsJpeg($temp, $destAbsolute);
        } finally {
            @unlink($temp);
        }
    }

    protected function pickRandomDefaultImage(): ?string
    {
        $candidates = $this->defaultImagePaths();

        if ($candidates === []) {
            return null;
        }

        return $candidates[array_rand($candidates)];
    }

    /**
     * @return array<int, string>
     */
    protected function defaultImagePaths(): array
    {
        $paths = [];
        $directory = public_path('images/instagram');

        if (! is_dir($directory)) {
            return [];
        }

        foreach (['default1', 'default2', 'default3'] as $name) {
            foreach (['webp', 'jpg', 'jpeg', 'png'] as $extension) {
                $absolute = $directory.DIRECTORY_SEPARATOR.$name.'.'.$extension;
                if (is_file($absolute)) {
                    $paths[] = $absolute;
                    break;
                }
            }
        }

        return $paths;
    }

    protected function copyImageAsJpeg(string $sourceAbsolute, string $destAbsolute): bool
    {
        if (extension_loaded('gd')) {
            $image = $this->loadImage($sourceAbsolute);
            if ($image !== null) {
                $saved = imagejpeg($image, $destAbsolute, 90);
                imagedestroy($image);

                return $saved && is_file($destAbsolute);
            }
        }

        $mime = mime_content_type($sourceAbsolute) ?: '';
        if (str_contains($mime, 'jpeg') || str_contains($mime, 'jpg')) {
            return copy($sourceAbsolute, $destAbsolute);
        }

        return false;
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
        $jpeg = base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////wgARCAABAAEDAREAAhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAAAv/EABQBAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwEAAhADEAAAAU//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAn//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AX//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AX//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAYJAn//xAAUEAEAAAAAAAAAAAAAAAAAAAAQ/9oACAEBAAE/IX//2Q==',
            true,
        );

        PublicStorage::put($dest, $jpeg);

        return $dest;
    }
}
