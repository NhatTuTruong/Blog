@extends('layouts.app')

@section('title', 'Blog - ' . config('app.name'))
@section('description', 'Latest articles and blog posts.')

@push('styles')
<style>
    .container { max-width: 1200px; margin: 0 auto; padding: 0 1.5rem; }

    .blog-hero {
        padding: 3.5rem 0 2.5rem;
        background: radial-gradient(900px 350px at 50% 0%, rgba(34,197,94,0.16) 0%, rgba(34,197,94,0.00) 70%);
        border-bottom: 1px solid var(--border);
    }
    .blog-hero-inner { text-align: center; }
    .blog-hero h1 {
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(2rem, 4vw, 3rem);
        font-weight: 800;
        letter-spacing: -0.03em;
    }
    .blog-hero p {
        color: var(--text-muted);
        margin: 0.75rem auto 0;
        max-width: 720px;
        font-size: 1.05rem;
    }

    .blog-toolbar {
        margin-top: 1.5rem;
        display: flex;
        justify-content: center;
    }
    .blog-search {
        width: 100%;
        max-width: 720px;
        display: flex;
        gap: 0.75rem;
        background: rgba(255,255,255,0.95);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 0.5rem;
        box-shadow: 0 6px 24px rgba(17,24,39,0.06);
    }
    .blog-search input {
        flex: 1;
        border: none;
        outline: none;
        background: transparent;
        padding: 0.75rem 0.9rem;
        font-size: 1rem;
        color: var(--text);
    }
    .blog-search button {
        border: none;
        background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
        color: #fff;
        padding: 0.75rem 1.1rem;
        border-radius: 12px;
        font-weight: 700;
        cursor: pointer;
        white-space: nowrap;
    }
    .blog-search button:hover { opacity: 0.95; }

    .category-chips {
        margin-top: 1.25rem;
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        justify-content: center;
    }
    .chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.4rem 0.8rem;
        border: 1px solid var(--border);
        border-radius: 999px;
        text-decoration: none;
        color: var(--text);
        background: #fff;
        font-size: 0.9rem;
        transition: border-color 0.2s, background 0.2s, color 0.2s;
    }
    .chip:hover { border-color: var(--accent); color: var(--accent); }
    .chip-active {
        background: rgba(34,197,94,0.10);
        border-color: rgba(34,197,94,0.35);
        color: #166534;
        font-weight: 700;
    }

    .blog-wrap { padding: 2rem 0 3rem; }
    .posts-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1.25rem;
    }
    @media (max-width: 1024px) {
        .posts-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 640px) {
        .posts-grid { grid-template-columns: 1fr; }
        .blog-search { flex-direction: column; }
        .blog-search button { width: 100%; }
    }

    .post-card {
        display: flex;
        flex-direction: column;
        border: 1px solid var(--border);
        border-radius: 16px;
        overflow: hidden;
        background: #fff;
        text-decoration: none;
        color: inherit;
        transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
        min-height: 100%;
    }
    .post-card:hover {
        border-color: rgba(34,197,94,0.5);
        box-shadow: 0 12px 28px rgba(17,24,39,0.08);
        transform: translateY(-2px);
    }
    .post-thumb {
        width: 100%;
        height: 180px;
        object-fit: cover;
        background: var(--surface);
        display: block;
    }
    .post-thumb-placeholder {
        width: 100%;
        height: 180px;
        background: linear-gradient(135deg, rgba(34,197,94,0.08) 0%, rgba(34,197,94,0.00) 70%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
        font-weight: 700;
        letter-spacing: 0.02em;
    }
    .post-body { padding: 1.1rem 1.1rem 1rem; display: flex; flex-direction: column; gap: 0.6rem; flex: 1; }
    .post-topline { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
    .post-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.55rem;
        border-radius: 999px;
        background: rgba(34,197,94,0.10);
        color: #166534;
        border: 1px solid rgba(34,197,94,0.25);
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        max-width: 70%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .post-date { font-size: 0.8rem; color: var(--text-muted); white-space: nowrap; }
    .post-title {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.15rem;
        font-weight: 800;
        line-height: 1.3;
        letter-spacing: -0.02em;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .post-excerpt {
        font-size: 0.95rem;
        color: var(--text-muted);
        line-height: 1.55;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .post-cta {
        margin-top: auto;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        color: var(--accent);
        font-weight: 700;
        font-size: 0.95rem;
    }
    .pagination-wrap {
        margin-top: 2rem;
        display: flex;
        justify-content: center;
    }
    .pagination-wrap nav a, .pagination-wrap nav span {
        display: inline-block;
        padding: 0.5rem 0.75rem;
        margin: 0 0.15rem;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        color: var(--text);
        text-decoration: none;
        font-size: 0.9rem;
    }
    .pagination-wrap nav a:hover { border-color: var(--accent); color: var(--accent); }
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--text-muted);
    }
</style>
@endpush

@section('content')
    <header class="blog-hero">
        <div class="container blog-hero-inner">
            <h1 class="font-heading">Blog</h1>
            <p>Guides, stories and updates — written to inform and inspire.</p>

            <div class="blog-toolbar">
                <form action="{{ route('blog.index') }}" method="get" class="blog-search">
                    <input type="search" name="q" value="{{ $searchQuery ?? '' }}" placeholder="Search articles…" autocomplete="off">
                    @if(!empty($selectedCategory))
                        <input type="hidden" name="category" value="{{ $selectedCategory }}">
                    @endif
                    <button type="submit">Search</button>
                </form>
            </div>

            @if(isset($categories) && $categories->count() > 0)
                <nav class="category-chips" aria-label="Blog categories">
                    @php
                        $baseParams = [];
                        if (!empty($searchQuery)) $baseParams['q'] = $searchQuery;
                    @endphp
                    <a class="chip {{ empty($selectedCategory) ? 'chip-active' : '' }}" href="{{ route('blog.index', $baseParams) }}">All</a>
                    @foreach($categories as $cat)
                        <a class="chip {{ ($selectedCategory ?? '') === $cat ? 'chip-active' : '' }}"
                           href="{{ route('blog.index', array_merge($baseParams, ['category' => $cat])) }}">
                            {{ $cat }}
                        </a>
                    @endforeach
                </nav>
            @endif
        </div>
    </header>

    <div class="blog-wrap">
        <div class="container">
            @if($posts->count() > 0)
                <div class="posts-grid">
                    @foreach($posts as $post)
                        <a href="{{ route('blog.show', $post->slug) }}" class="post-card">
                            <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="post-thumb" loading="lazy">
                            <div class="post-body">
                                <div class="post-topline">
                                    @if(!empty($post->category))
                                        <span class="post-badge">{{ $post->category }}</span>
                                    @else
                                        <span class="post-badge">News</span>
                                    @endif
                                    <span class="post-date">{{ $post->created_at?->format('d/m/Y') }}</span>
                                </div>
                                <h2 class="post-title">{{ $post->title }}</h2>
                                @if($post->content)
                                    <p class="post-excerpt">{{ Str::limit(trim(strip_tags($post->content)), 160) }}</p>
                                @endif
                                <span class="post-cta">Read article →</span>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="pagination-wrap">
                    {{ $posts->links('vendor.pagination.simple') }}
                </div>
            @else
                <div class="empty-state">
                    No blog posts available yet. Please check back later!
                </div>
            @endif
        </div>
    </div>
@endsection
