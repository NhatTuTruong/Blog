<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BlogDeal extends Model
{
    protected $fillable = [
        'title',
        'description',
        'coupon_code',
        'shop_url',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function blogs(): BelongsToMany
    {
        return $this->belongsToMany(Blog::class, 'blog_blog_deal')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('blog_blog_deal.sort_order')
            ->orderBy('blogs.id');
    }

    /**
     * Trang /deals: thứ tự cao hơn trước; bằng nhau thì mới tạo trước.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrderedForListing(Builder $query): Builder
    {
        return $query
            ->orderByDesc('sort_order')
            ->orderByDesc('created_at');
    }

    public function isCouponDeal(): bool
    {
        return filled(trim((string) $this->coupon_code));
    }

    public function typeLabel(): string
    {
        return $this->isCouponDeal() ? 'Coupon Codes' : 'Discount Deals';
    }

    public function primaryPublishedBlog(): ?Blog
    {
        if ($this->relationLoaded('blogs')) {
            return $this->blogs->first(fn (Blog $blog): bool => $blog->is_published)
                ?? $this->blogs->first();
        }

        return $this->blogs()
            ->published()
            ->orderBy('blog_blog_deal.sort_order')
            ->orderBy('blogs.id')
            ->first()
            ?? $this->blogs()
                ->orderBy('blog_blog_deal.sort_order')
                ->orderBy('blogs.id')
                ->first();
    }

    public function brandImageUrl(): ?string
    {
        return $this->primaryPublishedBlog()?->featured_image_url;
    }

    public function brandInitial(): string
    {
        $title = trim($this->title);

        return $title !== '' ? mb_strtoupper(mb_substr($title, 0, 1)) : 'D';
    }

    public function postUrl(): ?string
    {
        $blog = $this->primaryPublishedBlog();

        if (! $blog?->slug) {
            return null;
        }

        return $blog->publicUrl();
    }
}
