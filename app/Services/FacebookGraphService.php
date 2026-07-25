<?php

namespace App\Services;

use App\Models\FacebookAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookGraphService
{
    public ?string $lastError = null;

    protected ?FacebookAccount $account = null;

    protected ?string $resolvedPageAccessToken = null;

    public function forAccount(FacebookAccount $account): self
    {
        $this->account = $account;
        $this->lastError = null;
        $this->resolvedPageAccessToken = null;

        return $this;
    }

    protected function activeAccount(): ?FacebookAccount
    {
        return $this->account;
    }

    protected function baseUrl(): string
    {
        return 'https://graph.facebook.com/'.ltrim(\App\Support\FacebookSettings::graphVersion(), '/');
    }

    protected function pageId(): ?string
    {
        $id = trim((string) ($this->activeAccount()?->page_id ?? ''));

        return $id !== '' ? $id : null;
    }

    protected function storedAccessToken(): ?string
    {
        return $this->activeAccount()?->normalizedAccessToken();
    }

    /**
     * Meta yêu cầu Page Access Token để đăng bài — User token (dù có pages_manage_posts) sẽ lỗi publish_actions.
     */
    protected function pageAccessToken(): ?string
    {
        if ($this->resolvedPageAccessToken !== null) {
            return $this->resolvedPageAccessToken;
        }

        $stored = $this->storedAccessToken();
        $pageId = $this->pageId();

        if ($stored === null || $pageId === null) {
            return null;
        }

        $pageToken = $this->fetchPageAccessTokenFromAccounts($stored, $pageId);
        $this->resolvedPageAccessToken = $pageToken ?? $stored;

        return $this->resolvedPageAccessToken;
    }

    protected function fetchPageAccessTokenFromAccounts(string $token, string $pageId): ?string
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout(30)
                ->timeout(60)
                ->get($this->baseUrl().'/me/accounts', [
                    'fields' => 'id,name,access_token',
                    'limit' => 100,
                    'access_token' => $token,
                ]);
        } catch (\Throwable $e) {
            Log::info('FacebookGraphService /me/accounts unavailable', [
                'error' => $e->getMessage(),
                'page_id' => $pageId,
            ]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        foreach ($response->json('data', []) as $page) {
            if (! is_array($page)) {
                continue;
            }

            if ((string) ($page['id'] ?? '') === $pageId) {
                $pageToken = trim((string) ($page['access_token'] ?? ''));

                return $pageToken !== '' ? $pageToken : null;
            }
        }

        return null;
    }

    protected function persistResolvedPageTokenIfChanged(): void
    {
        if ($this->account === null) {
            return;
        }

        $stored = $this->storedAccessToken();
        $resolved = $this->pageAccessToken();

        if ($stored === null || $resolved === null || hash_equals($stored, $resolved)) {
            return;
        }

        $this->account->update(['access_token' => $resolved]);
        $this->account = $this->account->fresh();
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->connectTimeout(30)
            ->timeout(120);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function graphGet(string $path, array $query = []): Response
    {
        $token = $this->pageAccessToken();
        if ($token !== null) {
            $query['access_token'] = $token;
        }

        return $this->http()->get($this->baseUrl().$path, $query);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function graphPost(string $path, array $data = []): Response
    {
        $token = $this->pageAccessToken();
        if ($token !== null) {
            $data['access_token'] = $token;
        }

        return $this->http()->post($this->baseUrl().$path, $data);
    }

    /**
     * @return array{id: string, name?: string, token_upgraded?: bool}|null
     */
    public function testConnection(?FacebookAccount $account = null): ?array
    {
        $this->lastError = null;

        if ($account !== null) {
            $this->account = $account;
            $this->resolvedPageAccessToken = null;
        }

        if ($this->activeAccount() === null || ! $this->activeAccount()->isConfigured()) {
            $this->lastError = 'Facebook Page chưa được cấu hình — cần Page ID và Access Token.';

            return null;
        }

        $pageId = $this->pageId();
        if ($pageId === null) {
            $this->lastError = 'Thiếu Facebook Page ID.';

            return null;
        }

        $storedBefore = $this->storedAccessToken();
        $resolvedBefore = $this->pageAccessToken();

        if ($resolvedBefore === null) {
            $this->lastError = 'Thiếu Access Token hợp lệ.';

            return null;
        }

        $tokenUpgraded = $resolvedBefore !== $storedBefore;
        $this->persistResolvedPageTokenIfChanged();

        $response = $this->graphGet('/'.$pageId, [
            'fields' => 'id,name',
        ]);

        if (! $response->successful()) {
            $this->lastError = $this->formatGraphError($response->json(), $response->status());

            return null;
        }

        $data = $response->json();

        $profile = [
            'id' => (string) ($data['id'] ?? $pageId),
            'name' => isset($data['name']) ? (string) $data['name'] : null,
            'token_upgraded' => $tokenUpgraded,
        ];

        $this->syncAccountProfile($profile);

        return $profile;
    }

    /**
     * @param  array{id: string, name?: string|null, token_upgraded?: bool}  $profile
     */
    protected function syncAccountProfile(array $profile): void
    {
        if ($this->account === null) {
            return;
        }

        $updates = array_filter([
            'page_name' => $profile['name'] ?? null,
            'page_id' => $profile['id'] ?? null,
        ], fn (mixed $value): bool => filled($value));

        if ($updates !== []) {
            $this->account->update($updates);
        }
    }

    public function publishPhoto(string $imageUrl, string $message): ?string
    {
        $this->lastError = null;

        $pageId = $this->pageId();
        if ($pageId === null || $this->activeAccount() === null || ! $this->activeAccount()->isConfigured()) {
            $this->lastError = 'Facebook Page chưa được cấu hình đầy đủ.';

            return null;
        }

        $this->persistResolvedPageTokenIfChanged();

        $response = $this->graphPost('/'.$pageId.'/photos', [
            'url' => $imageUrl,
            'caption' => $message,
            'published' => true,
        ]);

        if (! $response->successful()) {
            $this->lastError = $this->formatGraphError($response->json(), $response->status());
            Log::warning('FacebookGraphService photo failed', [
                'status' => $response->status(),
                'body' => $response->json(),
                'image_url' => $imageUrl,
                'page_id' => $pageId,
            ]);

            return null;
        }

        $postId = (string) ($response->json('post_id') ?? $response->json('id') ?? '');

        return $postId !== '' ? $postId : null;
    }

    public function publishPhotoFromPath(string $absolutePath, string $message): ?string
    {
        $this->lastError = null;

        $pageId = $this->pageId();
        if ($pageId === null || $this->activeAccount() === null || ! $this->activeAccount()->isConfigured()) {
            $this->lastError = 'Facebook Page chưa được cấu hình đầy đủ.';

            return null;
        }

        if (! is_file($absolutePath)) {
            $this->lastError = 'File ảnh không tồn tại trên máy chủ.';

            return null;
        }

        $this->persistResolvedPageTokenIfChanged();

        $token = $this->pageAccessToken();
        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            $this->lastError = 'Không đọc được file ảnh trên máy chủ.';

            return null;
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(30)
                ->timeout(300)
                ->attach('source', $handle, basename($absolutePath))
                ->post($this->baseUrl().'/'.$pageId.'/photos', [
                    'caption' => $message,
                    'published' => true,
                    'access_token' => $token,
                ]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if (! $response->successful()) {
            $this->lastError = $this->formatGraphError($response->json(), $response->status());
            Log::warning('FacebookGraphService photo upload failed', [
                'status' => $response->status(),
                'body' => $response->json(),
                'path' => $absolutePath,
                'page_id' => $pageId,
            ]);

            return null;
        }

        $postId = (string) ($response->json('post_id') ?? $response->json('id') ?? '');

        return $postId !== '' ? $postId : null;
    }

    public function publishVideo(string $videoUrl, string $message): ?string
    {
        $this->lastError = null;

        $pageId = $this->pageId();
        if ($pageId === null || $this->activeAccount() === null || ! $this->activeAccount()->isConfigured()) {
            $this->lastError = 'Facebook Page chưa được cấu hình đầy đủ.';

            return null;
        }

        $this->persistResolvedPageTokenIfChanged();

        $response = $this->graphPost('/'.$pageId.'/videos', [
            'file_url' => $videoUrl,
            'description' => $message,
            'published' => true,
        ]);

        if (! $response->successful()) {
            $this->lastError = $this->formatGraphError($response->json(), $response->status());
            Log::warning('FacebookGraphService video failed', [
                'status' => $response->status(),
                'body' => $response->json(),
                'video_url' => $videoUrl,
                'page_id' => $pageId,
            ]);

            return null;
        }

        $postId = (string) ($response->json('id') ?? '');

        return $postId !== '' ? $postId : null;
    }

    public function publishVideoFromPath(string $absolutePath, string $message): ?string
    {
        $this->lastError = null;

        $pageId = $this->pageId();
        if ($pageId === null || $this->activeAccount() === null || ! $this->activeAccount()->isConfigured()) {
            $this->lastError = 'Facebook Page chưa được cấu hình đầy đủ.';

            return null;
        }

        if (! is_file($absolutePath)) {
            $this->lastError = 'File video không tồn tại trên máy chủ.';

            return null;
        }

        $this->persistResolvedPageTokenIfChanged();

        $token = $this->pageAccessToken();
        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            $this->lastError = 'Không đọc được file video trên máy chủ.';

            return null;
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(30)
                ->timeout(600)
                ->attach('source', $handle, basename($absolutePath))
                ->post($this->baseUrl().'/'.$pageId.'/videos', [
                    'description' => $message,
                    'published' => true,
                    'access_token' => $token,
                ]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if (! $response->successful()) {
            $this->lastError = $this->formatGraphError($response->json(), $response->status());
            Log::warning('FacebookGraphService video upload failed', [
                'status' => $response->status(),
                'body' => $response->json(),
                'path' => $absolutePath,
                'page_id' => $pageId,
            ]);

            return null;
        }

        $postId = (string) ($response->json('id') ?? '');

        return $postId !== '' ? $postId : null;
    }

    public function publishFeed(string $message, ?string $link = null): ?string
    {
        $this->lastError = null;

        $pageId = $this->pageId();
        if ($pageId === null || $this->activeAccount() === null || ! $this->activeAccount()->isConfigured()) {
            $this->lastError = 'Facebook Page chưa được cấu hình đầy đủ.';

            return null;
        }

        $this->persistResolvedPageTokenIfChanged();

        $payload = [
            'message' => $message,
            'published' => true,
        ];
        if (filled($link)) {
            $payload['link'] = $link;
        }

        $response = $this->graphPost('/'.$pageId.'/feed', $payload);

        if (! $response->successful()) {
            $this->lastError = $this->formatGraphError($response->json(), $response->status());

            return null;
        }

        $postId = (string) ($response->json('id') ?? '');

        return $postId !== '' ? $postId : null;
    }

    protected function formatGraphError(mixed $body, int $status): string
    {
        $message = (string) data_get($body, 'error.message', 'Unknown error');
        $code = data_get($body, 'error.code');
        $subcode = data_get($body, 'error.error_subcode');

        if ($this->isPublishActionsDeprecatedError($message, $code)) {
            $message = 'Token đang là User Access Token (hoặc sai Page). Meta không còn cho đăng bằng publish_actions. '
                .'Cần Page Access Token của đúng Page ID: vào developers.facebook.com → Graph API Explorer → chọn App + User token có pages_manage_posts → '
                .'gọi GET /me/accounts?fields=id,name,access_token → copy access_token của Page (EAA…). '
                .'Hoặc nhập User token hợp lệ — hệ thống sẽ tự đổi sang Page token khi Test kết nối.';
        }

        $parts = ["HTTP {$status}"];
        if (! empty($message) && $message !== 'Unknown error') {
            $parts[] = $message;
        }
        if ($code !== null) {
            $parts[] = "error_code={$code}";
        }
        if ($subcode !== null) {
            $parts[] = "subcode={$subcode}";
        }

        return implode(' | ', $parts);
    }

    protected function isPublishActionsDeprecatedError(string $message, mixed $code): bool
    {
        $lower = strtolower($message);

        return (int) $code === 200
            && (str_contains($lower, 'publish_actions') || str_contains($lower, 'sharing products'));
    }
}
