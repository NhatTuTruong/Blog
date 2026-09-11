<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\BlogCategory;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('categories.index', [
            'categories' => $this->buildCategoryCards(),
        ]);
    }

    /**
     * @return Collection<int, array{id: int, name: string, slug: string, count: int, url: string, image: string}>
     */
    protected function buildCategoryCards(): Collection
    {
        $postCounts = BlogCategory::query()
            ->active()
            ->withCount([
                'assignedBlogs as posts_count' => fn ($query) => $query->published(),
            ])
            ->pluck('posts_count', 'id');

        $legacyCounts = Blog::query()
            ->published()
            ->whereNotNull('blog_category_id')
            ->whereDoesntHave('blogCategories')
            ->selectRaw('blog_category_id, COUNT(*) as posts_count')
            ->groupBy('blog_category_id')
            ->pluck('posts_count', 'blog_category_id');

        foreach ($legacyCounts as $categoryId => $count) {
            $postCounts[$categoryId] = (int) ($postCounts[$categoryId] ?? 0) + (int) $count;
        }

        return BlogCategory::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (BlogCategory $cat) use ($postCounts) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'count' => (int) ($postCounts[$cat->id] ?? 0),
                    'url' => route('home', ['cat' => $cat->name]),
                    'image' => $cat->uploadedOrDefaultImageUrl(),
                ];
            });
    }
}
