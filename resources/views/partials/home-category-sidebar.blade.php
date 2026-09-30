@php
    $sidebarCategories = isset($categoryPosts) && $categoryPosts->isNotEmpty()
        ? $categoryPosts
        : $featuredCategories->sortByDesc('count')->values();
    $selectedCategory = $selectedCategory ?? request('cat', '');
@endphp
<aside class="bh-cat-sidebar">
    <div class="bh-cat-sidebar__inner bh-cat-sidebar__inner--editorial">
        <header class="bh-cat-sidebar__head">
            <span class="bh-cat-sidebar__head-badge" aria-hidden="true">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h10M4 18h14"/></svg>
            </span>
            <div class="bh-cat-sidebar__head-text">
                <p class="bh-cat-sidebar__head-kicker">Explore</p>
                <p class="bh-cat-sidebar__head-title">Topics & trending</p>
            </div>
        </header>

        @if($sidebarCategories->isNotEmpty())
        <div class="bh-cat-sidebar__widget">
            <h3 class="bh-cat-sidebar__title">Categories</h3>
            <ul class="bh-cat-sidebar__cats">
                @foreach($sidebarCategories->take(8) as $cat)
                <li>
                    <a href="{{ $cat['url'] }}"
                       data-cat-anchor="{{ $cat['slug'] }}"
                       class="{{ filled($selectedCategory) && $selectedCategory === $cat['name'] ? 'active' : '' }}">
                        <span class="bh-cat-sidebar__cat-dot" style="--cat-dot: {{ $cat['color'] ?? '#2563eb' }}"></span>
                        <span class="bh-cat-sidebar__cat-name">{{ $cat['name'] }}</span>
                        <span class="bh-cat-sidebar__cat-count">{{ $cat['count'] }}</span>
                    </a>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        @if(isset($trendingPosts) && $trendingPosts->isNotEmpty())
        <div class="bh-cat-sidebar__widget bh-cat-sidebar__widget--trending">
            <h3 class="bh-cat-sidebar__title">Trending now</h3>
            <ol class="bh-cat-sidebar__trending">
                @foreach($trendingPosts->take(5) as $index => $post)
                <li>
                    <a href="{{ route('blog.show', $post->slug) }}">
                        <span class="bh-cat-sidebar__trend-rank">{{ $index + 1 }}</span>
                        @if($post->featured_image_url)
                        <img src="{{ $post->featured_image_url }}" alt="" loading="lazy" decoding="async">
                        @endif
                        <div class="bh-cat-sidebar__trend-body">
                            <span class="bh-cat-sidebar__trend-title">{{ $post->title }}</span>
                            <span class="bh-cat-sidebar__trend-meta">
                                {{ $post->created_at?->format('M j, Y') }}
                                @if($post->views_count > 0)
                                    · {{ number_format($post->views_count) }} views
                                @endif
                            </span>
                        </div>
                    </a>
                </li>
                @endforeach
            </ol>
        </div>
        @endif
    </div>
</aside>
