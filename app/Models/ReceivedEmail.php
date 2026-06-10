<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ReceivedEmail extends Model
{
    protected $fillable = [
        'user_id',
        'imap_uid',
        'folder',
        'message_id',
        'from_email',
        'from_name',
        'to',
        'subject',
        'body_html',
        'body_text',
        'received_at',
        'is_seen',
        'attachments_count',
        'attachments',
    ];

    protected $casts = [
        'imap_uid' => 'integer',
        'to' => 'array',
        'attachments' => 'array',
        'received_at' => 'datetime',
        'is_seen' => 'boolean',
        'attachments_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (ReceivedEmail $email): void {
            $email->deleteStoredAttachmentFiles();
        });
    }

    public function fromDisplay(): string
    {
        if ($this->from_name) {
            return $this->from_name.' <'.$this->from_email.'>';
        }

        return $this->from_email;
    }

    public function recipientsDisplay(): string
    {
        $recipients = self::normalizeArray($this->to);

        if ($recipients !== []) {
            return implode(', ', $recipients);
        }

        $mailbox = \App\Support\MailSettings::fromAddress();

        return filled($mailbox) ? $mailbox : '—';
    }

    public function displayBodyHtml(): string
    {
        if (filled($this->body_html)) {
            return self::prepareHtmlForPreview($this->body_html);
        }

        if (filled($this->body_text)) {
            return '<div class="received-email-plain">'.nl2br(e($this->body_text), false).'</div>';
        }

        return '<p class="received-email-empty">(Không có nội dung)</p>';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function attachmentItems(): array
    {
        return collect(self::normalizeAttachments($this->attachments))
            ->map(function (array $attachment): array {
                $id = (string) ($attachment['id'] ?? '');
                $name = (string) ($attachment['name'] ?? basename((string) ($attachment['path'] ?? 'file')));
                $contentType = (string) ($attachment['content_type'] ?? '');
                $size = (int) ($attachment['size'] ?? 0);
                $canPreview = self::canPreviewAttachment($contentType, $name);

                return [
                    'id' => $id,
                    'name' => $name,
                    'path' => (string) ($attachment['path'] ?? ''),
                    'size' => $size,
                    'size_label' => self::formatFileSize($size),
                    'content_type' => $contentType,
                    'can_preview' => $canPreview,
                    'preview_url' => $canPreview ? route('admin.received-emails.attachments.show', [
                        'receivedEmail' => $this->getKey(),
                        'attachment' => $id,
                    ]) : null,
                    'download_url' => route('admin.received-emails.attachments.download', [
                        'receivedEmail' => $this->getKey(),
                        'attachment' => $id,
                    ]),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAttachment(string $attachmentId): ?array
    {
        foreach (self::normalizeAttachments($this->attachments) as $attachment) {
            if ((string) ($attachment['id'] ?? '') === $attachmentId) {
                return $attachment;
            }
        }

        return null;
    }

    public function hasStoredAttachments(): bool
    {
        foreach (self::normalizeAttachments($this->attachments) as $attachment) {
            $path = (string) ($attachment['path'] ?? '');

            if ($path !== '' && Storage::disk('local')->exists($path)) {
                return true;
            }
        }

        return false;
    }

    public function deleteStoredAttachmentFiles(): void
    {
        $directory = 'received-email-attachments/'.$this->getKey();

        if (Storage::disk('local')->exists($directory)) {
            Storage::disk('local')->deleteDirectory($directory);
        }
    }

    public static function canPreviewAttachment(string $contentType, string $filename): bool
    {
        if (str_starts_with($contentType, 'image/')) {
            return true;
        }

        if ($contentType === 'application/pdf') {
            return true;
        }

        if (str_starts_with($contentType, 'text/')) {
            return true;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'txt', 'csv'], true);
    }

    public static function formatFileSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), $power > 0 ? 1 : 0).' '.$units[$power];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeAttachments(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values()
            ->all();
    }

    public static function prepareHtmlForPreview(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $matches)) {
            $html = $matches[1];
        }

        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $html = preg_replace('/<link\b[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<meta\b[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html) ?? $html;

        return trim($html);
    }

    public static function normalizeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
