@extends('layouts.app')

@section('title', $post->title . ' - ' . config('app.name'))
@section('description', Str::limit(strip_tags($post->renderedContent()), 160))

@push('styles')
<style>
    :root {
        --blog-bg: #ffffff;
        --blog-surface: #ffffff;
        --blog-border: rgba(15, 23, 42, 0.12);
        --blog-text: #0f172a;
        --blog-muted: #64748b;
        --blog-accent: #2563eb;
        --blog-accent-soft: rgba(37, 99, 235, 0.10);
    }

    body {
        background: #ffffff;
    }

    .blog-shell {
        max-width: 1440px;
        margin: 0 auto 3.5rem;
        padding: 1.75rem 0.2rem 3rem;
    }

    @media (min-width: 1024px) {
        .blog-shell {
            padding-top: 2.5rem;
        }
    }

    .blog-breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8rem;
        color: var(--blog-muted);
        margin-bottom: 1rem;
    }

    .blog-breadcrumb a {
        color: var(--blog-muted);
        text-decoration: none;
    }
    .blog-breadcrumb a:hover {
        color: var(--blog-accent);
    }

    .blog-hero {
        position: relative;
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
        align-items: stretch;
        border-radius: 1.25rem;
        overflow: hidden;
        min-height: 0;
        background:
            radial-gradient(circle at top left, rgba(37, 99, 235, 0.1), transparent 52%),
            #ffffff;
        border: 1px solid var(--blog-border);
        box-shadow: 0 16px 48px rgba(15, 23, 42, 0.1);
    }

    .blog-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #2563eb, #60a5fa, #2563eb);
        z-index: 2;
    }

    .blog-hero-main {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 1.35rem 1.5rem;
        min-height: 260px;
    }

    .blog-hero-eyebrow {
        display: inline-flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin-bottom: 0.75rem;
    }

    .blog-hero-eyebrow span {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.28rem 0.7rem;
        border-radius: 999px;
        background: #f8fafc;
        border: 1px solid var(--blog-border);
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--blog-muted);
    }

    .blog-hero-eyebrow-cat {
        background: var(--blog-accent-soft) !important;
        border-color: rgba(37, 99, 235, 0.25) !important;
        color: var(--blog-accent) !important;
    }

    .blog-title {
        font-family: 'Space Grotesk', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        font-size: clamp(1.55rem, 2.6vw, 2.15rem);
        font-weight: 800;
        letter-spacing: -0.03em;
        line-height: 1.2;
        color: var(--blog-text);
        margin-bottom: 0.65rem;
    }

    .blog-meta {
        font-size: 0.85rem;
        color: var(--blog-muted);
    }

    .blog-meta a {
        color: var(--blog-accent);
        text-decoration: underline;
    }

    .blog-hero-media {
        position: relative;
        min-height: 260px;
        max-height: 300px;
        background: #f1f5f9;
        overflow: hidden;
    }

    .blog-hero-media-inner {
        width: 100%;
        height: 100%;
    }

    .blog-hero-media img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
    }

    .blog-hero-media-overlay {
        display: none;
    }

    .blog-hero-media-fallback {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--blog-muted);
        font-size: 0.8rem;
    }

    @media (max-width: 900px) {
        .blog-hero {
            grid-template-columns: minmax(0, 1fr);
        }

        .blog-hero-media {
            order: -1;
            min-height: 220px;
            max-height: 240px;
        }

        .blog-hero-main {
            min-height: 0;
            padding: 1.15rem 1.25rem 1.35rem;
        }
    }

    @media (max-width: 640px) {
        .blog-title {
            font-size: clamp(1.35rem, 6vw, 1.75rem);
        }
    }

    .blog-hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.55rem;
        margin-top: 0.85rem;
    }

    .blog-hero-share {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.85rem;
        border-radius: 999px;
        border: 1px solid var(--blog-border);
        background: #ffffff;
        color: var(--blog-muted);
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
        transition: border-color 0.2s, color 0.2s;
    }

    .blog-hero-share:hover {
        border-color: var(--blog-accent);
        color: var(--blog-accent);
    }

    .blog-main-grid {
        display: grid;
        grid-template-columns: minmax(0, 5fr) minmax(0, 2fr);
        gap: 2.25rem;
        margin-top: 2rem;
    }

    @media (max-width: 900px) {
        .blog-main-grid {
            grid-template-columns: minmax(0, 1fr);
            gap: 2rem;
        }
    }

    .blog-main {
        min-width: 0;
        background: #ffffff;
        border-radius: 1.25rem;
        border: 1px solid var(--blog-border);
        padding: 1.75rem 1.75rem 2rem;
        box-shadow: 0 18px 60px rgba(15, 23, 42, 0.08);
    }

    @media (max-width: 640px) {
        .blog-main {
            padding: 1.5rem 1.25rem 1.75rem;
        }
    }

    .blog-back {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        margin-bottom: 1.25rem;
        color: var(--blog-muted);
        text-decoration: none;
        font-size: 0.82rem;
    }

    .blog-back span.icon {
        width: 18px;
        height: 18px;
        border-radius: 999px;
        border: 1px solid var(--blog-border);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.65rem;
    }

    .blog-back:hover {
        color: var(--blog-accent);
    }

    .blog-chip-row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .blog-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.35rem 0.7rem;
        border-radius: 999px;
        border: 1px solid var(--blog-border);
        background: #ffffff;
        color: var(--blog-muted);
        font-size: 0.8rem;
    }

    .blog-chip-accent {
        border-color: rgba(22, 163, 74, 0.35);
        background: var(--blog-accent-soft);
        color: #166534;
    }

    .blog-share-button {
        margin-left: auto;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.8rem;
        border-radius: 999px;
        border: 1px solid var(--blog-border);
        background: #ffffff;
        cursor: pointer;
        font-size: 0.8rem;
        color: var(--blog-muted);
    }

    .blog-share-button:hover {
        border-color: var(--blog-accent);
        color: var(--blog-accent);
    }

    @media (max-width: 640px) {
        .blog-share-button {
            margin-left: 0;
        }
    }

    .blog-content.prose {
        color: var(--blog-text);
        font-size: 0.98rem;
        line-height: 1.8;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .blog-content.prose pre {
        white-space: pre-wrap;
        overflow-x: auto;
        max-width: 100%;
    }

    .blog-content.prose pre code {
        white-space: inherit;
    }

    .blog-content.prose h2,
    .blog-content.prose h3,
    .blog-content.prose h4 {
        font-family: 'Space Grotesk', system-ui, sans-serif;
        font-weight: 750;
        letter-spacing: -0.02em;
        margin: 1.75rem 0 0.75rem;
        line-height: 1.3;
        color: var(--blog-text);
    }

    .blog-content.prose h2 { font-size: 1.25rem; }
    .blog-content.prose h3 { font-size: 1.05rem; }

    .blog-content.prose p { margin: 0.9rem 0; }
    .blog-content.prose ul,
    .blog-content.prose ol { margin: 0.75rem 0 1.1rem; padding-left: 1.3rem; }
    .blog-content.prose li { margin: 0.35rem 0; }

    .blog-content.prose a {
        color: var(--blog-accent);
        text-decoration: underline;
    }

    .blog-content.prose img {
        max-width: 100%;
        border-radius: 0.9rem;
        border: 1px solid var(--blog-border);
    }

    .blog-content.prose .blog-inline-image {
        margin: 1.75rem 0;
        text-align: center;
    }

    .blog-content.prose .blog-inline-image img {
        width: 100%;
        height: auto;
        display: block;
        margin: 0 auto;
    }

    .blog-side-media {
        margin-top: 1.75rem;
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    }

    .blog-side-media img {
        width: 100%;
        height: auto;
        border-radius: 0.75rem;
        border: 1px solid var(--blog-border);
    }

    .blog-side-media video {
        width: 100%;
        border-radius: 0.75rem;
        border: 1px solid var(--blog-border);
    }

    .blog-aside {
        min-width: 0;
        border-radius: 1.25rem;
        border: 1px solid var(--blog-border);
        background: #ffffff;
        padding: 1.5rem 1.5rem 1.75rem;
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
        position: sticky;
        top: 1.5rem;
        height: fit-content;
    }

    @media (max-width: 900px) {
        .blog-aside {
            position: static;
        }
    }

    .blog-aside-title {
        font-family: 'Space Grotesk', system-ui, sans-serif;
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: 1rem;
        color: var(--blog-text);
    }

    .blog-aside-related a {
        text-decoration: none;
        color: inherit;
    }

    .related-blogs {
        margin-top: 2.75rem;
        padding-top: 2.25rem;
        border-top: 1px solid rgba(148, 163, 184, 0.35);
    }

    .related-blogs-title {
        font-family: 'Space Grotesk', system-ui, sans-serif;
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 1.25rem;
        color: var(--blog-text);
    }

    .related-blogs-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 1.2rem;
    }

    .related-blog-card {
        text-decoration: none;
        color: inherit;
        border-radius: 0.9rem;
        border: 1px solid var(--blog-border);
        background: #ffffff;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: border-color 0.2s, box-shadow 0.2s, transform 0.15s;
    }

    .related-blog-card:hover {
        border-color: var(--blog-accent);
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.7);
        transform: translateY(-1px);
    }

    .related-blog-card-thumb,
    .related-blog-card-thumb-placeholder {
        width: 100%;
        height: 140px;
        object-fit: cover;
        background: radial-gradient(circle at center, rgba(15, 23, 42, 0.06), rgba(15, 23, 42, 0.02));
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--blog-muted);
        font-size: 0.78rem;
    }

    .related-blog-card-body {
        padding: 0.9rem 1rem 1rem;
    }

    .related-blog-card-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--blog-text);
        margin-bottom: 0.35rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .related-blog-card-meta {
        font-size: 0.8rem;
        color: var(--blog-muted);
    }
