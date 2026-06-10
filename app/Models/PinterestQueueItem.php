<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PinterestQueueItem extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'batch_id',
        'user_id',
        'pinterest_account_id',
        'board_id',
        'board_name',
        'sort_order',
        'brand_domain',
        'content_idea',
        'aff_link',
        'coupon_codes',
        'image_path',
        'video_path',
        'caption',
        'used_default_caption',
        'pinterest_pin_id',
        'status',
        'scheduled_at',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'coupon_codes' => 'array',
        'scheduled_at' => 'datetime',
        'processed_at' => 'datetime',
        'sort_order' => 'integer',
        'used_default_caption' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pinterestAccount(): BelongsTo
    {
        return $this->belongsTo(PinterestAccount::class);
    }

    public function usesDefaultCaptionInQueue(): bool
    {
        return (bool) $this->used_default_caption
            && in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
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
