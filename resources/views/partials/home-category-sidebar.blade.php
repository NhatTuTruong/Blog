@php
    $sidebarCategories = isset($categoryPosts) && $categoryPosts->isNotEmpty()
        ? $categoryPosts
        : $featuredCategories->take(8);
    $selectedCategory = $selectedCategory ?? request('cat', '');
@endphp
<aside class="bh-cat-sidebar">
    <div class="bh-cat-sidebar__inner">
        @if($sidebarCategories->isNotEmpty())
        <div class="bh-cat-sidebar__widget">
            <h3 class="bh-cat-sidebar__title">Categories</h3>
            <ul class="bh-cat-sidebar__cats">
                @foreach($sidebarCategories as $cat)
                <li>
                    <a href="{{ $cat['url'] }}"
                       data-cat-anchor="{{ $cat['slug'] }}"
                       class="{{ filled($selectedCategory) && $selectedCategory === $cat['name'] ? 'active' : '' }}">
                        <span class="bh-cat-sidebar__cat-dot" style="background-color: {{ $cat['color'] ?? '#2563eb' }}"></span>
                        <span class="bh-cat-sidebar__cat-name">{{ $cat['name'] }}</span>
                        <span class="bh-cat-sidebar__cat-count">{{ $cat['count'] }}</span>
                    </a>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        @if(isset($trendingPosts) && $trendingPosts->isNotEmpty())
        <div class="bh-cat-sidebar__widget">
            <h3 class="bh-cat-sidebar__title">Trending News</h3>
            <ol class="bh-cat-sidebar__trending">
                @foreach($trendingPosts->take(5) as $post)
                <li>
                    <a href="{{ $post->publicUrl() }}">
                        @if($post->featured_image_url)
                        <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" loading="lazy" decoding="async">
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

        @if(isset($latestPosts) && $latestPosts->isNotEmpty())
        <div class="bh-cat-sidebar__widget">
            <h3 class="bh-cat-sidebar__title">Latest</h3>
            <ul class="bh-cat-sidebar__latest">
                @foreach($latestPosts->take(5) as $post)
                <li>
                    <a href="{{ $post->publicUrl() }}">{{ $post->title }}</a>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</aside>
