<?php

namespace App\Services;

use App\Models\InstagramAccount;
use App\Support\InstagramSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramGraphService
{
    public ?string $lastError = null;

    protected ?InstagramAccount $account = null;

    public function forAccount(InstagramAccount $account): self
    {
        $this->account = $account;
        $this->lastError = null;

        return $this;
    }

    protected function activeAccount(): ?InstagramAccount
    {
        return $this->account ?? InstagramSettings::primaryAccount();
    }

    protected function baseUrl(): string
    {
        return 'https://'.$this->apiHost().'/'.ltrim(InstagramSettings::graphVersion(), '/');
    }

    protected function apiHost(): string
    {
        return InstagramSettings::apiHostForAccount($this->activeAccount());
    }

    protected function token(): ?string
    {
        return $this->activeAccount()?->normalizedAccessToken();
    }

    protected function configuredUserId(): ?string
    {
        $id = trim((string) ($this->activeAccount()?->user_id ?? ''));

        return $id !== '' ? $id : null;
    }

    protected function usesInstagramLoginApi(): bool
    {
        return $this->activeAccount()?->usesInstagramLoginApi() ?? false;
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->withToken((string) $this->token())
            ->connectTimeout(30)
            ->timeout(120);
    }

    /**
     * @return array{id: string, username?: string, name?: string}|null
     */
    public function testConnection(?InstagramAccount $account = null): ?array
    {
        $this->lastError = null;

        if ($account !== null) {
            $this->account = $account;
        }

        if ($this->activeAccount() === null || ! $this->activeAccount()->isConfigured()) {
            $this->lastError = 'Instagram chưa được bật hoặc thiếu token / User ID trong Cài đặt hệ thống.';

            return null;
        }

        if ($this->usesInstagramLoginApi()) {
            return $this->testInstagramLoginConnection();
        }

        return $this->testFacebookLoginConnection();
    }

    /**
     * @return array{id: string, username?: string, name?: string}|null
     */
    protected function testInstagramLoginConnection(): ?array
    {
        $response = $this->http()->get($this->baseUrl().'/me', [
            'fields' => 'user_id,username,name',
        ]);

        if (! $response->successful()) {
            $this->lastError = $this->formatGraphError($response->json(), $response->status());

            return null;
        }

        $data = $response->json();
        $userId = (string) ($data['user_id'] ?? $data['id'] ?? '');

        if ($userId === '') {
            $this->lastError = 'API không trả về user_id. Kiểm tra quyền token.';

            return null;
        }

        $configured = $this->configuredUserId();
        if ($configured !== null && $configured !== $userId) {
            $this->lastError = "User ID trong cài đặt ({$configured}) không khớp tài khoản token ({$userId}). Cập nhật User ID hoặc để trống để tự lấy từ token.";

            return null;
        }

        $profile = [
            'id' => $userId,
            'username' => isset($data['username']) ? (string) $data['username'] : null,
            'name' => isset($data['name']) ? (string) $data['name'] : null,
        ];

        $this->syncAccountProfile($profile);

        return $profile;
    }

    protected function syncAccountProfile(array $profile): void
    {
        if ($this->account === null) {
            return;
        }

        $updates = array_filter([
            'username' => $profile['username'] ?? null,
            'user_id' => $profile['id'] ?? null,
        ], fn (mixed $value): bool => filled($value));

        if ($updates !== []) {
            $this->account->update($updates);
        }
    }

    /**
     * @return array{id: string, username?: string, name?: string}|null
     */
    protected function testFacebookLoginConnection(): ?array
    {
        $userId = $this->configuredUserId();
        if ($userId === null) {
            $this->lastError = 'Cần Instagram User ID khi dùng token Facebook Login (EAA…).';

            return null;
        }

        $response = $this->http()->get($this->baseUrl().'/'.$userId, [
            'fields' => 'id,username,name',
        ]);

        if (! $response->successful()) {
            $this->lastError = $this->formatGraphError($response->json(), $response->status());

            return null;
        }

        $data = $response->json();

        $profile = [
            'id' => (string) ($data['id'] ?? ''),
            'username' => isset($data['username']) ? (string) $data['username'] : null,
            'name' => isset($data['name']) ? (string) $data['name'] : null,
        ];

        $this->syncAccountProfile($profile);

        return $profile;
    }

    public function publishImage(string $imageUrl, string $caption): ?string
    {
        $this->lastError = null;

        if ($this->activeAccount() === null || ! $this->activeAccount()->isConfigured()) {
            $this->lastError = 'Instagram chưa được cấu hình đầy đủ.';

            return null;
        }

        $userId = $this->resolvePublishUserId();
        if ($userId === null) {
            return null;
        }

        $payload = [
            'image_url' => $imageUrl,
            'caption' => $caption,
        ];

        if ($this->usesInstagramLoginApi()) {
            $payload['media_type'] = 'IMAGE';
        }

        $containerResponse = $this->http()->post($this->baseUrl().'/'.$userId.'/media', $payload);

        if (! $containerResponse->successful()) {
            $this->lastError = $this->formatGraphError($containerResponse->json(), $containerResponse->status());

            Log::warning('InstagramGraphService container failed', [
                'host' => $this->apiHost(),
                'status' => $containerResponse->status(),
                'body' => $containerResponse->json(),
                'image_url' => $imageUrl,
            ]);

            return null;
        }

        $creationId = (string) $containerResponse->json('id', '');
        if ($creationId === '') {
            $this->lastError = 'Meta API không trả về creation_id.';

            return null;
        }

        if (! $this->waitForContainerReady($creationId)) {
            return null;
        }

        $publishResponse = $this->http()->post($this->baseUrl().'/'.$userId.'/media_publish', [
            'creation_id' => $creationId,
        ]);

        if (! $publishResponse->successful()) {
            $this->lastError = $this->formatGraphError($publishResponse->json(), $publishResponse->status());

            Log::warning('InstagramGraphService publish failed', [
                'host' => $this->apiHost(),
                'status' => $publishResponse->status(),
                'body' => $publishResponse->json(),
                'creation_id' => $creationId,
            ]);

            return null;
        }

        $mediaId = (string) $publishResponse->json('id', '');
        if ($mediaId === '') {
            $this->lastError = 'Meta API không trả về media id sau khi publish.';

            return null;
        }

        return $mediaId;
    }

    public function publishVideo(string $videoUrl, string $caption): ?string
    {
        $this->lastError = null;

        if ($this->activeAccount() === null || ! $this->activeAccount()->isConfigured()) {
            $this->lastError = 'Instagram chưa được cấu hình đầy đủ.';

            return null;
        }

        $userId = $this->resolvePublishUserId();
        if ($userId === null) {
            return null;
        }

        $payload = [
            'video_url' => $videoUrl,
            'caption' => $caption,
        ];

        if ($this->usesInstagramLoginApi()) {
            $payload['media_type'] = 'REELS';
        }

        $containerResponse = $this->http()->post($this->baseUrl().'/'.$userId.'/media', $payload);

        if (! $containerResponse->successful()) {
            $this->lastError = $this->formatGraphError($containerResponse->json(), $containerResponse->status());

            Log::warning('InstagramGraphService video container failed', [
                'host' => $this->apiHost(),
                'status' => $containerResponse->status(),
                'body' => $containerResponse->json(),
                'video_url' => $videoUrl,
            ]);

            return null;
        }

        $creationId = (string) $containerResponse->json('id', '');
        if ($creationId === '') {
            $this->lastError = 'Meta API không trả về creation_id.';

            return null;
        }

        if (! $this->waitForVideoContainerReady($creationId)) {
            return null;
        }

        $publishResponse = $this->http()->post($this->baseUrl().'/'.$userId.'/media_publish', [
            'creation_id' => $creationId,
        ]);

        if (! $publishResponse->successful()) {
            $this->lastError = $this->formatGraphError($publishResponse->json(), $publishResponse->status());

            Log::warning('InstagramGraphService video publish failed', [
                'host' => $this->apiHost(),
                'status' => $publishResponse->status(),
                'body' => $publishResponse->json(),
                'creation_id' => $creationId,
            ]);

            return null;
        }

        $mediaId = (string) $publishResponse->json('id', '');
        if ($mediaId === '') {
            $this->lastError = 'Meta API không trả về media id sau khi publish video.';

            return null;
        }

        return $mediaId;
    }

    protected function waitForContainerReady(string $creationId): bool
    {
        for ($attempt = 0; $attempt < 15; $attempt++) {
            $response = $this->http()->get($this->baseUrl().'/'.$creationId, [
                'fields' => 'status_code,status',
            ]);

            if (! $response->successful()) {
                $this->lastError = $this->formatGraphError($response->json(), $response->status());

                return false;
            }

            $statusCode = (string) $response->json('status_code', '');

            if ($statusCode === 'FINISHED') {
                return true;
            }

            if ($statusCode === 'ERROR') {
                $this->lastError = 'Meta không xử lý được ảnh (status ERROR). Kiểm tra URL ảnh công khai HTTPS và định dạng JPEG.';

                return false;
            }

            sleep(2);
        }

        $this->lastError = 'Meta chưa sẵn sàng ảnh sau khi tạo container (timeout).';

        return false;
    }

    protected function waitForVideoContainerReady(string $creationId): bool
    {
        for ($attempt = 0; $attempt < 40; $attempt++) {
            $response = $this->http()->get($this->baseUrl().'/'.$creationId, [
                'fields' => 'status_code,status',
            ]);

            if (! $response->successful()) {
                $this->lastError = $this->formatGraphError($response->json(), $response->status());

                return false;
            }

            $statusCode = (string) $response->json('status_code', '');

            if ($statusCode === 'FINISHED') {
                return true;
            }

            if ($statusCode === 'ERROR') {
                $this->lastError = 'Meta không xử lý được video (status ERROR). Kiểm tra URL video công khai HTTPS và định dạng MP4/MOV.';

                return false;
            }

            sleep(3);
        }

        $this->lastError = 'Meta chưa sẵn sàng video sau khi tạo container (timeout).';

        return false;
    }

    protected function resolvePublishUserId(): ?string
    {
        if ($this->usesInstagramLoginApi()) {
            $configured = $this->configuredUserId();
            if ($configured !== null) {
                return $configured;
            }

            $profile = $this->testInstagramLoginConnection();

            return $profile['id'] ?? null;
        }

        $userId = $this->configuredUserId();
        if ($userId === null) {
            $this->lastError = 'Cần Instagram User ID khi dùng token Facebook Login (EAA…).';

            return null;
        }

        return $userId;
    }

    protected function formatGraphError(mixed $body, int $status): string
    {
        $message = (string) data_get($body, 'error.message', 'Unknown error');
        $code = data_get($body, 'error.code');
        $subcode = data_get($body, 'error.error_subcode');

        $parts = ["HTTP {$status}: {$message}"];
        if ($code !== null) {
            $parts[] = "code={$code}";
        }
        if ($subcode !== null) {
            $parts[] = "subcode={$subcode}";
        }

        $host = $this->apiHost();
        $parts[] = "host={$host}";

        if ((int) $code === 9004 || (int) $subcode === 2207052) {
            $parts[] = '(Meta không tải được ảnh — cần URL HTTPS công khai trả về file JPEG hợp lệ)';
        }

        return implode(' ', $parts);
    }
}

