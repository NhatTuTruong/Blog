<?php

namespace App\Services;

use App\Models\FacebookAccount;
use App\Models\InstagramAccount;
use App\Models\PinterestAccount;
use App\Support\GeminiKeyScope;
use App\Support\IntegrationSettingsStore;
use App\Support\MailSettings;
use App\Support\PinterestUi;

class IntegrationSettingsPersistence
{
    private readonly IntegrationSettingsStore $store;

    public function __construct(private readonly int $userId)
    {
        $this->store = new IntegrationSettingsStore($this->userId);
    }

    public function loadFormData(): array
    {
        $store = $this->store;

        return array_merge([
            'gemini_api_key_auto_blog' => $this->maskedGeminiKey($store, GeminiKeyScope::AUTO_BLOG),
            'gemini_api_key_instagram' => $this->maskedGeminiKey($store, GeminiKeyScope::INSTAGRAM),
            'gemini_api_key_facebook' => $this->maskedGeminiKey($store, GeminiKeyScope::FACEBOOK),
            'gemini_model' => (string) $store->get('gemini_model', config('gemini.model', 'gemini-2.5-flash-lite')),
            'gemini_timeout' => max(60, (int) $store->get('gemini_timeout', config('gemini.timeout', 120))),
            'apify_api_token' => $store->getEncrypted('apify_api_token') ? '********' : '',
            'mail_host' => (string) $store->get('mail_host', env('MAIL_HOST', 'smtp.gmail.com')),
            'mail_port' => (int) $store->get('mail_port', env('MAIL_PORT', 587)),
            'mail_encryption' => (string) $store->get('mail_encryption', env('MAIL_ENCRYPTION', 'tls')),
            'mail_username' => (string) $store->get('mail_username', env('MAIL_USERNAME', '')),
            'mail_password' => $store->getEncrypted('mail_password') ? '********' : '',
            'mail_from_name' => (string) $store->get('mail_from_name', env('MAIL_FROM_NAME', config('app.name'))),
            'imap_host' => (string) $store->get('imap_host', env('IMAP_HOST', 'imap.gmail.com')),
            'imap_port' => (int) $store->get('imap_port', env('IMAP_PORT', 993)),
            'imap_encryption' => (string) $store->get('imap_encryption', env('IMAP_ENCRYPTION', 'ssl')),
            'imap_sync_limit' => (int) $store->get('imap_sync_limit', env('IMAP_SYNC_LIMIT', 50)),
            'imap_auto_sync_seconds' => (int) $store->get('imap_auto_sync_seconds', env('IMAP_AUTO_SYNC_SECONDS', 120)),
            'imap_ui_poll_seconds' => (int) $store->get('imap_ui_poll_seconds', env('IMAP_UI_POLL_SECONDS', 15)),
            'imap_notifications_poll_seconds' => (int) $store->get('imap_notifications_poll_seconds', env('IMAP_NOTIFICATIONS_POLL_SECONDS', 10)),
            'instagram_enabled' => (bool) $store->get('instagram_enabled', false),
            'instagram_accounts' => InstagramAccount::query()
                ->where('owner_user_id', $this->userId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (InstagramAccount $account): array => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'access_token' => filled($account->access_token) ? '********' : '',
                    'user_id' => $account->user_id,
                    'enabled' => $account->enabled,
                ])
                ->values()
                ->all(),
            'instagram_graph_version' => (string) $store->get('instagram_graph_version', 'v21.0'),
            'instagram_queue_interval_minutes' => (int) $store->get('instagram_queue_interval_minutes', 30),
            'instagram_auto_queue_enabled' => (bool) $store->get('instagram_auto_queue_enabled', false),
            'instagram_auto_queue_interval_minutes' => (int) $store->get('instagram_auto_queue_interval_minutes', 60),
            'instagram_public_base_url' => (string) $store->get('instagram_public_base_url', ''),
            'instagram_default_image_url' => (string) $store->get('instagram_default_image_url', ''),
            'facebook_enabled' => (bool) $store->get('facebook_enabled', false),
            'facebook_accounts' => FacebookAccount::query()
                ->where('owner_user_id', $this->userId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (FacebookAccount $account): array => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'page_id' => $account->page_id,
                    'access_token' => filled($account->access_token) ? '********' : '',
                    'enabled' => $account->enabled,
                ])
                ->values()
                ->all(),
            'facebook_graph_version' => (string) $store->get('facebook_graph_version', 'v21.0'),
            'facebook_queue_interval_minutes' => (int) $store->get('facebook_queue_interval_minutes', 30),
            'facebook_auto_queue_enabled' => (bool) $store->get('facebook_auto_queue_enabled', false),
            'facebook_auto_queue_interval_minutes' => (int) $store->get('facebook_auto_queue_interval_minutes', 60),
            'facebook_public_base_url' => (string) $store->get('facebook_public_base_url', ''),
        ], PinterestUi::enabled() ? $this->pinterestFormFields($store) : []);
    }

    /**
     * @return array<string, mixed>
     */
    protected function pinterestFormFields(IntegrationSettingsStore $store): array
    {
        return [
            'gemini_api_key_pinterest' => $this->maskedGeminiKey($store, GeminiKeyScope::PINTEREST),
            'pinterest_enabled' => (bool) $store->get('pinterest_enabled', false),
            'pinterest_accounts' => PinterestAccount::query()
                ->where('owner_user_id', $this->userId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (PinterestAccount $account): array => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'access_token' => filled($account->access_token) ? '********' : '',
                    'enabled' => $account->enabled,
                ])
                ->values()
                ->all(),
            'pinterest_queue_interval_minutes' => (int) $store->get('pinterest_queue_interval_minutes', 30),
            'pinterest_auto_queue_enabled' => (bool) $store->get('pinterest_auto_queue_enabled', false),
            'pinterest_auto_queue_interval_minutes' => (int) $store->get('pinterest_auto_queue_interval_minutes', 60),
            'pinterest_auto_queue_board_ids' => implode(', ', \App\Support\PinterestSettings::autoQueueBoardIds($this->userId)),
            'pinterest_public_base_url' => (string) $store->get('pinterest_public_base_url', ''),
            'pinterest_api_base_url' => (string) $store->get('pinterest_api_base_url', ''),
        ];
    }

    public function saveFormData(array $data): void
    {
        $store = $this->store;

        $this->saveGeminiApiKey(GeminiKeyScope::settingKey(GeminiKeyScope::AUTO_BLOG), $data);
        $this->saveGeminiApiKey(GeminiKeyScope::settingKey(GeminiKeyScope::INSTAGRAM), $data);
        $this->saveGeminiApiKey(GeminiKeyScope::settingKey(GeminiKeyScope::FACEBOOK), $data);
        if (PinterestUi::enabled()) {
            $this->saveGeminiApiKey(GeminiKeyScope::settingKey(GeminiKeyScope::PINTEREST), $data);
        }

        $store->set('gemini_model', trim((string) ($data['gemini_model'] ?? config('gemini.model', 'gemini-2.5-flash-lite'))));
        $store->set('gemini_timeout', max(60, min(600, (int) ($data['gemini_timeout'] ?? config('gemini.timeout', 120)))));
        $this->saveApifyApiToken($data);

        $store->set('mail_host', trim((string) ($data['mail_host'] ?? 'smtp.gmail.com')));
        $store->set('mail_port', max(1, min(65535, (int) ($data['mail_port'] ?? 587))));
        $store->set('mail_encryption', trim((string) ($data['mail_encryption'] ?? 'tls')));
        $store->set('mail_username', trim((string) ($data['mail_username'] ?? '')));
        $this->saveMailPassword($data);
        $store->set('mail_from_name', trim((string) ($data['mail_from_name'] ?? config('app.name'))));
        $store->set('imap_host', trim((string) ($data['imap_host'] ?? 'imap.gmail.com')));
        $store->set('imap_port', max(1, min(65535, (int) ($data['imap_port'] ?? 993))));
        $store->set('imap_encryption', trim((string) ($data['imap_encryption'] ?? 'ssl')));
        $store->set('imap_sync_limit', max(1, min(500, (int) ($data['imap_sync_limit'] ?? 50))));
        $store->set('imap_auto_sync_seconds', max(0, min(3600, (int) ($data['imap_auto_sync_seconds'] ?? 120))));
        $store->set('imap_ui_poll_seconds', max(0, min(300, (int) ($data['imap_ui_poll_seconds'] ?? 15))));
        $store->set('imap_notifications_poll_seconds', max(0, min(300, (int) ($data['imap_notifications_poll_seconds'] ?? 10))));

        $this->applyMailConfig();

        $store->set('instagram_enabled', (bool) ($data['instagram_enabled'] ?? false));
        $this->saveInstagramAccounts($data);
        $store->set('instagram_graph_version', trim((string) ($data['instagram_graph_version'] ?? 'v21.0')));
        $store->set('instagram_queue_interval_minutes', max(1, min(1440, (int) ($data['instagram_queue_interval_minutes'] ?? 30))));
        $store->set('instagram_auto_queue_enabled', (bool) ($data['instagram_auto_queue_enabled'] ?? false));
        $store->set('instagram_auto_queue_interval_minutes', max(1, min(1440, (int) ($data['instagram_auto_queue_interval_minutes'] ?? 60))));
        $store->set('instagram_public_base_url', trim((string) ($data['instagram_public_base_url'] ?? '')));
        $store->set('instagram_default_image_url', trim((string) ($data['instagram_default_image_url'] ?? '')));

        $store->set('facebook_enabled', (bool) ($data['facebook_enabled'] ?? false));
        $this->saveFacebookAccounts($data);
        $store->set('facebook_graph_version', trim((string) ($data['facebook_graph_version'] ?? 'v21.0')));
        $store->set('facebook_queue_interval_minutes', max(1, min(1440, (int) ($data['facebook_queue_interval_minutes'] ?? 30))));
        $store->set('facebook_auto_queue_enabled', (bool) ($data['facebook_auto_queue_enabled'] ?? false));
        $store->set('facebook_auto_queue_interval_minutes', max(1, min(1440, (int) ($data['facebook_auto_queue_interval_minutes'] ?? 60))));
        $store->set('facebook_public_base_url', trim((string) ($data['facebook_public_base_url'] ?? '')));

        if (PinterestUi::enabled()) {
            $store->set('pinterest_enabled', (bool) ($data['pinterest_enabled'] ?? false));
            $this->savePinterestAccounts($data);
            $store->set('pinterest_queue_interval_minutes', max(1, min(1440, (int) ($data['pinterest_queue_interval_minutes'] ?? 30))));
            $store->set('pinterest_auto_queue_enabled', (bool) ($data['pinterest_auto_queue_enabled'] ?? false));
            $store->set('pinterest_auto_queue_interval_minutes', max(1, min(1440, (int) ($data['pinterest_auto_queue_interval_minutes'] ?? 60))));
            $boardIds = collect(preg_split('/\s*,\s*/', trim((string) ($data['pinterest_auto_queue_board_ids'] ?? ''))) ?: [])
                ->map(fn (mixed $id): string => trim((string) $id))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $store->set('pinterest_auto_queue_board_ids', $boardIds);
            $store->set('pinterest_public_base_url', trim((string) ($data['pinterest_public_base_url'] ?? '')));
            $store->set('pinterest_api_base_url', trim((string) ($data['pinterest_api_base_url'] ?? '')));
        }
    }

    protected function maskedGeminiKey(IntegrationSettingsStore $store, string $scope): string
    {
        $field = GeminiKeyScope::settingKey($scope);
        if ($store->getEncrypted($field)) {
            return '********';
        }

        if ($scope === GeminiKeyScope::AUTO_BLOG && $store->getEncrypted('gemini_api_key')) {
            return '********';
        }

        return '';
    }

    protected function saveGeminiApiKey(string $field, array $data): void
    {
        $value = trim((string) ($data[$field] ?? ''));

        if ($value === '********') {
            return;
        }

        $this->store->setEncrypted($field, $value !== '' ? $value : null);
    }

    protected function saveApifyApiToken(array $data): void
    {
        $value = trim((string) ($data['apify_api_token'] ?? ''));

        if ($value === '********') {
            return;
        }

        $this->store->setEncrypted('apify_api_token', $value !== '' ? $value : null);
    }

    protected function saveMailPassword(array $data): void
    {
        $value = trim((string) ($data['mail_password'] ?? ''));

        if ($value === '********') {
            return;
        }

        $this->store->setEncrypted('mail_password', $value !== '' ? $value : null);
    }

    protected function saveInstagramAccounts(array $data): void
    {
        $rows = is_array($data['instagram_accounts'] ?? null) ? $data['instagram_accounts'] : [];
        $keptIds = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $tokenInput = trim((string) ($row['access_token'] ?? ''));
            $accountId = filled($row['id'] ?? null) ? (int) $row['id'] : null;

            /** @var InstagramAccount|null $account */
            $account = $accountId
                ? InstagramAccount::query()
                    ->where('owner_user_id', $this->userId)
                    ->find($accountId)
                : null;

            if ($account === null && ($tokenInput === '' || $tokenInput === '********')) {
                continue;
            }

            if ($account === null) {
                $account = new InstagramAccount;
                $account->owner_user_id = $this->userId;
            }

            $account->name = filled($row['name'] ?? null) ? trim((string) $row['name']) : null;
            $account->user_id = filled($row['user_id'] ?? null) ? trim((string) $row['user_id']) : null;
            $account->enabled = (bool) ($row['enabled'] ?? true);
            $account->sort_order = (int) $index;

            if ($tokenInput !== '' && $tokenInput !== '********') {
                $account->access_token = $tokenInput;
            } elseif (! $account->exists) {
                continue;
            }

            $account->save();
            $keptIds[] = $account->id;
        }

        $query = InstagramAccount::query()->where('owner_user_id', $this->userId);

        if ($keptIds !== []) {
            $query->whereNotIn('id', $keptIds)->delete();
        } else {
            $query->delete();
        }
    }

    protected function savePinterestAccounts(array $data): void
    {
        $rows = is_array($data['pinterest_accounts'] ?? null) ? $data['pinterest_accounts'] : [];
        $keptIds = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $tokenInput = trim((string) ($row['access_token'] ?? ''));
            $accountId = filled($row['id'] ?? null) ? (int) $row['id'] : null;

            /** @var PinterestAccount|null $account */
            $account = $accountId
                ? PinterestAccount::query()
                    ->where('owner_user_id', $this->userId)
                    ->find($accountId)
                : null;

            if ($account === null && ($tokenInput === '' || $tokenInput === '********')) {
                continue;
            }

            if ($account === null) {
                $account = new PinterestAccount;
                $account->owner_user_id = $this->userId;
            }

            $account->name = filled($row['name'] ?? null) ? trim((string) $row['name']) : null;
            $account->enabled = (bool) ($row['enabled'] ?? true);
            $account->sort_order = (int) $index;

            if ($tokenInput !== '' && $tokenInput !== '********') {
                $account->access_token = $tokenInput;
            } elseif (! $account->exists) {
                continue;
            }

            $account->save();
            $keptIds[] = $account->id;
        }

        $query = PinterestAccount::query()->where('owner_user_id', $this->userId);

        if ($keptIds !== []) {
            $query->whereNotIn('id', $keptIds)->delete();
        } else {
            $query->delete();
        }

        if (PinterestAccount::query()
            ->where('owner_user_id', $this->userId)
            ->where('enabled', true)
            ->get()
            ->contains(fn (PinterestAccount $account): bool => $account->isConfigured())) {
            $this->store->set('pinterest_enabled', true);
        }
    }

    protected function saveFacebookAccounts(array $data): void
    {
        $rows = is_array($data['facebook_accounts'] ?? null) ? $data['facebook_accounts'] : [];
        $keptIds = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $tokenInput = trim((string) ($row['access_token'] ?? ''));
            $accountId = filled($row['id'] ?? null) ? (int) $row['id'] : null;
            $pageId = filled($row['page_id'] ?? null) ? trim((string) $row['page_id']) : '';

            /** @var FacebookAccount|null $account */
            $account = $accountId
                ? FacebookAccount::query()
                    ->where('owner_user_id', $this->userId)
                    ->find($accountId)
                : null;

            if ($account === null && ($tokenInput === '' || $tokenInput === '********')) {
                continue;
            }

            if ($account === null) {
                $account = new FacebookAccount;
                $account->owner_user_id = $this->userId;
            }

            if ($pageId === '' && ! $account->exists) {
                continue;
            }

            $account->name = filled($row['name'] ?? null) ? trim((string) $row['name']) : null;
            $account->page_id = $pageId !== '' ? $pageId : $account->page_id;
            $account->enabled = (bool) ($row['enabled'] ?? true);
            $account->sort_order = (int) $index;

            if ($tokenInput !== '' && $tokenInput !== '********') {
                $account->access_token = $tokenInput;
            } elseif (! $account->exists) {
                continue;
            }

            $account->save();
            $keptIds[] = $account->id;
        }

        $query = FacebookAccount::query()->where('owner_user_id', $this->userId);

        if ($keptIds !== []) {
            $query->whereNotIn('id', $keptIds)->delete();
        } else {
            $query->delete();
        }
    }

    protected function applyMailConfig(): void
    {
        if (method_exists(MailSettings::class, 'applyForUser')) {
            MailSettings::applyForUser($this->userId);

            return;
        }

        $store = $this->store;
        $username = trim((string) $store->get('mail_username', env('MAIL_USERNAME', '')));

        if ($username !== '') {
            config([
                'mail.mailers.smtp.username' => $username,
                'mail.from.address' => $username,
                'imap.username' => $username,
            ]);
        }

        $password = $store->getEncrypted('mail_password');

        if (! is_string($password) || $password === '') {
            $password = (string) env('MAIL_PASSWORD', '');
        }

        if ($password !== '') {
            config([
                'mail.mailers.smtp.password' => $password,
                'imap.password' => $password,
            ]);
        }

        config([
            'mail.mailers.smtp.host' => trim((string) $store->get('mail_host', env('MAIL_HOST', 'smtp.gmail.com'))),
            'mail.mailers.smtp.port' => max(1, (int) $store->get('mail_port', env('MAIL_PORT', 587))),
            'mail.mailers.smtp.encryption' => trim((string) $store->get('mail_encryption', env('MAIL_ENCRYPTION', 'tls'))),
            'mail.from.name' => trim((string) $store->get('mail_from_name', env('MAIL_FROM_NAME', config('app.name')))),
            'imap.host' => trim((string) $store->get('imap_host', env('IMAP_HOST', 'imap.gmail.com'))),
            'imap.port' => max(1, (int) $store->get('imap_port', env('IMAP_PORT', 993))),
            'imap.encryption' => trim((string) $store->get('imap_encryption', env('IMAP_ENCRYPTION', 'ssl'))),
            'imap.sync_limit' => max(1, (int) $store->get('imap_sync_limit', env('IMAP_SYNC_LIMIT', 50))),
            'imap.auto_sync_seconds' => max(0, (int) $store->get('imap_auto_sync_seconds', env('IMAP_AUTO_SYNC_SECONDS', 120))),
            'imap.ui_poll_seconds' => max(0, (int) $store->get('imap_ui_poll_seconds', env('IMAP_UI_POLL_SECONDS', 15))),
            'imap.notifications_poll_seconds' => max(0, (int) $store->get('imap_notifications_poll_seconds', env('IMAP_NOTIFICATIONS_POLL_SECONDS', 10))),
        ]);
    }
}
