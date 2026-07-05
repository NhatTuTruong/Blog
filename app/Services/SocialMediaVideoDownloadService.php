<?php

namespace App\Services;

use App\Support\ApifySettings;
use App\Support\ApifyTokenRotator;
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

        $timeout = $this->downloadTimeoutSeconds($videoUrl);
        $retries = max(1, (int) config('apify.video_download_retries', 3));

        if ($this->isApifyKeyValueStoreUrl($videoUrl)) {
            return $this->downloadApifyKeyValueStore($videoUrl, $destAbsolute, $timeout, $retries, $userId);
        }

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            if ($this->attemptDownload($videoUrl, $destAbsolute, $timeout)) {
                return true;
            }

            if ($attempt < $retries) {
                sleep(min(5, $attempt * 2));
            }
        }

        return false;
    }

    protected function downloadApifyKeyValueStore(
        string $videoUrl,
        string $destAbsolute,
        int $timeout,
        int $retries,
        ?int $userId,
    ): bool {
        $baseUrl = preg_replace('/([?&])token=[^&]*/', '', $videoUrl) ?? $videoUrl;
        $baseUrl = rtrim($baseUrl, '?&');

        $tokenError = null;
        $downloaded = ApifyTokenRotator::attempt(
            $userId,
            function (string $token) use ($baseUrl, $destAbsolute, $timeout, $retries): array {
                $url = $baseUrl.(str_contains($baseUrl, '?') ? '&' : '?').'token='.rawurlencode($token);

                for ($attempt = 1; $attempt <= $retries; $attempt++) {
                    [$success, $httpStatus] = $this->attemptDownloadWithStatus($url, $destAbsolute, $timeout);

                    if ($success) {
                        return ApifyTokenRotator::result(true);
                    }

                    if ($httpStatus !== null && ApifySettings::shouldRotateToken($httpStatus, (string) $this->lastError)) {
                        return ApifyTokenRotator::result(false, true, $this->lastError);
                    }

                    if ($attempt < $retries) {
                        sleep(min(5, $attempt * 2));
                    }
                }

                return ApifyTokenRotator::result(false);
            },
            $tokenError,
            'ApifyVideoDownload',
        );

        if ($downloaded !== true && $tokenError !== null) {
            $this->lastError = $tokenError;
        }

        return $downloaded === true;
    }

    /**
     * @return array{0: bool, 1: ?int}
     */
    protected function attemptDownloadWithStatus(string $videoUrl, string $destAbsolute, int $timeout): array
    {
        $tempPath = $destAbsolute.'.part';
        $headers = $this->downloadHeaders($videoUrl);

        if (is_file($tempPath)) {
            @unlink($tempPath);
        }

        $httpStatus = null;

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(min(30, $timeout))
                ->withHeaders($headers)
                ->withOptions(['sink' => $tempPath])
                ->get($videoUrl);

            $httpStatus = $response->status();

            if (! $response->successful()) {
                $this->lastError = 'Tải video HTTP '.$httpStatus.'.';

                return [false, $httpStatus];
            }

            if (! is_file($tempPath)) {
                $this->lastError = 'Không ghi được file video tạm.';

                return [false, $httpStatus];
            }

            $size = filesize($tempPath);
            if ($size === false || $size < 10_000) {
                $this->lastError = 'File video tải về quá nhỏ hoặc không hợp lệ.';

                return [false, $httpStatus];
            }

            if (is_file($destAbsolute)) {
                @unlink($destAbsolute);
            }

            if (! @rename($tempPath, $destAbsolute)) {
                if (! @copy($tempPath, $destAbsolute)) {
                    $this->lastError = 'Không lưu được file video.';

                    return [false, $httpStatus];
                }

                @unlink($tempPath);
            }

            return [true, $httpStatus];
        } catch (\Throwable $e) {
            $this->lastError = 'Lỗi tải video: '.$e->getMessage();
            Log::warning('SocialMediaVideoDownloadService failed', [
                'url' => $this->redactUrl($videoUrl),
                'error' => $e->getMessage(),
            ]);

            return [false, $httpStatus];
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    protected function isApifyKeyValueStoreUrl(string $videoUrl): bool
    {
        return str_contains(strtolower($videoUrl), 'api.apify.com/v2/key-value-stores');
    }

    protected function attemptDownload(string $videoUrl, string $destAbsolute, int $timeout): bool
    {
        [$success] = $this->attemptDownloadWithStatus($videoUrl, $destAbsolute, $timeout);

        return $success;
    }

    protected function downloadTimeoutSeconds(string $videoUrl): int
    {
        if (str_contains(strtolower($videoUrl), 'api.apify.com')) {
            return max(180, (int) config('apify.video_download_timeout_seconds', 600));
        }

        return max(60, (int) config('apify.video_download_timeout_seconds', 600));
    }

    protected function redactUrl(string $url): string
    {
        return preg_replace('/token=[^&]+/', 'token=***', $url) ?? $url;
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
                'url' => $this->redactUrl($imageUrl),
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
