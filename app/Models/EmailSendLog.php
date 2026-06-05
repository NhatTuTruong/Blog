<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailSendLog extends Model
{
    protected $fillable = [
        'email_template_id',
        'template_name',
        'recipients',
        'variable_values',
        'subject',
        'body',
        'attachments',
        'sent_count',
        'failed_count',
        'errors',
        'user_id',
    ];

    protected $casts = [
        'recipients' => 'array',
        'variable_values' => 'array',
        'attachments' => 'array',
        'errors' => 'array',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function normalizeArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(
                $value,
                fn (mixed $item): bool => $item !== null && $item !== '',
            ));
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return array_values(array_filter(
                    $decoded,
                    fn (mixed $item): bool => $item !== null && $item !== '',
                ));
            }

            return [$value];
        }

        return [];
    }

    public static function formatList(mixed $value, string $empty = '—'): string
    {
        $items = static::normalizeArray($value);

        return $items !== [] ? implode(', ', $items) : $empty;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function attachmentDisplayItems(): array
    {
        return collect(static::normalizeArray($this->attachments))
            ->map(function (mixed $name): array {
                $filename = is_string($name) ? $name : (string) $name;

                return [
                    'name' => $filename,
                    'size_label' => null,
                    'content_type' => null,
                    'preview_url' => null,
                    'download_url' => null,
                    'can_preview' => false,
                    'meta_note' => 'Đã gửi kèm — tải lại file khi gửi lại',
                ];
            })
            ->values()
            ->all();
    }

    public function recipientsForResend(): string
    {
        return implode("\n", static::normalizeArray($this->recipients));
    }

    public function isManualSend(): bool
    {
        return $this->email_template_id === null;
    }
}
