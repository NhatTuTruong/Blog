<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'image',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function blogs(): HasMany
    {
        return $this->hasMany(Blog::class);
    }

    public function assignedBlogs(): BelongsToMany
    {
        return $this->belongsToMany(Blog::class, 'blog_blog_category');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getImageUrlAttribute(): string
    {
        return Blog::categoryImageUrl($this);
    }

    public static function optionsForSelect(): array
    {
        return static::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function booted(): void
    {
        static::creating(function (BlogCategory $category) {
            if (empty($category->slug) && filled($category->name)) {
                $category->slug = static::uniqueSlug(Str::slug($category->name));
            }
        });

        static::updating(function (BlogCategory $category) {
            if ($category->isDirty('name') && ! $category->isDirty('slug')) {
                $category->slug = static::uniqueSlug(Str::slug($category->name), $category->id);
            }
        });

        static::saved(function (BlogCategory $category) {
            if ($category->wasChanged('name')) {
                Blog::query()
                    ->where('blog_category_id', $category->id)
                    ->update(['category' => $category->name]);
            }
        });

        static::deleting(function (BlogCategory $category) {
            Blog::query()
                ->where('blog_category_id', $category->id)
                ->update(['blog_category_id' => null]);
        });
    }

    protected static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base ?: 'category';
        $n = 0;

        while (static::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $n++;
            $slug = $base.'-'.$n;
        }

        return $slug;
    }
}
