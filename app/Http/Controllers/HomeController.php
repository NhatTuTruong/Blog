<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $searchQuery = $request->string('q')->toString();
        $selectedCategory = $request->string('cat')->toString();
        $isFiltered = $searchQuery !== '' || $selectedCategory !== '';

        $baseQuery = Blog::query()->published()->with('blogCategory');

        $applyFilters = function ($query) use ($searchQuery, $selectedCategory) {
            if ($searchQuery !== '') {
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('title', 'like', "%{$searchQuery}%")
                        ->orWhere('content', 'like', "%{$searchQuery}%");
                });
            }
            if ($selectedCategory !== '') {
                $query->where('category', $selectedCategory);
            }
        };

        $stats = [
            'posts' => (clone $baseQuery)->count(),
            'categories' => BlogCategory::query()->active()->count(),
        ];

        $featuredCategories = $this->buildFeaturedCategories();

        if ($isFiltered) {
            $filteredPosts = (clone $baseQuery)->tap($applyFilters)
                ->orderByDesc('created_at')
                ->limit(24)
                ->get();

            return view('home', [
                'isFiltered' => true,
                'searchQuery' => $searchQuery,
                'selectedCategory' => $selectedCategory,
                'filteredPosts' => $filteredPosts,
                'featuredCategories' => $featuredCategories,
                'stats' => $stats,
                'featuredPost' => null,
                'trendingPosts' => collect(),
                'latestPosts' => collect(),
            ]);
        }

        $featuredPost = (clone $baseQuery)
            ->orderByDesc('views_count')
            ->orderByDesc('created_at')
            ->first();

        $excludeIds = $featuredPost ? [$featuredPost->id] : [];

        $trendingPosts = (clone $baseQuery)
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->orderByDesc('views_count')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $excludeIds = array_merge($excludeIds, $trendingPosts->pluck('id')->all());

        $latestPosts = (clone $baseQuery)
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->orderByDesc('created_at')
            ->limit(9)
            ->get();

        return view('home', [
            'isFiltered' => false,
            'searchQuery' => '',
            'selectedCategory' => '',
            'filteredPosts' => collect(),
            'featuredCategories' => $featuredCategories,
            'stats' => $stats,
            'featuredPost' => $featuredPost,
            'trendingPosts' => $trendingPosts,
            'latestPosts' => $latestPosts,
        ]);
    }

    /**
     * @return Collection<int, array{name: string, slug: string, count: int, url: string, icon: string, color: string}>
     */
    protected function buildFeaturedCategories(): Collection
    {
        $postCounts = Blog::query()
            ->published()
            ->whereNotNull('blog_category_id')
            ->selectRaw('blog_category_id, COUNT(*) as posts_count')
            ->groupBy('blog_category_id')
            ->pluck('posts_count', 'blog_category_id');

        return BlogCategory::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(function (BlogCategory $cat) use ($postCounts) {
                return [
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'count' => (int) ($postCounts[$cat->id] ?? 0),
                    'url' => route('home', ['cat' => $cat->name]),
                    'icon' => $cat->image_url,
                    'color' => Blog::categoryColor($cat->name),
                ];
            });
    }
}
