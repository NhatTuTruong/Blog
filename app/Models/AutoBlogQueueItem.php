<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoBlogQueueItem extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'batch_id',
        'user_id',
        'sort_order',
        'brand_domain',
        'blog_category_id',
        'blog_category_ids',
        'category_name',
        'content_idea',
        'aff_link',
        'coupon_codes',
        'image_path',
        'status',
        'scheduled_at',
        'processed_at',
        'blog_id',
        'error_message',
    ];

    protected $casts = [
        'coupon_codes' => 'array',
        'blog_category_ids' => 'array',
        'scheduled_at' => 'datetime',
        'processed_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function blogCategory(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class);
    }

    public function blog(): BelongsTo
    {
        return $this->belongsTo(Blog::class);
    }

    /**
     * @return array<int, int>
     */
    public function resolvedCategoryIds(): array
    {
        $ids = collect(is_array($this->blog_category_ids) ? $this->blog_category_ids : [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids !== []) {
            return $ids;
        }

        return filled($this->blog_category_id) ? [(int) $this->blog_category_id] : [];
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Chờ đăng',
            self::STATUS_PROCESSING => 'Đang tạo',
            self::STATUS_COMPLETED => 'Hoàn thành',
            self::STATUS_FAILED => 'Thất bại',
            default => $this->status,
        };
    }
}
