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

        $baseQuery = Blog::query()->published()->with(['blogCategory', 'blogCategories']);

        $applyFilters = function ($query) use ($searchQuery, $selectedCategory) {
            if ($searchQuery !== '') {
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('title', 'like', "%{$searchQuery}%")
                        ->orWhere('content', 'like', "%{$searchQuery}%");
                });
            }
            if ($selectedCategory !== '') {
                $query->inBlogCategoryName($selectedCategory);
            }
        };

        $stats = [
            'posts' => (clone $baseQuery)->count(),
            'categories' => BlogCategory::query()->active()->count(),
        ];

        $featuredCategories = $this->buildFeaturedCategories();

        if ($isFiltered) {
            $filteredPosts = (clone $baseQuery)->tap($applyFilters)
                ->homeOrder()
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
            ->homeOrder()
            ->first();

        $excludeIds = $featuredPost ? [$featuredPost->id] : [];

        $heroRotationPosts = (clone $baseQuery)
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->homeOrder()
            ->limit(5)
            ->get();

        $excludeIds = array_merge($excludeIds, $heroRotationPosts->pluck('id')->all());

        $trendingPosts = (clone $baseQuery)
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->homeOrder()
            ->limit(5)
            ->get();

        $excludeIds = array_merge($excludeIds, $trendingPosts->pluck('id')->all());

        $latestPosts = (clone $baseQuery)
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->orderByDesc('created_at')
            ->limit(9)
            ->get();

        $categoryPosts = $this->buildCategoryPosts($featuredCategories);

        return view('home', [
            'isFiltered' => false,
            'searchQuery' => '',
            'selectedCategory' => '',
            'filteredPosts' => collect(),
            'featuredCategories' => $featuredCategories,
            'categoryPosts' => $categoryPosts,
            'stats' => $stats,
            'featuredPost' => $featuredPost,
            'heroRotationPosts' => $heroRotationPosts,
            'trendingPosts' => $trendingPosts,
            'latestPosts' => $latestPosts,
        ]);
    }

    /**
     * @return Collection<int, array{name: string, slug: string, count: int, url: string, icon: string, color: string}>
     */
    protected function buildFeaturedCategories(): Collection
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
            ->limit(8)
            ->get()
            ->map(function (BlogCategory $cat) use ($postCounts) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'count' => (int) ($postCounts[$cat->id] ?? 0),
                    'url' => route('home', ['cat' => $cat->name]),
                    'icon' => $cat->image_url,
                    'color' => Blog::categoryColor($cat->name),
                ];
            });
    }

    /**
     * @param  Collection<int, array{name: string, slug: string, count: int, url: string, icon: string, color: string}>  $categories
     * @return Collection<int, array{name: string, slug: string, count: int, url: string, icon: string, color: string, posts: Collection}>
     */
    protected function buildCategoryPosts(Collection $categories): Collection
    {
        $baseQuery = Blog::query()->published()->with(['blogCategory', 'blogCategories']);

        return $categories
            ->filter(fn ($cat) => ($cat['count'] ?? 0) > 0)
            ->take(4)
            ->map(function ($cat) use ($baseQuery) {
                $query = (clone $baseQuery)
                    ->homeOrder()
                    ->limit(4);

                if (! empty($cat['id'])) {
                    $query->inBlogCategory((int) $cat['id']);
                } else {
                    $query->inBlogCategoryName($cat['name']);
                }

                $posts = $query->get();

                return array_merge($cat, ['posts' => $posts]);
            })
            ->filter(fn ($cat) => $cat['posts']->isNotEmpty())
            ->values();
    }
}
