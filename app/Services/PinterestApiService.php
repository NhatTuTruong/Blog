<?php

namespace App\Services;

use App\Models\PinterestAccount;
use App\Support\PinterestSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PinterestApiService
{
    public ?string $lastError = null;

    protected ?PinterestAccount $account = null;

    protected ?string $boardIdOverride = null;

    /** @var array<int, array<string, string>> */
    protected static array $boardListCache = [];

    /** @var array{media_id?: string, upload_url?: string, upload_parameters?: array<string, string>}|null */
    protected ?array $lastVideoUpload = null;

    public function forAccount(PinterestAccount $account): self
    {
        $this->account = $account;
        $this->boardIdOverride = null;
        $this->lastError = null;

        return $this;
    }

    public function forBoard(string $boardId): self
    {
        $boardId = trim($boardId);
        $this->boardIdOverride = $boardId !== '' ? $boardId : null;

        return $this;
    }

    protected function activeAccount(): ?PinterestAccount
    {
        return $this->account;
    }

    protected function baseUrl(): string
    {
        return PinterestSettings::apiBaseUrl();
    }

    protected function boardId(): ?string
    {
        if (filled($this->boardIdOverride)) {
            return $this->boardIdOverride;
        }

        $id = trim((string) ($this->activeAccount()?->board_id ?? ''));

        return $id !== '' ? $id : null;
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->withToken((string) $this->activeAccount()?->normalizedAccessToken())
            ->connectTimeout(30)
            ->timeout(180);
    }

    /**
     * @return array{id: string, username?: string, board_count?: int}|null
     */
    public function testConnection(?PinterestAccount $account = null): ?array
    {
        $this->lastError = null;

        if ($account !== null) {
            $this->account = $account;
        }

        if ($this->activeAccount() === null || ! $this->activeAccount()->isConfigured()) {
            $this->lastError = 'Pinterest chưa được cấu hình — cần Access Token.';

            return null;
        }

        $response = $this->http()->get($this->baseUrl().'/user_account');

        if (! $response->successful()) {
            $this->lastError = $this->formatApiError($response->json(), $response->status());

            return null;
        }

        $data = $response->json();
        $profile = [
            'id' => (string) ($data['id'] ?? $data['username'] ?? ''),
            'username' => isset($data['username']) ? (string) $data['username'] : null,
            'board_count' => count($this->listBoardOptions()),
        ];

        $this->syncAccountProfile($profile);

        return $profile;
    }

    /**
     * @return array<string, string> board_id => board_name
     */
    public function listBoardOptions(bool $useCache = true): array
    {
        $this->lastError = null;

        if ($this->activeAccount() === null || ! $this->activeAccount()->isConfigured()) {
            $this->lastError = 'Pinterest chưa được cấu hình — cần Access Token.';

            return [];
        }

        $accountId = (int) $this->activeAccount()->id;
        if ($useCache && isset(self::$boardListCache[$accountId])) {
            return self::$boardListCache[$accountId];
        }

        $options = [];
        $bookmark = null;

        do {
            $params = ['page_size' => 250];
            if (filled($bookmark)) {
                $params['bookmark'] = $bookmark;
            }

            $response = $this->http()->get($this->baseUrl().'/boards', $params);

            if (! $response->successful()) {
                $this->lastError = $this->formatApiError($response->json(), $response->status());

                return $options;
            }

            foreach ($response->json('items', []) as $board) {
                if (! is_array($board)) {
                    continue;
                }

                $id = (string) ($board['id'] ?? '');
                if ($id === '') {
                    continue;
                }

                $options[$id] = (string) ($board['name'] ?? $id);
            }

            $bookmark = $response->json('bookmark');
        } while (filled($bookmark));

        self::$boardListCache[$accountId] = $options;

        return $options;
    }

    /**
     * @param  array{id?: string, username?: string|null, board_count?: int|null}  $profile
     */
    protected function syncAccountProfile(array $profile): void
    {
        if ($this->account === null) {
            return;
        }

        $updates = array_filter([
            'username' => $profile['username'] ?? null,
        ], fn (mixed $value): bool => filled($value));

        if ($updates !== []) {
            $this->account->update($updates);
        }
    }

    public function publishImagePin(
        string $imageUrl,
        string $title,
        string $description,
        ?string $link = null,
    ): ?string {
        $this->lastError = null;

        $boardId = $this->boardId();
        if ($boardId === null || $this->activeAccount() === null || ! $this->activeAccount()->isConfigured()) {
            $this->lastError = 'Chưa chọn Board Pinterest để đăng Pin.';

            return null;
        }

        $payload = [
            'board_id' => $boardId,
            'title' => Str::limit($title, 100, ''),
            'description' => Str::limit($description, 800, ''),
            'media_source' => [
                'source_type' => 'image_url',
                'url' => $imageUrl,
            ],
        ];

        if (filled($link)) {
            $payload['link'] = $link;
        }

        $response = $this->http()->post($this->baseUrl().'/pins', $payload);

        if (! $response->successful()) {
            $this->lastError = $this->formatApiError($response->json(), $response->status());
            Log::warning('PinterestApiService image pin failed', [
                'status' => $response->status(),
                'body' => $response->json(),
                'image_url' => $imageUrl,
            ]);

            return null;
        }

        $pinId = (string) ($response->json('id') ?? '');

        return $pinId !== '' ? $pinId : null;
    }

    public function publishVideoPin(
        string $videoAbsolutePath,
        string $coverImageUrl,
        string $title,
        string $description,
        ?string $link = null,
    ): ?string {
        $this->lastError = null;

        $boardId = $this->boardId();
        if ($boardId === null || $this->activeAccount() === null || ! $this->activeAccount()->isConfigured()) {
            $this->lastError = 'Chưa chọn Board Pinterest để đăng Pin.';

            return null;
        }

        if (! is_file($videoAbsolutePath)) {
            $this->lastError = 'File video không tồn tại trên máy chủ.';

            return null;
        }

        $mediaId = $this->registerVideoUpload();
        if ($mediaId === null) {
            return null;
        }

        if (! $this->uploadVideoFile($mediaId, $videoAbsolutePath)) {
            return null;
        }

        if (! $this->waitForVideoReady($mediaId)) {
            return null;
        }

        $payload = [
            'board_id' => $boardId,
            'title' => Str::limit($title, 100, ''),
            'description' => Str::limit($description, 800, ''),
            'media_source' => [
                'source_type' => 'video_id',
                'cover_image_url' => $coverImageUrl,
                'media_id' => $mediaId,
            ],
        ];

        if (filled($link)) {
            $payload['link'] = $link;
        }

        $response = $this->http()->post($this->baseUrl().'/pins', $payload);

        if (! $response->successful()) {
            $this->lastError = $this->formatApiError($response->json(), $response->status());
            Log::warning('PinterestApiService video pin failed', [
                'status' => $response->status(),
                'body' => $response->json(),
                'media_id' => $mediaId,
            ]);

            return null;
        }

        $pinId = (string) ($response->json('id') ?? '');

        return $pinId !== '' ? $pinId : null;
    }

    protected function registerVideoUpload(): ?string
    {
        $response = $this->http()->post($this->baseUrl().'/media', [
            'media_type' => 'video',
        ]);

        if (! $response->successful()) {
            $this->lastError = $this->formatApiError($response->json(), $response->status());

            return null;
        }

        $mediaId = (string) ($response->json('media_id') ?? '');

        if ($mediaId === '') {
            $this->lastError = 'Pinterest không trả về media_id khi đăng ký video.';

            return null;
        }

        $this->lastVideoUpload = [
            'media_id' => $mediaId,
            'upload_url' => (string) ($response->json('upload_url') ?? ''),
            'upload_parameters' => is_array($response->json('upload_parameters'))
                ? $response->json('upload_parameters')
                : [],
        ];

        return $mediaId;
    }

    protected function uploadVideoFile(string $mediaId, string $videoAbsolutePath): bool
    {
        $uploadUrl = (string) ($this->lastVideoUpload['upload_url'] ?? '');
        $uploadParameters = $this->lastVideoUpload['upload_parameters'] ?? [];

        if ($uploadUrl === '' || ! is_array($uploadParameters)) {
            $this->lastError = 'Pinterest không cung cấp upload_url cho video.';

            return false;
        }

        $multipart = [];
        foreach ($uploadParameters as $key => $value) {
            $multipart[] = [
                'name' => (string) $key,
                'contents' => (string) $value,
            ];
        }

        $multipart[] = [
            'name' => 'file',
            'contents' => fopen($videoAbsolutePath, 'r'),
            'filename' => basename($videoAbsolutePath),
        ];

        $uploadResponse = Http::asMultipart()
            ->timeout(300)
            ->post($uploadUrl, $multipart);

        if (! $uploadResponse->successful() && $uploadResponse->status() !== 204) {
            $this->lastError = 'Upload video lên Pinterest thất bại (HTTP '.$uploadResponse->status().').';

            return false;
        }

        return true;
    }

    protected function waitForVideoReady(string $mediaId, int $maxAttempts = 30): bool
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $response = $this->http()->get($this->baseUrl().'/media/'.$mediaId);

            if (! $response->successful()) {
                $this->lastError = $this->formatApiError($response->json(), $response->status());

                return false;
            }

            $status = strtolower((string) ($response->json('status') ?? ''));

            if (in_array($status, ['succeeded', 'ready', 'complete', 'completed'], true)) {
                return true;
            }

            if (in_array($status, ['failed', 'error'], true)) {
                $this->lastError = 'Pinterest xử lý video thất bại.';

                return false;
            }

            sleep(2);
        }

        $this->lastError = 'Pinterest xử lý video quá lâu — thử lại sau.';

        return false;
    }

    protected function formatApiError(mixed $body, int $status): string
    {
        $message = (string) data_get($body, 'message', data_get($body, 'error.message', 'Unknown error'));
        $code = data_get($body, 'code', data_get($body, 'error.code'));

        $parts = ["HTTP {$status}: {$message}"];
        if ($code !== null) {
            $parts[] = "code={$code}";
        }

        return implode(' ', $parts);
    }
}
