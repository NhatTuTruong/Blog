<?php

namespace App\Services;

use App\Models\FacebookAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookGraphService
{
    public ?string $lastError = null;

    protected ?FacebookAccount $account = null;

    public function forAccount(FacebookAccount $account): self
    {
        $this->account = $account;
        $this->lastError = null;

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

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->withToken((string) $this->activeAccount()?->normalizedAccessToken())
            ->connectTimeout(30)
            ->timeout(120);
    }

    /**
     * @return array{id: string, name?: string}|null
     */
    public function testConnection(?FacebookAccount $account = null): ?array
    {
        $this->lastError = null;

        if ($account !== null) {
            $this->account = $account;
        }

        if ($this->activeAccount() === null || ! $this->activeAccount()->isConfigured()) {
            $this->lastError = 'Facebook Page chưa được cấu hình — cần Page ID và Page Access Token.';

            return null;
        }

        $pageId = $this->pageId();
        if ($pageId === null) {
            $this->lastError = 'Thiếu Facebook Page ID.';

            return null;
        }

        $response = $this->http()->get($this->baseUrl().'/'.$pageId, [
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
        ];

        $this->syncAccountProfile($profile);

        return $profile;
    }

    /**
     * @param  array{id: string, name?: string|null}  $profile
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

        $response = $this->http()->post($this->baseUrl().'/'.$pageId.'/photos', [
            'url' => $imageUrl,
            'caption' => $message,
        ]);

        if (! $response->successful()) {
            $this->lastError = $this->formatGraphError($response->json(), $response->status());
            Log::warning('FacebookGraphService photo failed', [
                'status' => $response->status(),
                'body' => $response->json(),
                'image_url' => $imageUrl,
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

        $response = $this->http()->post($this->baseUrl().'/'.$pageId.'/videos', [
            'file_url' => $videoUrl,
            'description' => $message,
        ]);

        if (! $response->successful()) {
            $this->lastError = $this->formatGraphError($response->json(), $response->status());
            Log::warning('FacebookGraphService video failed', [
                'status' => $response->status(),
                'body' => $response->json(),
                'video_url' => $videoUrl,
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

        $payload = ['message' => $message];
        if (filled($link)) {
            $payload['link'] = $link;
        }

        $response = $this->http()->post($this->baseUrl().'/'.$pageId.'/feed', $payload);

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

        $parts = ["HTTP {$status}: {$message}"];
        if ($code !== null) {
            $parts[] = "code={$code}";
        }
        if ($subcode !== null) {
            $parts[] = "subcode={$subcode}";
        }

        return implode(' ', $parts);
    }
}
