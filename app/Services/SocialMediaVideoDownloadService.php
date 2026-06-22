<?php

namespace App\Services;

use App\Support\ApifySettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tải video qua Laravel HTTP client (Guzzle).
 */
class SocialMediaVideoDownloadService
{
    public ?string $lastError = null;

    public function downloadToFile(string $videoUrl, string $destAbsolute, ?int $userId = null): bool
    {
        $this->lastError = null;
        $videoUrl = $this->prepareDownloadUrl(trim($videoUrl), $userId);

        if ($videoUrl === '') {
            $this->lastError = 'URL video trống.';

            return false;
        }

        $directory = dirname(str_replace('\\', '/', $destAbsolute));
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->lastError = 'Không tạo được thư mục lưu video.';

            return false;
        }

        $tempPath = $destAbsolute.'.part';
        $headers = $this->downloadHeaders($videoUrl);

        try {
            $response = Http::timeout(180)
                ->withHeaders($headers)
                ->withOptions(['sink' => $tempPath])
                ->get($videoUrl);

            if (! $response->successful()) {
                $this->lastError = 'Tải video HTTP '.$response->status().'.';

                return false;
            }

            if (! is_file($tempPath)) {
                $this->lastError = 'Không ghi được file video tạm.';

                return false;
            }

            $size = filesize($tempPath);
            if ($size === false || $size < 10_000) {
                $this->lastError = 'File video tải về quá nhỏ hoặc không hợp lệ.';

                return false;
            }

            if (is_file($destAbsolute)) {
                @unlink($destAbsolute);
            }

            if (! @rename($tempPath, $destAbsolute)) {
                if (! @copy($tempPath, $destAbsolute)) {
                    $this->lastError = 'Không lưu được file video.';

                    return false;
                }

                @unlink($tempPath);
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastError = 'Lỗi tải video: '.$e->getMessage();
            Log::warning('SocialMediaVideoDownloadService failed', [
                'url' => $videoUrl,
                'error' => $e->getMessage(),
            ]);

            return false;
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    public function downloadImageToFile(string $imageUrl, string $destAbsolute, ?int $userId = null): bool
    {
        $this->lastError = null;
        $imageUrl = $this->prepareDownloadUrl(trim($imageUrl), $userId);

        if ($imageUrl === '') {
            $this->lastError = 'URL ảnh trống.';

            return false;
        }

        $directory = dirname(str_replace('\\', '/', $destAbsolute));
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->lastError = 'Không tạo được thư mục lưu ảnh.';

            return false;
        }

        $tempPath = $destAbsolute.'.part';
        $headers = $this->downloadHeaders($imageUrl);

        try {
            $response = Http::timeout(60)
                ->withHeaders($headers)
                ->get($imageUrl);

            if (! $response->successful()) {
                $this->lastError = 'Tải ảnh HTTP '.$response->status().'.';

                return false;
            }

            $body = $response->body();
            if ($body === '' || strlen($body) < 500) {
                $this->lastError = 'Ảnh cover tải về quá nhỏ hoặc không hợp lệ.';

                return false;
            }

            file_put_contents($tempPath, $body);

            if (is_file($destAbsolute)) {
                @unlink($destAbsolute);
            }

            if (! @rename($tempPath, $destAbsolute)) {
                if (! @copy($tempPath, $destAbsolute)) {
                    $this->lastError = 'Không lưu được ảnh cover.';

                    return false;
                }

                @unlink($tempPath);
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastError = 'Lỗi tải ảnh: '.$e->getMessage();
            Log::warning('SocialMediaVideoDownloadService image failed', [
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);

            return false;
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    protected function prepareDownloadUrl(string $videoUrl, ?int $userId): string
    {
        if ($videoUrl === '') {
            return '';
        }

        if (! str_contains(strtolower($videoUrl), 'api.apify.com/v2/key-value-stores')) {
            return $videoUrl;
        }

        if (str_contains($videoUrl, 'token=')) {
            return $videoUrl;
        }

        $token = ApifySettings::apiToken($userId);
        if ($token === null) {
            return $videoUrl;
        }

        return $videoUrl.(str_contains($videoUrl, '?') ? '&' : '?').'token='.rawurlencode($token);
    }

    /**
     * @return array<string, string>
     */
    protected function downloadHeaders(string $videoUrl): array
    {
        if (str_contains(strtolower($videoUrl), 'api.apify.com')) {
            return [
                'Accept' => '*/*',
                'User-Agent' => 'Mozilla/5.0 (compatible; SocialMediaBot/1.0)',
            ];
        }

        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Referer' => 'https://www.tiktok.com/',
            'Accept' => '*/*',
        ];
    }
}
