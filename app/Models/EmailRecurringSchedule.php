<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailRecurringSchedule extends Model
{
    protected $fillable = [
        'email_template_id',
        'template_name',
        'recipients',
        'variable_values',
        'subject',
        'body',
        'extra_attachment_paths',
        'interval_hours',
        'next_send_at',
        'stopped_at',
        'last_sent_at',
        'send_count',
        'user_id',
    ];

    protected $casts = [
        'recipients' => 'array',
        'variable_values' => 'array',
        'extra_attachment_paths' => 'array',
        'interval_hours' => 'integer',
        'send_count' => 'integer',
        'next_send_at' => 'datetime',
        'stopped_at' => 'datetime',
        'last_sent_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sendLogs(): HasMany
    {
        return $this->hasMany(EmailSendLog::class);
    }

    public function isActive(): bool
    {
        return $this->stopped_at === null;
    }

    public function stop(): void
    {
        if ($this->isActive()) {
            $this->update(['stopped_at' => now()]);
        }
    }

    /**
     * @return array<int, string>
     */
    public function extraAttachmentStoragePaths(): array
    {
        return EmailSendLog::normalizeArray($this->extra_attachment_paths);
    }

    public function intervalLabel(): string
    {
        $hours = max(1, (int) $this->interval_hours);

        if ($hours % 24 === 0) {
            $days = (int) ($hours / 24);

            return $days === 1 ? '24 giờ' : "{$days} ngày";
        }

        return "{$hours} giờ";
    }

    public function statusLabel(): string
    {
        if (! $this->isActive()) {
            return 'Đã dừng gửi lại';
        }

        return 'Gửi lại mỗi '.$this->intervalLabel();
    }
}
