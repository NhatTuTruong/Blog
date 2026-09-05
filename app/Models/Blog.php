<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Support\BlogCategorySelection;
use App\Support\BlogContentSanitizer;
use App\Support\PublicStorage;
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
        'priority',
        'created_at',
    ];

    protected $casts = [
        'images' => 'array',
        'videos' => 'array',
        'is_published' => 'boolean',
        'views_count' => 'integer',
        'priority' => 'integer',
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

    public function blogCategories(): BelongsToMany
    {
        return $this->belongsToMany(BlogCategory::class, 'blog_blog_category');
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    public function syncBlogCategories(array $categoryIds): void
    {
        $categoryIds = collect($categoryIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $this->blogCategories()->sync($categoryIds);

        if ($categoryIds !== []) {
            $primaryId = $categoryIds[0];
            $labels = BlogCategorySelection::labelForIds($categoryIds);

            $this->forceFill([
                'blog_category_id' => $primaryId,
                'category' => $labels ?? $this->category,
            ])->saveQuietly();
        }
    }

    public function getCategoryLabelsAttribute(): string
    {
        $names = $this->category_names_list;

        return $names !== [] ? implode(', ', $names) : '';
    }

    /**
     * @return array<int, string>
     */
    public function getCategoryNamesListAttribute(): array
    {
        if ($this->relationLoaded('blogCategories') && $this->blogCategories->isNotEmpty()) {
            return $this->blogCategories->pluck('name')->values()->all();
        }

        $ids = $this->blogCategories()->pluck('blog_categories.id')->all();
        if ($ids !== []) {
            return BlogCategorySelection::namesForIds($ids);
        }

        $category = trim((string) ($this->category ?? $this->blogCategory?->name ?? ''));
        if ($category === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            trim(...),
            preg_split('/[,;|]+/', $category) ?: []
        )));
    }

    public function scopeInBlogCategory($query, int $categoryId)
    {
        return $query->where(function ($inner) use ($categoryId): void {
            $inner->where('blog_category_id', $categoryId)
                ->orWhereHas('blogCategories', fn ($relation) => $relation->where('blog_categories.id', $categoryId));
        });
    }

    public function scopeInBlogCategoryName($query, string $categoryName)
    {
        return $query->where(function ($inner) use ($categoryName): void {
            $inner->where('category', $categoryName)
                ->orWhere('category', 'like', $categoryName.',%')
                ->orWhere('category', 'like', '%, '.$categoryName)
                ->orWhere('category', 'like', '%, '.$categoryName.',%')
                ->orWhereHas('blogCategories', fn ($relation) => $relation->where('blog_categories.name', $categoryName));
        });
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Sắp xếp bài viết: priority giảm dần, sau đó lượt tương tác, rồi ngày tạo.
     * Áp dụng cho các block featured/hero/trending trên trang chủ.
     */
    public function scopeHomeOrder($query)
    {
        return $query
            ->orderByDesc('priority')
            ->orderByDesc('views_count')
            ->orderByDesc('created_at');
    }

    public function renderedContent(): string
    {
        return BlogContentSanitizer::sanitize((string) $this->content);
    }

    public function getExcerptAttribute(): string
    {
        return Str::limit(trim(strip_tags($this->renderedContent())), 160);
    }

    public function getReadingMinutesAttribute(): int
    {
        $words = str_word_count(strip_tags($this->renderedContent()));

        return max(1, (int) ceil($words / 220));
    }

    /** Bài viết có file ảnh đại diện hợp lệ trên disk. */
    public function hasStoredFeaturedImage(): bool
    {
        return filled($this->featured_image)
            && PublicStorage::exists($this->featured_image);
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
            return PublicStorage::url($this->featured_image);
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

            if ($category->image && PublicStorage::exists($category->image)) {
                return PublicStorage::url($category->image);
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

        if ($model && $model->image && PublicStorage::exists($model->image)) {
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
            if ($blog->blog_category_id && ! $blog->isDirty('category')) {
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
