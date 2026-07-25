<?php

namespace App\Services;

use App\Support\ApifyImageOrientation;
use App\Support\BrandDomain;
use App\Support\PublicStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialMediaImageSourceService
{
    /**
     * @param string $orientation ApifyImageOrientation::LANDSCAPE, PORTRAIT_SQUARE, etc.
     * @param bool $randomImage If true, pick a random image from top candidates instead of best.
     * @param int $topCandidates Number of top candidates to consider when random=true.
     */
    public function generatePlaceholderJpeg(
        ?string $brandDomain,
        ?int $userId,
        string $destStoragePath,
        string $orientation = ApifyImageOrientation::LANDSCAPE,
        bool $randomImage = false,
        int $topCandidates = 5,
    ): string {
        PublicStorage::ensureDirectory(dirname(str_replace('\\', '/', $destStoragePath)));
        $destAbsolute = PublicStorage::absolutePath($destStoragePath);

        $query = $this->buildSearchQuery($brandDomain);
        if ($query !== null) {
            $apify = app(ApifyGoogleImagesService::class);

            $success = $randomImage
                ? $apify->downloadRandomQualityImageForQuery($query, $userId, $destAbsolute, $orientation, $topCandidates)
                : $apify->downloadLargestImageForQuery($query, $userId, $destAbsolute, $orientation);

            if ($success && ! $this->isBlockedPlaceholderImage($destAbsolute)) {
                return $destStoragePath;
            }

            if (is_file($destAbsolute)) {
                @unlink($destAbsolute);
            }

            Log::info('SocialMediaImageSource: Apify image unavailable — using default1', [
                'query' => $query,
                'error' => $apify->lastError,
                'random' => $randomImage,
            ]);
        }

        if ($this->copyPrimaryDefaultImage($destAbsolute)) {
            return $destStoragePath;
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

        if ($this->copyPrimaryDefaultImage($destAbsolute)) {
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
        return BrandDomain::searchUrl($brandDomain);
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

        if ($this->responseBodyLooksLikeBlockedPlaceholder($body)) {
            Log::info('SocialMediaImageSource: remote image looks like permission placeholder', [
                'url' => $imageUrl,
            ]);

            return false;
        }

        $temp = tempnam(sys_get_temp_dir(), 'smp-img-');
        if ($temp === false) {
            return false;
        }

        try {
            file_put_contents($temp, $body);

            if (! $this->copyImageAsJpeg($temp, $destAbsolute)) {
                return false;
            }

            if ($this->isBlockedPlaceholderImage($destAbsolute)) {
                @unlink($destAbsolute);

                return false;
            }

            return true;
        } finally {
            @unlink($temp);
        }
    }

    public function isBlockedPlaceholderImage(string $absolutePath): bool
    {
        if (! is_file($absolutePath)) {
            return true;
        }

        $size = filesize($absolutePath);
        if ($size === false || $size < 500) {
            return true;
        }

        if (@getimagesize($absolutePath) === false) {
            return true;
        }

        $sample = @file_get_contents($absolutePath, false, null, 0, min((int) $size, 131072));
        if ($sample === false || $sample === '') {
            return false;
        }

        return $this->responseBodyLooksLikeBlockedPlaceholder($sample)
            || $this->looksLikePermissionPlaceholderImage($absolutePath);
    }

    protected function looksLikePermissionPlaceholderImage(string $absolutePath): bool
    {
        if (! extension_loaded('gd')) {
            return false;
        }

        $image = $this->loadImage($absolutePath);
        if ($image === null) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width < 80 || $height < 80) {
            imagedestroy($image);

            return true;
        }

        $lightBackground = 0;
        $darkText = 0;
        $samples = 0;
        $stepX = max(1, (int) floor($width / 24));
        $stepY = max(1, (int) floor($height / 24));

        for ($x = 0; $x < $width; $x += $stepX) {
            for ($y = 0; $y < $height; $y += $stepY) {
                $rgb = imagecolorat($image, $x, $y);
                $red = ($rgb >> 16) & 0xFF;
                $green = ($rgb >> 8) & 0xFF;
                $blue = $rgb & 0xFF;
                $samples++;

                if ($red > 175 && $green > 185 && $blue > 195 && abs($red - $green) < 35) {
                    $lightBackground++;
                }

                if ($red < 45 && $green < 45 && $blue < 45) {
                    $darkText++;
                }
            }
        }

        imagedestroy($image);

        if ($samples === 0) {
            return false;
        }

        $lightRatio = $lightBackground / $samples;
        $darkRatio = $darkText / $samples;

        return $lightRatio > 0.3 && $darkRatio > 0.015 && $darkRatio < 0.2;
    }

    protected function responseBodyLooksLikeBlockedPlaceholder(string $body): bool
    {
        $lower = strtolower($body);

        $needles = [
            'does not have permission to access or serve this content',
            'does not have permission',
            'permission to access or serve',
            'this site does not have permission',
        ];

        foreach ($needles as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function primaryDefaultImagePath(): ?string
    {
        $directory = public_path('images/instagram');

        if (! is_dir($directory)) {
            return null;
        }

        foreach (['webp', 'jpg', 'jpeg', 'png'] as $extension) {
            $absolute = $directory.DIRECTORY_SEPARATOR.'default1.'.$extension;
            if (is_file($absolute)) {
                return $absolute;
            }
        }

        return null;
    }

    protected function copyPrimaryDefaultImage(string $destAbsolute): bool
    {
        $source = $this->primaryDefaultImagePath();

        if ($source === null) {
            return false;
        }

        return $this->copyImageAsJpeg($source, $destAbsolute);
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
