<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

class SocialMediaPublicUrlValidator
{
    /**
     * @return array{0: ?string, 1: ?string} [error, contentType]
     */
    public static function validateImageUrl(string $url): array
    {
        return static::validateMediaUrl($url, 'image');
    }

    /**
     * @return array{0: ?string, 1: ?string} [error, contentType]
     */
    public static function validateVideoUrl(string $url): array
    {
        return static::validateMediaUrl($url, 'video');
    }

    /**
     * @return array{0: ?string, 1: ?string} [error, contentType]
     */
    protected static function validateMediaUrl(string $url, string $kind): array
    {
        try {
            $response = Http::timeout(25)
                ->withHeaders(static::probeHeaders())
                ->withOptions(['stream' => true])
                ->get($url);

            if (! $response->successful()) {
                return [
                    'Meta không truy cập được URL '.($kind === 'video' ? 'video' : 'ảnh').' (HTTP '.$response->status().'). Kiểm tra URL công khai HTTPS.',
                    null,
                ];
            }

            $contentType = strtolower((string) $response->header('Content-Type', ''));
            $body = $response->body();
            $sample = substr($body, 0, min(strlen($body), 2048));

            if (static::looksLikeTunnelInterstitial($sample, $contentType)) {
                return [
                    'URL media bị chặn bởi trang cảnh báo tunnel (ngrok/cloudflare). Meta không tải được file. '
                    .'Dùng domain HTTPS thật, ngrok gói trả phí/static domain, hoặc cloudflared — không dùng ngrok miễn phí.',
                    $contentType,
                ];
            }

            $validByType = $kind === 'video'
                ? static::contentTypeLooksLikeVideo($contentType)
                : static::contentTypeLooksLikeImage($contentType);

            $validByMagic = $kind === 'video'
                ? static::bodyLooksLikeVideo($sample)
                : static::bodyLooksLikeImage($sample);

            if (! $validByType && ! $validByMagic) {
                $label = $kind === 'video' ? 'video' : 'JPEG/PNG';

                return [
                    'URL '.($kind === 'video' ? 'video' : 'ảnh').' trả về Content-Type không phải '.$label
                    .($contentType !== '' ? ' ('.$contentType.')' : '').'.',
                    $contentType,
                ];
            }

            return [null, $contentType !== '' ? $contentType : ($kind === 'video' ? 'video/mp4' : 'image/jpeg')];
        } catch (\Throwable $e) {
            return [
                'Không kiểm tra được URL '.($kind === 'video' ? 'video' : 'ảnh').': '.$e->getMessage(),
                null,
            ];
        }
    }

    /**
     * @return array<string, string>
     */
    protected static function probeHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (compatible; MetaMediaBot/1.0)',
            'Accept' => '*/*',
            'Range' => 'bytes=0-2047',
            'ngrok-skip-browser-warning' => '1',
        ];
    }

    protected static function contentTypeLooksLikeImage(string $contentType): bool
    {
        if ($contentType === '') {
            return false;
        }

        foreach (['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'] as $allowed) {
            if (str_contains($contentType, $allowed)) {
                return true;
            }
        }

        return false;
    }

    protected static function contentTypeLooksLikeVideo(string $contentType): bool
    {
        return $contentType !== '' && str_contains($contentType, 'video/');
    }

    protected static function bodyLooksLikeImage(string $sample): bool
    {
        if ($sample === '') {
            return false;
        }

        if (str_starts_with($sample, "\xFF\xD8\xFF")) {
            return true;
        }

        if (str_starts_with($sample, "\x89PNG\r\n\x1A\n")) {
            return true;
        }

        if (str_starts_with($sample, 'GIF87a') || str_starts_with($sample, 'GIF89a')) {
            return true;
        }

        return str_starts_with($sample, 'RIFF') && str_contains(substr($sample, 0, 16), 'WEBP');
    }

    protected static function bodyLooksLikeVideo(string $sample): bool
    {
        if (strlen($sample) < 12) {
            return false;
        }

        return substr($sample, 4, 4) === 'ftyp';
    }

    protected static function looksLikeTunnelInterstitial(string $sample, string $contentType): bool
    {
        if ($sample === '') {
            return false;
        }

        if (static::bodyLooksLikeImage($sample) || static::bodyLooksLikeVideo($sample)) {
            return false;
        }

        $lower = strtolower($sample);

        $needles = [
            'ngrok',
            'you are about to visit',
            'cloudflare',
            'please enable cookies',
            'browser verification',
            '<!doctype html',
            '<html',
        ];

        foreach ($needles as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return str_contains($contentType, 'text/plain') || str_contains($contentType, 'text/html');
    }
}
