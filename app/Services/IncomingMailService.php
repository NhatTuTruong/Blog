<?php

namespace App\Services;

use App\Filament\Admin\Resources\ReceivedEmailResource;
use App\Models\ReceivedEmail;
use App\Models\User;
use App\Notifications\SyncFilamentDatabaseNotification;
use App\Support\MailSettings;
use Carbon\Carbon;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

class IncomingMailService
{
    public ?string $lastError = null;

    public function isConfigured(): bool
    {
        return MailSettings::isConfigured();
    }

    public function imapExtensionAvailable(): bool
    {
        return function_exists('imap_open');
    }

    /**
     * @return array{new: int, updated: int, errors: array<int, string>, mode: string}
     */
    public function sync(?int $limit = null): array
    {
        $this->lastError = null;

        if (! $this->imapExtensionAvailable()) {
            $this->lastError = 'PHP chưa bật extension imap. Bật extension=imap trong php.ini rồi khởi động lại server.';

            return $this->emptyResult($this->lastError);
        }

        if (! $this->isConfigured()) {
            $this->lastError = 'Chưa cấu hình IMAP (IMAP_USERNAME / IMAP_PASSWORD hoặc MAIL_USERNAME / MAIL_PASSWORD trong .env).';

            return $this->emptyResult($this->lastError);
        }

        $limit = ($limit !== null && $limit > 0)
            ? $limit
            : (int) config('imap.sync_limit', 50);

        $folderName = (string) config('imap.folder', 'INBOX');
        $incremental = (bool) config('imap.incremental_sync', true);
        $lastUid = $incremental
            ? (int) ReceivedEmail::query()->where('folder', $folderName)->max('imap_uid')
            : 0;

        $new = 0;
        $updated = 0;
        $errors = [];
        $mode = 'initial';

        try {
            $client = $this->client();
            $client->connect();

            $folder = $client->getFolder($folderName);

            if ($lastUid > 0) {
                $mode = 'incremental';
                $messages = $this->fetchMessagesAfterUid($folder, $lastUid, $limit);
            } else {
                $messages = $folder->messages()
                    ->all()
                    ->setFetchOrderDesc()
                    ->limit($limit, 1)
                    ->get();
            }

            /** @var Message $message */
            foreach ($messages as $message) {
                try {
                    $record = $this->storeMessage($message, $folderName);

                    if ($record->wasRecentlyCreated) {
                        $new++;
                    } else {
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = $e->getMessage();
                    Log::warning('IncomingMailService store message failed', [
                        'uid' => $message->getUid(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $client->disconnect();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            $errors[] = $this->lastError;
            Log::warning('IncomingMailService sync failed', ['error' => $e->getMessage()]);
        }

        if ($new > 0) {
            $latest = ReceivedEmail::query()->orderByDesc('id')->first();

            try {
                $this->notifyAdminsOfNewEmails($new, $latest);
            } catch (\Throwable $e) {
                Log::warning('IncomingMailService notify admins failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'new' => $new,
            'updated' => $updated,
            'errors' => $errors,
            'mode' => $mode,
            // Tương thích code cũ dùng key "synced"
            'synced' => $new + $updated,
        ];
    }

    protected function notifyAdminsOfNewEmails(int $count, ?ReceivedEmail $latest): void
    {
        $admins = User::query()->where('is_admin', true)->get();

        if ($admins->isEmpty()) {
            return;
        }

        $title = $count === 1 ? 'Email mới' : "{$count} email mới";
        $body = $this->buildNewEmailNotificationBody($count, $latest);

        $actions = $latest && $count === 1
            ? [
                NotificationAction::make('view_email')
                    ->label('Xem email')
                    ->url($this->receivedEmailUrl('view', ['record' => $latest]))
                    ->markAsRead(),
                NotificationAction::make('view_inbox')
                    ->label('Danh sách')
                    ->url($this->receivedEmailUrl('index'))
                    ->markAsRead(),
            ]
            : [
                NotificationAction::make('view_inbox')
                    ->label('Xem Nhận mail')
                    ->url($this->receivedEmailUrl('index'))
                    ->markAsRead(),
            ];

        $data = Notification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-inbox')
            ->success()
            ->actions($actions)
            ->getDatabaseMessage();

        foreach ($admins as $admin) {
            $admin->notify(new SyncFilamentDatabaseNotification($data));
        }
    }

    protected function receivedEmailUrl(string $name, array $parameters = []): string
    {
        return ReceivedEmailResource::getUrl($name, $parameters, panel: 'admin');
    }

    protected function buildNewEmailNotificationBody(int $count, ?ReceivedEmail $latest): string
    {
        if ($count === 1 && $latest) {
            $from = $latest->fromDisplay();
            $subject = $latest->subject ?: '(Không có tiêu đề)';

            return "Từ: {$from}\nTiêu đề: {$subject}";
        }

        if ($latest) {
            return "Email mới nhất: {$latest->fromDisplay()} — ".($latest->subject ?: '(Không có tiêu đề)');
        }

        return 'Có email mới trong hộp thư.';
    }

    /**
     * Chỉ lấy email có UID lớn hơn UID cao nhất đã lưu (email mới trên server).
     */
    /**
     * @return array<int, Message>
     */
    protected function fetchMessagesAfterUid(mixed $folder, int $lastUid, int $limit): array
    {
        $collection = $folder->messages()->getByUidGreater($lastUid);

        return collect($collection->all())
            ->sortByDesc(fn (Message $message): int => (int) $message->getUid())
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array{new: int, updated: int, errors: array<int, string>, mode: string, synced: int}
     */
    protected function emptyResult(?string $error = null): array
    {
        return [
            'new' => 0,
            'updated' => 0,
            'errors' => $error ? [$error] : [],
            'mode' => 'none',
            'synced' => 0,
        ];
    }

    protected function storeMessage(Message $message, string $folderName): ReceivedEmail
    {
        $from = $message->getFrom()->first();
        $fromEmail = $from?->mail ?? 'unknown@local';
        $fromName = $from?->personal ?? null;

        $to = [];
        foreach ($message->getTo() as $address) {
            $to[] = $address->mail ?? (string) $address;
        }

        $date = $message->getDate();
        $receivedAt = $date instanceof Carbon
            ? $date
            : ($date ? Carbon::parse($date) : now());

        $htmlBody = $message->getHTMLBody();
        $textBody = $message->getTextBody();

        if ($htmlBody === false || $htmlBody === null) {
            $htmlBody = null;
        }

        if ($textBody === false || $textBody === null) {
            $textBody = null;
        }

        $record = ReceivedEmail::query()->updateOrCreate(
            [
                'folder' => $folderName,
                'imap_uid' => (int) $message->getUid(),
            ],
            [
                'message_id' => $message->getMessageId() ?: null,
                'from_email' => strtolower((string) $fromEmail),
                'from_name' => $fromName ? (string) $fromName : null,
                'to' => $to !== [] ? $to : null,
                'subject' => $message->getSubject() ?: '(Không có tiêu đề)',
                'body_html' => is_string($htmlBody) ? $htmlBody : null,
                'body_text' => is_string($textBody) ? $textBody : null,
                'received_at' => $receivedAt,
                'is_seen' => $message->hasFlag('seen'),
                'attachments_count' => $message->getAttachments()->count(),
            ],
        );

        $attachments = $this->syncMessageAttachments($message, $record);

        $record->forceFill([
            'attachments' => $attachments !== [] ? $attachments : null,
            'attachments_count' => count($attachments),
        ])->save();

        return $record;
    }

    public function fetchAttachmentsForRecord(ReceivedEmail $record): bool
    {
        $this->lastError = null;

        if (! $this->imapExtensionAvailable() || ! $this->isConfigured()) {
            $this->lastError = 'Chưa cấu hình IMAP hoặc thiếu extension imap.';

            return false;
        }

        try {
            $client = $this->client();
            $client->connect();

            $folder = $client->getFolder($record->folder);
            $message = $folder->messages()->getMessageByUid((int) $record->imap_uid);
            $attachments = $this->syncMessageAttachments($message, $record, force: true);

            $record->forceFill([
                'attachments' => $attachments !== [] ? $attachments : null,
                'attachments_count' => count($attachments),
            ])->save();

            $client->disconnect();

            return $attachments !== [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::warning('IncomingMailService fetch attachments failed', [
                'received_email_id' => $record->getKey(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function syncMessageAttachments(Message $message, ReceivedEmail $record, bool $force = false): array
    {
        $collection = $message->getAttachments();

        if ($collection->count() === 0) {
            if ($force || ! $record->hasStoredAttachments()) {
                $record->deleteStoredAttachmentFiles();
            }

            return [];
        }

        $existing = ReceivedEmail::normalizeAttachments($record->attachments);

        if (! $force && $existing !== [] && $this->allAttachmentFilesExist($existing)) {
            return $existing;
        }

        $record->deleteStoredAttachmentFiles();

        $directory = 'received-email-attachments/'.$record->getKey();
        Storage::disk('local')->makeDirectory($directory);

        $stored = [];
        $usedNames = [];

        /** @var Attachment $attachment */
        foreach ($collection as $index => $attachment) {
            $displayName = $this->attachmentDisplayName($attachment, $index);
            $attachmentId = (string) ($attachment->getHash() ?: ('att-'.$index));
            $storageName = $this->uniqueAttachmentFilename($displayName, $attachmentId, $usedNames);
            $relativePath = $directory.'/'.$storageName;

            Storage::disk('local')->put($relativePath, $attachment->getContent());

            $stored[] = [
                'id' => $attachmentId,
                'name' => $displayName,
                'path' => $relativePath,
                'size' => Storage::disk('local')->size($relativePath),
                'content_type' => $attachment->getContentType() ?: $attachment->getMimeType(),
            ];
        }

        return $stored;
    }

    protected function attachmentDisplayName(Attachment $attachment, int $index): string
    {
        $name = trim((string) ($attachment->getName() ?: $attachment->getFilename() ?: ''));

        if ($name === '') {
            $name = 'attachment-'.($index + 1);
        }

        return $name;
    }

    /**
     * @param  array<int, string>  $usedNames
     */
    protected function uniqueAttachmentFilename(string $displayName, string $attachmentId, array &$usedNames): string
    {
        $extension = pathinfo($displayName, PATHINFO_EXTENSION);
        $basename = pathinfo($displayName, PATHINFO_FILENAME);
        $basename = trim($basename) !== '' ? $basename : 'attachment';
        $safeBase = Str::slug($basename);

        if ($safeBase === '') {
            $safeBase = 'attachment';
        }

        $suffix = substr(preg_replace('/[^a-z0-9]/i', '', $attachmentId) ?: 'file', 0, 8);
        $filename = $extension !== ''
            ? $safeBase.'-'.$suffix.'.'.strtolower($extension)
            : $safeBase.'-'.$suffix;

        $candidate = $filename;
        $counter = 1;

        while (in_array(strtolower($candidate), $usedNames, true)) {
            $candidate = $extension !== ''
                ? $safeBase.'-'.$suffix.'-'.$counter.'.'.strtolower($extension)
                : $safeBase.'-'.$suffix.'-'.$counter;
            $counter++;
        }

        $usedNames[] = strtolower($candidate);

        return $candidate;
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     */
    protected function allAttachmentFilesExist(array $attachments): bool
    {
        if ($attachments === []) {
            return false;
        }

        foreach ($attachments as $attachment) {
            $path = (string) ($attachment['path'] ?? '');

            if ($path === '' || ! Storage::disk('local')->exists($path)) {
                return false;
            }
        }

        return true;
    }

    protected function client(): Client
    {
        $cm = new ClientManager([
            'default' => 'default',
            'accounts' => [
                'default' => [
                    'host' => config('imap.host'),
                    'port' => config('imap.port'),
                    'encryption' => config('imap.encryption'),
                    'validate_cert' => (bool) config('imap.validate_cert', true),
                    'username' => config('imap.username'),
                    'password' => config('imap.password'),
                    'protocol' => 'imap',
                ],
            ],
        ]);

        return $cm->account('default');
    }
}
