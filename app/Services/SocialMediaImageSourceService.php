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

            $savedAbsolute = $this->resolveSavedAbsolute($destAbsolute);
            if ($success && $savedAbsolute !== null && ! $this->isBlockedPlaceholderImage($savedAbsolute)) {
                return $this->storedPathMatchingFile($destStoragePath, $savedAbsolute);
            }

            foreach (array_filter([$destAbsolute, $this->jpegSibling($destAbsolute)]) as $leftover) {
                if (is_file($leftover)) {
                    @unlink($leftover);
                }
            }

            Log::info('SocialMediaImageSource: Apify image unavailable — using default1', [
                'query' => $query,
                'error' => $apify->lastError,
                'random' => $randomImage,
            ]);
        }

        if ($this->copyPrimaryDefaultImage($destAbsolute)) {
            return $this->storedPathMatchingFile($destStoragePath, $destAbsolute);
        }

        $defaultSource = $this->pickRandomDefaultImage();
        if ($defaultSource !== null && $this->copyImageToDestination($defaultSource, $destAbsolute)) {
            return $this->storedPathMatchingFile($destStoragePath, $destAbsolute);
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

    public function downloadRemoteImageAsWebp(string $imageUrl, string $destAbsolute): bool
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

            if (! $this->copyImageAsWebp($temp, $destAbsolute) || ! is_file($destAbsolute)) {
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

        $absolute = $directory.DIRECTORY_SEPARATOR.'default1.webp';
        if (is_file($absolute)) {
            return $absolute;
        }

        return null;
    }

    protected function copyPrimaryDefaultImage(string $destAbsolute): bool
    {
        $source = $this->primaryDefaultImagePath();

        if ($source === null) {
            return false;
        }

        return $this->copyImageToDestination($source, $destAbsolute);
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
            $absolute = $directory.DIRECTORY_SEPARATOR.$name.'.webp';
            if (is_file($absolute)) {
                $paths[] = $absolute;
            }
        }

        return $paths;
    }

    public function convertFileToWebp(string $sourceAbsolute, string $destAbsolute, int $maxEdge = 1920): bool
    {
        return $this->copyImageAsWebp($sourceAbsolute, $destAbsolute, $maxEdge) && is_file($destAbsolute);
    }

    protected function copyImageToDestination(string $sourceAbsolute, string $destAbsolute): bool
    {
        return $this->wantsWebp($destAbsolute)
            ? $this->copyImageAsWebp($sourceAbsolute, $destAbsolute)
            : $this->copyImageAsJpeg($sourceAbsolute, $destAbsolute);
    }

    protected function wantsWebp(string $path): bool
    {
        return str_ends_with(strtolower($path), '.webp');
    }

    protected function jpegSibling(string $path): string
    {
        return preg_replace('/\.webp$/i', '.jpg', $path) ?: $path;
    }

    protected function resolveSavedAbsolute(string $destAbsolute): ?string
    {
        if (is_file($destAbsolute)) {
            return $destAbsolute;
        }

        $jpeg = $this->jpegSibling($destAbsolute);

        return is_file($jpeg) ? $jpeg : null;
    }

    protected function storedPathMatchingFile(string $destStoragePath, string $savedAbsolute): string
    {
        if (str_ends_with(strtolower($savedAbsolute), '.jpg') && $this->wantsWebp($destStoragePath)) {
            return preg_replace('/\.webp$/i', '.jpg', $destStoragePath) ?: $destStoragePath;
        }

        return $destStoragePath;
    }

    protected function copyImageAsWebp(string $sourceAbsolute, string $destAbsolute, int $maxEdge = 1920): bool
    {
        $maxEdge = max(320, $maxEdge);

        if (extension_loaded('imagick')) {
            try {
                $image = new \Imagick($sourceAbsolute);
                $image->setImageBackgroundColor('white');
                $image = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $image->thumbnailImage($maxEdge, $maxEdge, true);
                $image->setImageFormat('webp');
                $image->setImageCompressionQuality(82);
                $written = $image->writeImage($destAbsolute);
                $image->clear();
                $image->destroy();

                if ($written && is_file($destAbsolute)) {
                    return true;
                }
            } catch (\Throwable) {
                // Fallback GD
            }
        }

        if (extension_loaded('gd') && function_exists('imagewebp')) {
            $info = @getimagesize($sourceAbsolute);
            $width = (int) ($info[0] ?? 0);
            $height = (int) ($info[1] ?? 0);
            $pixels = $width * $height;

            // Truecolor ~4 bytes/pixel; imagewebp cần thêm buffer.
            if ($pixels > 25_000_000) {
                return false;
            }

            $image = $this->loadImage($sourceAbsolute);
            if ($image !== null) {
                $image = $this->ensureTruecolorImage($image);
                $image = $this->constrainImageEdge($image, $maxEdge);
                imagesavealpha($image, true);
                imagealphablending($image, true);
                $saved = @imagewebp($image, $destAbsolute, 82);
                imagedestroy($image);

                if ($saved && is_file($destAbsolute)) {
                    return true;
                }
            }
        }

        $mime = mime_content_type($sourceAbsolute) ?: '';
        if (str_contains($mime, 'webp')) {
            return copy($sourceAbsolute, $destAbsolute) && is_file($destAbsolute);
        }

        return false;
    }

    protected function constrainImageEdge(\GdImage $image, int $maxEdge): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $long = max($width, $height);

        if ($long <= $maxEdge) {
            return $image;
        }

        $scale = $maxEdge / $long;
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if ($resized === false) {
            return $image;
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
        imagealphablending($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }

    protected function ensureTruecolorImage(\GdImage $image): \GdImage
    {
        if (imageistruecolor($image)) {
            return $image;
        }

        if (function_exists('imagepalettetotruecolor') && @imagepalettetotruecolor($image)) {
            return $image;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $truecolor = imagecreatetruecolor($width, $height);

        if ($truecolor === false) {
            return $image;
        }

        imagealphablending($truecolor, false);
        imagesavealpha($truecolor, true);
        $transparent = imagecolorallocatealpha($truecolor, 0, 0, 0, 127);
        imagefilledrectangle($truecolor, 0, 0, $width, $height, $transparent);
        imagealphablending($truecolor, true);
        imagecopy($truecolor, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);

        return $truecolor;
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

    public function copyDefaultJpeg(string $dest): string
    {
        $jpeg = base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////wgARCAABAAEDAREAAhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAAAv/EABQBAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwEAAhADEAAAAU//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAn//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AX//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AX//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAYJAn//xAAUEAEAAAAAAAAAAAAAAAAAAAAQ/9oACAEBAAE/IX//2Q==',
            true,
        );

        if ($this->wantsWebp($dest) && extension_loaded('gd') && function_exists('imagewebp')) {
            $absolute = PublicStorage::absolutePath($dest);
            PublicStorage::ensureDirectory(dirname(str_replace('\\', '/', $dest)));
            $image = imagecreatetruecolor(1, 1);
            if ($image !== false) {
                $saved = @imagewebp($image, $absolute, 80);
                imagedestroy($image);
                if ($saved && is_file($absolute)) {
                    return $dest;
                }
            }
        }

        $jpegDest = $this->wantsWebp($dest)
            ? (preg_replace('/\.webp$/i', '.jpg', $dest) ?: $dest)
            : $dest;

        PublicStorage::put($jpegDest, $jpeg ?: '');

        if ($this->wantsWebp($dest)) {
            $webpAbsolute = PublicStorage::absolutePath($dest);
            $jpegAbsolute = PublicStorage::absolutePath($jpegDest);
            if ($this->copyImageAsWebp($jpegAbsolute, $webpAbsolute)) {
                @unlink($jpegAbsolute);

                return $dest;
            }
        }

        return $jpegDest;
    }
}
