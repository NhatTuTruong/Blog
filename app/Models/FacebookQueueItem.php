<?php

namespace App\Models;

use App\Support\SocialMediaQueueSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookQueueItem extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'batch_id',
        'queue_source',
        'user_id',
        'facebook_account_id',
        'sort_order',
        'brand_domain',
        'content_idea',
        'aff_link',
        'coupon_codes',
        'image_path',
        'video_path',
        'media_type',
        'caption',
        'used_default_caption',
        'facebook_post_id',
        'status',
        'scheduled_at',
        'processed_at',
        'processing_started_at',
        'error_message',
    ];

    protected $casts = [
        'coupon_codes' => 'array',
        'scheduled_at' => 'datetime',
        'processed_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'sort_order' => 'integer',
        'used_default_caption' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function facebookAccount(): BelongsTo
    {
        return $this->belongsTo(FacebookAccount::class);
    }

    public function usesDefaultCaptionInQueue(): bool
    {
        return (bool) $this->used_default_caption
            && in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    public function queueSourceLabel(): string
    {
        return SocialMediaQueueSource::label($this->queue_source);
    }

    public function isAutoQueue(): bool
    {
        return $this->queue_source === SocialMediaQueueSource::AUTO;
    }

    public function statusLabel(): string
    {
        if ($this->usesDefaultCaptionInQueue()) {
            return 'Đang sử dụng nội dung mặc định';
        }

        return match ($this->status) {
            self::STATUS_PENDING => 'Chờ đăng',
            self::STATUS_PROCESSING => 'Đang đăng',
            self::STATUS_COMPLETED => 'Hoàn thành',
            self::STATUS_FAILED => 'Thất bại',
            default => $this->status,
        };
    }
}
