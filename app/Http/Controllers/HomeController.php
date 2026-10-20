<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller
{
    private const CATEGORY_POSTS_LIMIT = 7;

    public function index(Request $request): View
    {
        $searchQuery = $request->string('q')->toString();
        $selectedCategory = $request->string('cat')->toString();
        $isFiltered = $searchQuery !== '' || $selectedCategory !== '';

        $publishedQuery = Blog::query()->published()->with(['blogCategory', 'blogCategories']);
        $reviewQuery = (clone $publishedQuery)->reviews();

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
            'posts' => (clone $reviewQuery)->count(),
            'categories' => BlogCategory::query()->active()->count(),
        ];

        $categoryCatalog = $this->buildCategoryCatalog();
        $featuredCategories = $categoryCatalog;
        $sidebarCategories = $categoryCatalog;

        if ($isFiltered) {
            $filteredPosts = (clone $publishedQuery)->tap($applyFilters)
                ->homeOrder()
                ->limit(24)
                ->get();

            return view('home', [
                'isFiltered' => true,
                'searchQuery' => $searchQuery,
                'selectedCategory' => $selectedCategory,
                'filteredPosts' => $filteredPosts,
                'featuredCategories' => $featuredCategories,
                'sidebarCategories' => $sidebarCategories,
                'stats' => $stats,
                'featuredPost' => null,
                'trendingPosts' => collect(),
                'latestPosts' => collect(),
                'popularPosts' => collect(),
            ]);
        }

        $featuredPost = (clone $reviewQuery)
            ->homeOrder()
            ->first();

        $excludeIds = $featuredPost ? [$featuredPost->id] : [];

        $heroRotationPosts = (clone $reviewQuery)
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->homeOrder()
            ->limit(5)
            ->get();

        $excludeIds = array_merge($excludeIds, $heroRotationPosts->pluck('id')->all());

        $trendingPosts = (clone $reviewQuery)
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->homeOrder()
            ->limit(5)
            ->get();

        $excludeIds = array_merge($excludeIds, $trendingPosts->pluck('id')->all());

        $latestPosts = (clone $reviewQuery)
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->orderByDesc('created_at')
            ->limit(9)
            ->get();

        $popularPosts = (clone $reviewQuery)
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->orderByDesc('views_count')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $categoryPosts = $this->buildCategoryPosts($categoryCatalog);

        return view('home', [
            'isFiltered' => false,
            'searchQuery' => '',
            'selectedCategory' => '',
            'filteredPosts' => collect(),
            'featuredCategories' => $featuredCategories,
            'sidebarCategories' => $sidebarCategories,
            'categoryPosts' => $categoryPosts,
            'stats' => $stats,
            'featuredPost' => $featuredPost,
            'heroRotationPosts' => $heroRotationPosts,
            'trendingPosts' => $trendingPosts,
            'latestPosts' => $latestPosts,
            'popularPosts' => $popularPosts,
        ]);
    }

    /**
     * Danh mục có bài review xuất bản — ưu tiên nhiều bài / nhiều lượt xem.
     *
     * @return Collection<int, array{id: int, name: string, slug: string, count: int, views_sum: int, url: string, icon: string, color: string}>
     */
    protected function buildCategoryCatalog(): Collection
    {
        $statsByCategory = $this->publishedReviewStatsByCategoryId();

        return BlogCategory::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (BlogCategory $cat) use ($statsByCategory) {
                $stats = $statsByCategory[$cat->id] ?? ['count' => 0, 'views_sum' => 0];

                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'count' => (int) $stats['count'],
                    'views_sum' => (int) $stats['views_sum'],
                    'url' => route('home', ['cat' => $cat->name]),
                    'icon' => $cat->image_url,
                    'color' => Blog::categoryColor($cat->name),
                ];
            })
            ->filter(fn (array $cat) => $cat['count'] > 0)
            ->sort(function (array $a, array $b): int {
                if ($a['count'] !== $b['count']) {
                    return $b['count'] <=> $a['count'];
                }

                if ($a['views_sum'] !== $b['views_sum']) {
                    return $b['views_sum'] <=> $a['views_sum'];
                }

                return strcasecmp($a['name'], $b['name']);
            })
            ->values();
    }

    /**
     * @return array<int, array{count: int, views_sum: int}>
     */
    protected function publishedReviewStatsByCategoryId(): array
    {
        $stats = [];

        $merge = function (int $categoryId, int $count, int $viewsSum) use (&$stats): void {
            if ($categoryId <= 0) {
                return;
            }

            if (! isset($stats[$categoryId])) {
                $stats[$categoryId] = ['count' => 0, 'views_sum' => 0];
            }

            $stats[$categoryId]['count'] += $count;
            $stats[$categoryId]['views_sum'] += $viewsSum;
        };

        $pivotRows = DB::table('blog_blog_category')
            ->join('blogs', 'blogs.id', '=', 'blog_blog_category.blog_id')
            ->where('blogs.is_published', true)
            ->where('blogs.post_type', Blog::TYPE_REVIEW)
            ->whereNull('blogs.deleted_at')
            ->groupBy('blog_blog_category.blog_category_id')
            ->selectRaw('blog_blog_category.blog_category_id as category_id, COUNT(DISTINCT blogs.id) as posts_count, COALESCE(SUM(blogs.views_count), 0) as views_sum')
            ->get();

        foreach ($pivotRows as $row) {
            $merge((int) $row->category_id, (int) $row->posts_count, (int) $row->views_sum);
        }

        $legacyRows = Blog::query()
            ->published()
            ->reviews()
            ->whereDoesntHave('blogCategories')
            ->whereNotNull('blog_category_id')
            ->groupBy('blog_category_id')
            ->selectRaw('blog_category_id as category_id, COUNT(*) as posts_count, COALESCE(SUM(views_count), 0) as views_sum')
            ->get();

        foreach ($legacyRows as $row) {
            $merge((int) $row->category_id, (int) $row->posts_count, (int) $row->views_sum);
        }

        return $stats;
    }

    /**
     * @param  Collection<int, array{id: int, name: string, slug: string, count: int, views_sum: int, url: string, icon: string, color: string}>  $categories
     * @return Collection<int, array{id: int, name: string, slug: string, count: int, views_sum: int, url: string, icon: string, color: string, posts: Collection}>
     */
    protected function buildCategoryPosts(Collection $categories): Collection
    {
        $baseQuery = Blog::query()->published()->reviews()->with(['blogCategory', 'blogCategories']);

        return $categories
            ->map(function (array $cat) use ($baseQuery) {
                $query = (clone $baseQuery)
                    ->homeOrder()
                    ->limit(self::CATEGORY_POSTS_LIMIT);

                if (! empty($cat['id'])) {
                    $query->inBlogCategory((int) $cat['id']);
                } else {
                    $query->inBlogCategoryName($cat['name']);
                }

                $posts = $query->get();

                return array_merge($cat, ['posts' => $posts]);
            })
            ->filter(fn (array $cat) => $cat['posts']->isNotEmpty())
            ->values();
    }
}