</style>
@endpush

@section('content')
    @php
        $wordCount = str_word_count(strip_tags($post->renderedContent()));
        $readingMinutes = max(1, (int) ceil($wordCount / 220));
    @endphp

    <div class="blog-shell">
        <div class="blog-breadcrumb">
            <a href="{{ route('blog.index') }}">Blog</a>
            <span>/</span>
            <span>{{ Str::limit($post->title, 48) }}</span>
        </div>

        <section class="blog-hero">
            <div class="blog-hero-main">
                <div class="blog-hero-eyebrow">
                    @if($post->category)
                        <span class="blog-hero-eyebrow-cat"><span>🗂</span><span>{{ $post->category }}</span></span>
                    @endif
                    <span><span>📅</span><span>{{ $post->created_at?->format('d/m/Y') }}</span></span>
                    <span><span>⏱</span><span>{{ $readingMinutes }} min read</span></span>
                </div>
                <h1 class="blog-title">{{ $post->title }}</h1>
                <p class="blog-meta">
                    Published on {{ $post->created_at?->format('F j, Y') }}
                </p>
                <div class="blog-hero-actions">
                    <button type="button" class="blog-hero-share"
                        onclick="navigator.clipboard.writeText(window.location.href); this.querySelector('span:last-child').textContent='Link copied'; setTimeout(() => this.querySelector('span:last-child').textContent='Copy link', 1200);">
                        <span>🔗</span>
                        <span>Copy link</span>
                    </button>
                </div>
            </div>

            <div class="blog-hero-media">
                <div class="blog-hero-media-inner">
                    <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" loading="eager" decoding="async">
                </div>
            </div>
        </section>

        <div class="blog-main-grid">
            <article class="blog-main">
                <a href="{{ route('blog.index') }}" class="blog-back">
                    <span class="icon">←</span>
                    <span>Back to all articles</span>
                </a>

                <div class="blog-content prose">
                    {!! $post->renderedContent() !!}
                </div>

                @if($post->images && count($post->images) > 0)
                    <div class="blog-side-media">
                        @foreach($post->images as $img)
                            <img src="{{ asset('storage/' . $img) }}" alt="" loading="lazy">
                        @endforeach
                    </div>
                @endif

                @if($post->videos && count($post->videos) > 0)
                    <div class="blog-side-media">
                        @foreach($post->videos as $video)
                            <video controls preload="metadata">
                                <source src="{{ asset('storage/' . $video) }}" type="video/mp4">
                            </video>
                        @endforeach
                    </div>
                @endif
            </article>

            @if(isset($relatedBlogs) && $relatedBlogs->isNotEmpty())
            <aside class="blog-aside">
                <h2 class="blog-aside-title">Related articles</h2>
                <div class="blog-aside-related">
                    @foreach($relatedBlogs as $related)
                        <a href="{{ route('blog.show', $related->slug) }}" class="related-blog-card" style="display:block;margin-bottom:0.75rem;">
                            <img src="{{ $related->featured_image_url }}" alt="{{ $related->title }}" class="related-blog-card-thumb" loading="lazy" style="width:100%;aspect-ratio:16/10;object-fit:cover;border-radius:8px;">
                            <div class="related-blog-card-body" style="padding:0.5rem 0.5rem 0;">
                                <h3 class="related-blog-card-title">{{ $related->title }}</h3>
                                <p class="related-blog-card-meta">{{ $related->created_at?->format('d/m/Y') }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </aside>
            @endif
        </div>

    </div>
@endsection
