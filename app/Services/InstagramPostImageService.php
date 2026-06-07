<?php

namespace App\Services;

use App\Models\InstagramQueueItem;
use App\Support\AdminSettings;
use App\Support\InstagramSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InstagramPostImageService
{
    public ?string $lastError = null;

    /**
     * Ensure queue item has a JPEG on public disk; return storage-relative path.
     */
    public function ensureStoredJpegForItem(InstagramQueueItem $item): string
    {
        $existing = $this->normalizeStoragePath($item->image_path);
        if ($existing !== null && $this->isJpegAtPath($existing)) {
            return $existing;
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

    public function signedPublicUrl(InstagramQueueItem $item): ?string
    {
        $this->ensureStoredJpegForItem($item);

        $base = InstagramSettings::publicBaseUrl();
        if ($base === null) {
            $this->lastError = 'APP_URL đang là localhost — Meta không tải được ảnh. '
                .'Vào Cài đặt hệ thống → Instagram → nhập «URL công khai» (domain HTTPS hoặc ngrok).';

            return null;
        }

        $token = $this->mediaAccessToken($item);

        return rtrim($base, '/').'/instagram/media/'.$item->id.'?t='.$token;
    }

    public function mediaAccessToken(InstagramQueueItem $item): string
    {
        return hash_hmac('sha256', 'instagram-media:'.$item->id, (string) config('app.key'));
    }

    public function absolutePath(string $storagePath): string
    {
        return Storage::disk('public')->path($storagePath);
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

        return Storage::disk('public')->exists($path) ? $path : null;
    }

    protected function isJpegAtPath(string $path): bool
    {
        if (! Storage::disk('public')->exists($path)) {
            return false;
        }

        $mime = mime_content_type($this->absolutePath($path));

        return in_array($mime, ['image/jpeg', 'image/jpg'], true);
    }

    protected function convertUploadToJpeg(string $sourcePath, int $itemId): ?string
    {
        if (! extension_loaded('gd')) {
            return $this->copyDefaultJpeg("instagram-generated/item-{$itemId}.jpg");
        }

        $absolute = $this->absolutePath($sourcePath);
        $image = $this->loadImage($absolute);
        if ($image === null) {
            return null;
        }

        $dest = "instagram-generated/item-{$itemId}.jpg";
        Storage::disk('public')->makeDirectory('instagram-generated');
        $destAbsolute = $this->absolutePath($dest);

        imagejpeg($image, $destAbsolute, 90);
        imagedestroy($image);

        return $dest;
    }

    protected function generatePlaceholderJpeg(InstagramQueueItem $item): string
    {
        Storage::disk('public')->makeDirectory('instagram-generated');
        $dest = "instagram-generated/item-{$item->id}.jpg";

        if (extension_loaded('gd')) {
            $this->renderTextCard($item, $this->absolutePath($dest));

            return $dest;
        }

        $fallbackUrl = trim((string) AdminSettings::get('instagram_default_image_url', ''));
        if ($fallbackUrl === '') {
            $fallbackUrl = trim((string) AdminSettings::get('seo_og_image_default', ''));
        }

        if ($fallbackUrl !== '' && filter_var($fallbackUrl, FILTER_VALIDATE_URL)) {
            try {
                $bytes = Http::timeout(20)->get($fallbackUrl)->body();
                if ($bytes !== '') {
                    Storage::disk('public')->put($dest, $bytes);

                    return $dest;
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        return $this->copyDefaultJpeg($dest);
    }

    protected function renderTextCard(InstagramQueueItem $item, string $destAbsolute): void
    {
        $width = 1080;
        $height = 1080;
        $image = imagecreatetruecolor($width, $height);

        $bg = imagecolorallocate($image, 17, 24, 39);
        $accent = imagecolorallocate($image, 245, 158, 11);
        $white = imagecolorallocate($image, 248, 250, 252);
        imagefill($image, 0, 0, $bg);

        $title = filled($item->brand_domain)
            ? Str::upper(GeminiBlogService::guessBrandNameFromDomain(
                GeminiBlogService::normalizeDomain((string) $item->brand_domain) ?? (string) $item->brand_domain
            ))
            : config('app.name', 'Deal');

        $lines = $this->wrapText(
            filled($item->content_idea)
                ? (string) $item->content_idea
                : 'Exclusive deals & promo codes — link in bio.',
            28,
        );

        if (is_array($item->coupon_codes) && $item->coupon_codes !== []) {
            $lines[] = '';
            $lines[] = 'CODE: '.implode(' · ', array_slice($item->coupon_codes, 0, 3));
        }

        imagefilledrectangle($image, 60, 60, $width - 60, 64, $accent);
        imagestring($image, 5, 72, 100, $this->truncate($title, 32), $accent);

        $y = 180;
        foreach ($lines as $line) {
            if ($y > $height - 120) {
                break;
            }
            imagestring($image, 4, 72, $y, $this->truncate($line, 42), $white);
            $y += 36;
        }

        imagejpeg($image, $destAbsolute, 90);
        imagedestroy($image);
    }

    /**
     * @return array<int, string>
     */
    protected function wrapText(string $text, int $maxCharsPerLine): array
    {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
        $words = explode(' ', $text);
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if (mb_strlen($candidate) <= $maxCharsPerLine) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
            }
            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return array_slice($lines, 0, 12);
    }

    protected function truncate(string $text, int $max): string
    {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        return mb_strlen($text) <= $max ? $text : mb_substr($text, 0, $max - 1).'…';
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

        Storage::disk('public')->put($dest, $jpeg);

        return $dest;
    }
}
