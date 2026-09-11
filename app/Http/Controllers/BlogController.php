<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(Request $request, string $postType): View
    {
        $postType = $this->resolvePostType($postType);
        $query = $request->string('q')->toString();
        $category = $request->string('category')->toString();

        $posts = Blog::query()
            ->with(['blogCategory', 'blogCategories'])
            ->where('is_published', true)
            ->ofType($postType)
            ->when($query, function ($q) use ($query) {
                $q->where(function ($qq) use ($query) {
                    $qq->where('title', 'like', "%{$query}%")
                        ->orWhere('content', 'like', "%{$query}%");
                });
            })
            ->when($category, fn ($q) => $q->inBlogCategoryName($category))
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $categories = BlogCategory::query()
            ->active()
            ->whereHas('blogs', fn ($q) => $q->published()->ofType($postType))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name');

        $listingMeta = Blog::listingMeta($postType);
        $listingRoute = $postType === Blog::TYPE_BLOG ? 'blogs.index' : 'review.index';

        return view('blog.index', [
            'posts' => $posts,
            'searchQuery' => $query,
            'selectedCategory' => $category,
            'categories' => $categories,
            'postType' => $postType,
            'listingMeta' => $listingMeta,
            'listingRoute' => $listingRoute,
        ]);
    }

    public function show(string $slug, string $postType): View
    {
        $postType = $this->resolvePostType($postType);

        $post = Blog::query()
            ->with(['blogCategory', 'blogCategories', 'deals'])
            ->where('is_published', true)
            ->where('slug', $slug)
            ->ofType($postType)
            ->firstOrFail();

        $post->increment('views_count');

        $categoryIds = $post->resolvedCategoryIds();

        $relatedBlogs = Blog::query()
            ->with(['blogCategory', 'blogCategories'])
            ->where('is_published', true)
            ->ofType($postType)
            ->where('id', '!=', $post->id)
            ->when($categoryIds !== [], fn ($q) => $q->sharingAnyCategory($categoryIds))
            ->orderByDesc('created_at')
            ->limit(4)
            ->get();

        if ($relatedBlogs->count() < 4) {
            $additionalBlogs = Blog::query()
                ->with(['blogCategory', 'blogCategories'])
                ->where('is_published', true)
                ->ofType($postType)
                ->where('id', '!=', $post->id)
                ->whereNotIn('id', $relatedBlogs->pluck('id'))
                ->orderByDesc('created_at')
                ->limit(4 - $relatedBlogs->count())
                ->get();
            $relatedBlogs = $relatedBlogs->merge($additionalBlogs);
        }

        return view('blog.show', [
            'post' => $post,
            'relatedBlogs' => $relatedBlogs,
            'listingMeta' => Blog::listingMeta($postType),
        ]);
    }

    protected function resolvePostType(string $postType): string
    {
        return array_key_exists($postType, Blog::postTypeOptions())
            ? $postType
            : Blog::TYPE_REVIEW;
    }
}
