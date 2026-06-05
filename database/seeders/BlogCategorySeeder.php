<?php

namespace Database\Seeders;

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogCategorySeeder extends Seeder
{
    public function run(): void
    {
        $names = collect(config('default_categories.names', User::defaultCategoryNames()))
            ->merge(
                Blog::query()
                    ->whereNotNull('category')
                    ->where('category', '!=', '')
                    ->distinct()
                    ->pluck('category')
            )
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values();

        $sort = 0;
        foreach ($names as $name) {
            BlogCategory::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'sort_order' => $sort++,
                    'is_active' => true,
                ]
            );
        }

        Blog::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->each(function (Blog $blog) {
                $category = BlogCategory::query()->where('name', $blog->category)->first();
                if ($category) {
                    $blog->update(['blog_category_id' => $category->id]);
                }
            });
    }
}
