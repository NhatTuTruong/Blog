@extends('layouts.app')

@section('title', \App\Support\SiteSeo::pageTitle('blog'))
@section('description', \App\Support\SiteSeo::pageDescription('blog'))
@section('canonical', url()->current())

@push('styles')
@include('partials.blog-category-tags-styles')
<style>
    /* Blog Index Page — matches home page bh- system */
    .blog-index {
        --bh-ink: #0f172a;
        --bh-muted: #64748b;
        --bh-light: #f8fafc;
        --bh-card: #ffffff;
        --bh-accent: #2563eb;
        --bh-accent2: #3b82f6;
        --bh-border: #e2e8f0;
        background: var(--bh-card);
        color: var(--bh-ink);
    }

    /* ================================
       HERO BANNER - MODERN
       ================================ */
    .blog-hero {
        position: relative;
        min-height: 50vh;
        display: flex;
        align-items: center;
        overflow: hidden;
        background: #0f172a;
    }
    .blog-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 80% 60% at 20% 20%, rgba(37, 99, 235, 0.4) 0%, transparent 50%),
            radial-gradient(ellipse 60% 50% at 80% 80%, rgba(139, 92, 246, 0.3) 0%, transparent 50%),
            radial-gradient(ellipse 40% 40% at 50% 50%, rgba(59, 130, 246, 0.15) 0%, transparent 50%);
    }
    .blog-hero__particles {
        position: absolute;
        inset: 0;
        overflow: hidden;
    }
    .blog-hero__particle {
        position: absolute;
        width: 4px;
        height: 4px;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        animation: particle-float 20s linear infinite;
    }
    @keyframes particle-float {
        0% { transform: translateY(100vh) scale(0); opacity: 0; }
        10% { opacity: 1; }
        90% { opacity: 1; }
        100% { transform: translateY(-10vh) scale(1); opacity: 0; }
    }
    .blog-hero__content {
        position: relative;
        z-index: 2;
        width: 100%;
        max-width: 1280px;
        margin: 0 auto;
        padding: 4rem 2rem;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
        align-items: center;
    }
    @media (max-width: 900px) {
        .blog-hero__content {
            grid-template-columns: 1fr;
            text-align: center;
            gap: 2rem;
            padding: 3rem 1.5rem;
        }
    }
    .blog-hero__text {}
    .blog-hero__label {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: rgba(37, 99, 235, 0.2);
        border: 1px solid rgba(37, 99, 235, 0.4);
        border-radius: 999px;
        color: #60a5fa;
        font-size: 0.8rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
    }
    .blog-hero__label svg {
        width: 14px;
        height: 14px;
    }
    .blog-hero__title {
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(2.5rem, 5vw, 4rem);
        font-weight: 800;
        line-height: 1.1;
        letter-spacing: -0.03em;
        color: #fff;
        margin: 0 0 1.25rem;
    }
    .blog-hero__title .highlight {
        background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 50%, #60a5fa 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        background-size: 200% 200%;
        animation: gradient-shift 4s ease infinite;
    }
    @keyframes gradient-shift {
        0%, 100% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
    }
    .blog-hero__subtitle {
        font-size: 1.15rem;
        color: rgba(255, 255, 255, 0.65);
        line-height: 1.7;
        margin: 0 0 2rem;
        max-width: 480px;
    }
    @media (max-width: 900px) {
        .blog-hero__subtitle { margin: 0 auto 2rem; }
    }
    .blog-hero__search {
        display: flex;
        max-width: 480px;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 14px;
        overflow: hidden;
        backdrop-filter: blur(12px);
    }
    @media (max-width: 900px) {
        .blog-hero__search { margin: 0 auto; }
    }
    .blog-hero__search input {
        flex: 1;
        border: none;
        background: transparent;
        padding: 1rem 1.25rem;
        font-size: 0.95rem;
        color: #fff;
        outline: none;
    }
    .blog-hero__search input::placeholder {
        color: rgba(255, 255, 255, 0.45);
    }
    .blog-hero__search button {
        border: none;
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: #fff;
        padding: 0.875rem 1.5rem;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s;
    }
    .blog-hero__search button:hover {
        background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    }
    .blog-hero__search button svg {
        width: 18px;
        height: 18px;
    }
    .blog-hero__stats {
        display: flex;
        gap: 2rem;
        margin-top: 2rem;
    }
    @media (max-width: 900px) {
        .blog-hero__stats { justify-content: center; }
    }
    .blog-hero__stat strong {
        display: block;
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.75rem;
        font-weight: 800;
        color: #fff;
        line-height: 1;
    }
    .blog-hero__stat span {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.5);
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .blog-hero__cards {
        position: relative;
        height: 320px;
    }
    @media (max-width: 900px) {
        .blog-hero__cards { display: none; }
    }
    .blog-hero__card {
        position: absolute;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        backdrop-filter: blur(12px);
        overflow: hidden;
        transition: transform 0.4s ease;
    }
    .blog-hero__card:nth-child(1) {
        width: 200px;
        height: 260px;
        top: 0;
        right: 60px;
        animation: card-float 6s ease-in-out infinite;
    }
    .blog-hero__card:nth-child(2) {
        width: 180px;
        height: 220px;
        bottom: 0;
        right: 20px;
        animation: card-float 6s ease-in-out infinite 1s;
    }
    .blog-hero__card:nth-child(3) {
        width: 160px;
        height: 180px;
        top: 20px;
        right: 0;
        animation: card-float 6s ease-in-out infinite 2s;
    }
    @keyframes card-float {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        50% { transform: translateY(-15px) rotate(1deg); }
    }
    .blog-hero__card-img {
        width: 100%;
        height: 120px;
        object-fit: cover;
    }
    .blog-hero__card:nth-child(2) .blog-hero__card-img { height: 100px; }
    .blog-hero__card:nth-child(3) .blog-hero__card-img { height: 80px; }
    .blog-hero__card-body {
        padding: 0.75rem;
    }
    .blog-hero__card-cat {
        font-size: 0.65rem;
        font-weight: 700;
        color: #60a5fa;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.25rem;
    }
    .blog-hero__card-title {
        font-size: 0.75rem;
        font-weight: 600;
        color: #fff;
        line-height: 1.3;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* ================================
       CATEGORIES PILLS
       ================================ */
    .blog-hero__cats {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1.5rem;
    }
    @media (max-width: 900px) {
        .blog-hero__cats { justify-content: center; }
    }
    .blog-hero__cat {
        padding: 0.5rem 1rem;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 999px;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 500;
        transition: all 0.25s;
    }
    .blog-hero__cat:hover,
    .blog-hero__cat.active {
        background: rgba(255, 255, 255, 0.12);
        border-color: rgba(37, 99, 235, 0.5);
        color: #fff;
        transform: translateY(-2px);
    }

    /* ================================
       WRAP
       ================================ */
    .bh-wrap {
        max-width: 1280px;
        margin: 0 auto;
        padding: 0 2rem;
    }
    @media (max-width: 768px) {
        .bh-wrap { padding: 0 1rem; }
    }

    /* ================================
       SECTION
       ================================ */
    .bh-section {
        padding: 3rem 0;
    }
    .bh-section--alt {
        background: var(--bh-light);
    }
    .bh-section__header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }
    .bh-section__header h2 {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
        letter-spacing: -0.02em;
    }

    /* ================================
       FILTERS / RESULTS BAR
       ================================ */
    .blog-results {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.25rem 1.5rem;
        background: var(--bh-light);
        border-radius: 14px;
        margin-bottom: 2rem;
    }
    .blog-results p { margin: 0; color: var(--bh-muted); font-size: 0.95rem; }
    .blog-results strong { color: var(--bh-ink); }
    .blog-results a {
        color: var(--bh-accent);
        font-weight: 600;
        text-decoration: none;
        font-size: 0.9rem;
    }
    .blog-results a:hover { text-decoration: underline; }

    /* ================================
       POSTS GRID
       ================================ */
    .bh-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 2rem;
    }
    @media (max-width: 1024px) {
        .bh-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 640px) {
        .bh-grid { grid-template-columns: 1fr; }
    }

    /* Card */
    .bh-card {
        background: var(--bh-card);
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid var(--bh-border);
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
    }
    .bh-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        border-color: transparent;
    }
    .bh-card__img {
        width: 100%;
        aspect-ratio: 16/10;
        object-fit: cover;
        display: block;
        transition: transform 0.5s ease;
    }
    .bh-card:hover .bh-card__img {
        transform: scale(1.08);
    }
    .bh-card__media {
        overflow: hidden;
        position: relative;
    }
    .bh-card__body {
        padding: 1.5rem;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    .bh-card__title {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.15rem;
        font-weight: 700;
        line-height: 1.4;
        margin: 0 0 0.75rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .bh-card__title a {
        color: var(--bh-ink);
        text-decoration: none;
        transition: color 0.2s;
    }
    .bh-card__title a:hover {
        color: var(--bh-accent);
    }
    .bh-card__excerpt {
        font-size: 0.9rem;
        color: var(--bh-muted);
        line-height: 1.6;
        margin: 0 0 1rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        flex: 1;
    }
    .bh-card__meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 1rem;
        border-top: 1px solid var(--bh-border);
        font-size: 0.8rem;
        color: var(--bh-muted);
    }
    .bh-card__read {
        color: var(--bh-accent);
        font-weight: 600;
        text-decoration: none;
    }
    .bh-card__read:hover {
        text-decoration: underline;
    }

    /* ================================
       PAGINATION
       ================================ */
    .bh-pagination {
        margin-top: 3rem;
        display: flex;
        justify-content: center;
    }

    /* ================================
       EMPTY STATE
       ================================ */
    .bh-empty {
        text-align: center;
        padding: 5rem 2rem;
        background: var(--bh-light);
        border-radius: 20px;
    }
    .bh-empty h2 {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.5rem;
        margin: 0 0 0.5rem;
    }
    .bh-empty p {
        color: var(--bh-muted);
        margin: 0;
    }

    /* Mobile */
    @media (max-width: 640px) {
        .blog-hero__stats { gap: 1.5rem; }
        .blog-hero__stat strong { font-size: 1.5rem; }
        .blog-hero__search { flex-direction: column; border-radius: 14px; }
        .blog-hero__search button { border-radius: 10px; width: 100%; }
        .blog-hero__cats { gap: 0.5rem; }
        .blog-hero__cat { padding: 0.5rem 1rem; font-size: 0.8rem; }
        .blog-results { flex-direction: column; gap: 1rem; text-align: center; }
    }
</style>
@endpush

@section('content')
<div class="blog-index">
    {{-- HERO BANNER --}}
    <section class="blog-hero">
        <div class="blog-hero__particles">
            <div class="blog-hero__particle" style="left: 10%; animation-delay: 0s;"></div>
            <div class="blog-hero__particle" style="left: 20%; animation-delay: 2s;"></div>
            <div class="blog-hero__particle" style="left: 35%; animation-delay: 4s;"></div>
            <div class="blog-hero__particle" style="left: 50%; animation-delay: 1s;"></div>
            <div class="blog-hero__particle" style="left: 65%; animation-delay: 3s;"></div>
            <div class="blog-hero__particle" style="left: 80%; animation-delay: 5s;"></div>
            <div class="blog-hero__particle" style="left: 90%; animation-delay: 2.5s;"></div>
        </div>

        <div class="blog-hero__content">
            <div class="blog-hero__text">
                <div class="blog-hero__label">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 12h10"/></svg>
                    Digital Magazine
                </div>
                <h1 class="blog-hero__title">
                    Discover Stories That <span class="highlight">Inspire</span> You
                </h1>
                <p class="blog-hero__subtitle">
                    Browse all guides, stories and insights from {{ config('app.name') }}. Search by topic or filter by category.
                </p>

                <form class="blog-hero__search" action="{{ route('blog.index') }}" method="get">
                    <input type="search" name="q" value="{{ $searchQuery ?? '' }}" placeholder="Search articles..." aria-label="Search">
                    <button type="submit">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Search
                    </button>
                </form>

                <div class="blog-hero__stats">
                    <div class="blog-hero__stat">
                        <strong>{{ number_format($posts->total()) }}</strong>
                        <span>Articles</span>
                    </div>
                    @if(isset($categories) && $categories->count() > 0)
                    <div class="blog-hero__stat">
                        <strong>{{ number_format($categories->count()) }}</strong>
                        <span>Topics</span>
                    </div>
                    @endif
                </div>
            </div>

            <div class="blog-hero__cards">
                @php
                    $featuredPosts = $posts->take(3);
                @endphp
                @foreach($featuredPosts as $index => $post)
                <div class="blog-hero__card" style="animation-delay: {{ $index * 0.5 }}s;">
                    <img src="{{ $post->featured_image_url ?? 'https://picsum.photos/seed/' . $post->id . '/400/300' }}" alt="" class="blog-hero__card-img">
                    @include('partials.blog-category-tags', ['post' => $post, 'variant' => 'overlay', 'compact' => true])
                    <div class="blog-hero__card-body">
                        <div class="blog-hero__card-title">{{ $post->title }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CATEGORIES --}}
    @if(isset($categories) && $categories->count() > 0)
    <div style="background: #0f172a; padding: 1rem 0; border-top: 1px solid rgba(255,255,255,0.05);">
        <div class="bh-wrap">
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
                <span style="color: rgba(255,255,255,0.5); font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-right: 0.5rem;">Topics:</span>
                @php
                    $baseParams = [];
                    if (!empty($searchQuery)) $baseParams['q'] = $searchQuery;
                @endphp
                <a href="{{ route('blog.index', $baseParams) }}" class="blog-hero__cat {{ empty($selectedCategory) ? 'active' : '' }}">All</a>
                @foreach($categories as $cat)
                    <a href="{{ route('blog.index', array_merge($baseParams, ['category' => $cat])) }}"
                       class="blog-hero__cat {{ ($selectedCategory ?? '') === $cat ? 'active' : '' }}">
                        {{ $cat }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- POSTS --}}
    <section class="bh-section">
        <div class="bh-wrap">
            @if($searchQuery || $selectedCategory)
                <div class="blog-results">
                    <p>
                        @if($searchQuery && $selectedCategory)
                            Results for <strong>"{{ $searchQuery }}"</strong> in <strong>{{ $selectedCategory }}</strong>
                        @elseif($searchQuery)
                            Results for <strong>"{{ $searchQuery }}"</strong>
                        @elseif($selectedCategory)
                            Topic: <strong>{{ $selectedCategory }}</strong>
                        @endif
                        — {{ $posts->count() }} {{ Str::plural('article', $posts->count()) }}
                    </p>
                    <a href="{{ route('blog.index') }}">Clear filters ×</a>
                </div>
            @endif

            @if($posts->count() > 0)
                <div class="bh-grid">
                    @foreach($posts as $post)
                    <article class="bh-card">
                        <a href="{{ route('blog.show', $post->slug) }}" class="bh-card__media">
                            <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="bh-card__img" loading="lazy" decoding="async">
                            @include('partials.blog-category-tags', ['post' => $post, 'variant' => 'overlay'])
                        </a>
                        <div class="bh-card__body">
                            @if(!$post->featured_image_url)
                                @include('partials.blog-category-tags', ['post' => $post, 'variant' => 'inline'])
                            @endif
                            <h3 class="bh-card__title">
                                <a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a>
                            </h3>
                            @if($post->content)
                                <p class="bh-card__excerpt">{{ Str::limit(trim(strip_tags($post->content)), 150) }}</p>
                            @endif
                            <div class="bh-card__meta">
                                <span>{{ $post->created_at?->format('M j, Y') }} · {{ $post->reading_minutes ?? 5 }} min</span>
                                <span class="bh-card__read">Read →</span>
                            </div>
                        </div>
                    </article>
                    @endforeach
                </div>

                @if($posts->hasPages())
                <div style="margin-top: 3rem; display: flex; justify-content: center;">
                    <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                        @if($posts->onFirstPage())
                            <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.6rem 1rem; font-size: 0.875rem; font-weight: 500; color: #cbd5e1; background: #f8fafc; border-radius: 8px; cursor: not-allowed;">
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
                                Prev
                            </span>
                        @else
                            <a href="{{ $posts->previousPageUrl() }}" style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.6rem 1rem; font-size: 0.875rem; font-weight: 600; color: #1e293b; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.borderColor='#2563eb';this.style.color='#2563eb'" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#1e293b'">
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
                                Prev
                            </a>
                        @endif

                        @php
                            $current = $posts->currentPage();
                            $last = $posts->lastPage();
                            $delta = 2;
                            $left = max(1, $current - $delta);
                            $right = min($last, $current + $delta);
                        @endphp

                        @if($left > 1)
                            <a href="{{ $posts->url(1) }}" style="display: inline-flex; align-items: center; justify-content: center; min-width: 40px; padding: 0.6rem; font-size: 0.875rem; font-weight: 500; color: #64748b; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.borderColor='#2563eb';this.style.color='#2563eb'" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#64748b'">1</a>
                            @if($left > 2)<span style="padding: 0 0.25rem; color: #94a3b8;">...</span>@endif
                        @endif

                        @for($i = $left; $i <= $right; $i++)
                            @if($i == $current)
                                <span style="display: inline-flex; align-items: center; justify-content: center; min-width: 40px; padding: 0.6rem; font-size: 0.875rem; font-weight: 700; color: #fff; background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); border: 1px solid transparent; border-radius: 8px; box-shadow: 0 2px 4px rgba(37,99,235,0.3);">{{ $i }}</span>
                            @else
                                <a href="{{ $posts->url($i) }}" style="display: inline-flex; align-items: center; justify-content: center; min-width: 40px; padding: 0.6rem; font-size: 0.875rem; font-weight: 500; color: #64748b; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.borderColor='#2563eb';this.style.color='#2563eb'" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#64748b'">{{ $i }}</a>
                            @endif
                        @endfor

                        @if($right < $last)
                            @if($right < $last - 1)<span style="padding: 0 0.25rem; color: #94a3b8;">...</span>@endif
                            <a href="{{ $posts->url($last) }}" style="display: inline-flex; align-items: center; justify-content: center; min-width: 40px; padding: 0.6rem; font-size: 0.875rem; font-weight: 500; color: #64748b; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.borderColor='#2563eb';this.style.color='#2563eb'" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#64748b'">{{ $last }}</a>
                        @endif

                        @if($posts->hasMorePages())
                            <a href="{{ $posts->nextPageUrl() }}" style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.6rem 1rem; font-size: 0.875rem; font-weight: 600; color: #1e293b; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.borderColor='#2563eb';this.style.color='#2563eb'" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#1e293b'">
                                Next
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
                            </a>
                        @else
                            <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.6rem 1rem; font-size: 0.875rem; font-weight: 500; color: #cbd5e1; background: #f8fafc; border-radius: 8px; cursor: not-allowed;">
                                Next
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
                            </span>
                        @endif
                    </div>
                </div>
                @endif
            @else
                <div class="bh-empty">
                    <h2>No articles found</h2>
                    <p>Try a different keyword or browse all topics.</p>
                </div>
            @endif
        </div>
    </section>
</div>
@endsection
