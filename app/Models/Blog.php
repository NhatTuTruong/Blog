<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Blog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'blog_category_id',
        'title',
        'category',
        'slug',
        'content',
        'featured_image',
        'images',
        'videos',
        'is_published',
        'views_count',
        'created_at',
    ];

    protected $casts = [
        'images' => 'array',
        'videos' => 'array',
        'is_published' => 'boolean',
        'views_count' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function blogCategory(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function getExcerptAttribute(): string
    {
        return Str::limit(trim(strip_tags((string) $this->content)), 160);
    }

    public function getReadingMinutesAttribute(): int
    {
        $words = str_word_count(strip_tags((string) $this->content));

        return max(1, (int) ceil($words / 220));
    }

    /** Bài viết có file ảnh đại diện hợp lệ trên disk. */
    public function hasStoredFeaturedImage(): bool
    {
        return filled($this->featured_image)
            && Storage::disk('public')->exists($this->featured_image);
    }

    /** Danh mục gắn với bài (theo blog_category_id hoặc tên category). */
    public function categoryForImage(): ?BlogCategory
    {
        if ($this->blog_category_id) {
            if ($this->relationLoaded('blogCategory')) {
                return $this->blogCategory;
            }

            return BlogCategory::query()->find($this->blog_category_id);
        }

        return static::resolveBlogCategory($this->category);
    }

    /** URL ảnh hiển thị: ảnh bài viết → public/categories/{slug} → ảnh upload danh mục → default */
    public function getFeaturedImageUrlAttribute(): string
    {
        if ($this->hasStoredFeaturedImage()) {
            return Storage::disk('public')->url($this->featured_image);
        }

        return static::categoryImageUrl($this->categoryForImage(), $this->category);
    }

    /**
     * Thư mục ảnh danh mục tĩnh (ưu tiên public/categories).
     *
     * @return array<int, string>
     */
    public static function publicCategoryImageDirs(): array
    {
        return ['categories', 'images/categories'];
    }

    /**
     * Ảnh danh mục trong public/categories/{slug}.jpg (hoặc images/categories).
     */
    public static function publicCategoryImageUrl(?string $slug = null, ?string $categoryName = null): ?string
    {
        $slug = filled($slug) ? $slug : (filled($categoryName) ? Str::slug($categoryName) : null);

        if (! filled($slug)) {
            return null;
        }

        foreach (static::publicCategoryImageDirs() as $dir) {
            foreach (['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'] as $ext) {
                $relative = "{$dir}/{$slug}.{$ext}";

                if (file_exists(public_path($relative))) {
                    return asset($relative);
                }
            }
        }

        return null;
    }

    /** URL ảnh danh mục: public/categories → upload admin → default.jpg */
    public static function categoryImageUrl(?BlogCategory $category = null, ?string $categoryName = null): string
    {
        if ($category) {
            if ($url = static::publicCategoryImageUrl($category->slug, $category->name)) {
                return $url;
            }

            if ($category->image && Storage::disk('public')->exists($category->image)) {
                return Storage::disk('public')->url($category->image);
            }
        }

        if ($url = static::publicCategoryImageUrl(categoryName: $categoryName ?? $category?->name)) {
            return $url;
        }

        return static::defaultImageUrl();
    }

    /** Ảnh mặc định khi bài viết / danh mục không có ảnh riêng. */
    public static function defaultImageUrl(): string
    {
        foreach ( [
            'images/default.jpg',
            'categories/default.jpg',
            'images/categories/default.jpg',
        ] as $path) {
            if (file_exists(public_path($path))) {
                return asset($path);
            }
        }

        return asset('images/default.jpg');
    }

    public static function categoryHasCustomImage(?string $category): bool
    {
        $model = static::resolveBlogCategory($category);

        if ($model && static::publicCategoryImageUrl($model->slug, $model->name)) {
            return true;
        }

        if ($model && $model->image && Storage::disk('public')->exists($model->image)) {
            return true;
        }

        return static::publicCategoryImageUrl(categoryName: $category) !== null;
    }

    public static function categoryIconUrl(?string $category): string
    {
        return static::categoryImageUrl(static::resolveBlogCategory($category), $category);
    }

    public static function resolveBlogCategory(?string $category): ?BlogCategory
    {
        if (! filled($category)) {
            return null;
        }

        return BlogCategory::query()
            ->where(function ($query) use ($category) {
                $query->where('name', $category)
                    ->orWhere('slug', Str::slug($category));
            })
            ->first();
    }

    /** Màu accent ổn định theo tên danh mục (cho card không có ảnh). */
    public static function categoryColor(string $category): string
    {
        $palette = ['#059669', '#0d9488', '#2563eb', '#7c3aed', '#db2777', '#ea580c', '#ca8a04', '#0891b2'];
        $index = abs(crc32($category)) % count($palette);

        return $palette[$index];
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function (Blog $blog) {
            if ($blog->blog_category_id) {
                $cat = BlogCategory::query()->find($blog->blog_category_id);
                if ($cat) {
                    $blog->category = $cat->name;
                }
            }
        });

        static::creating(function (Blog $blog) {
            if (empty($blog->slug)) {
                $baseSlug = Str::slug($blog->title);
                $slug = $baseSlug;
                $n = 0;
                while (static::where('slug', $slug)->exists()) {
                    $n++;
                    $slug = $baseSlug . '-' . $n;
                }
                $blog->slug = $slug;
            }
            if (empty($blog->user_id) && auth()->check()) {
                $blog->user_id = auth()->id();
            }
        });

        static::updating(function (Blog $blog) {
            if ($blog->isDirty('title') && ! $blog->isDirty('slug')) {
                $baseSlug = Str::slug($blog->title);
                $slug = $baseSlug;
                $n = 0;
                while (static::where('slug', $slug)->where('id', '!=', $blog->id)->exists()) {
                    $n++;
                    $slug = $baseSlug . '-' . $n;
                }
                $blog->slug = $slug;
            }
        });
    }
}
