@php
    $popularCategories = $featuredCategories->sortByDesc('count')->values();
@endphp
<aside class="bh-cat-sidebar">
    <div class="bh-cat-sidebar__inner">
        @if($popularCategories->isNotEmpty())
        <div class="bh-cat-sidebar__widget">
            <h3 class="bh-cat-sidebar__title">Categories</h3>
            <ul class="bh-cat-sidebar__cats">
                @foreach($popularCategories->take(8) as $cat)
                <li>
                    <a href="{{ $cat['url'] }}"
                       data-cat-anchor="{{ $cat['slug'] }}"
                       class="{{ request('cat') === $cat['name'] ? 'active' : '' }}">
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
            <h3 class="bh-cat-sidebar__title">Trending Posts</h3>
            <ol class="bh-cat-sidebar__trending">
                @foreach($trendingPosts->take(5) as $post)
                <li>
                    <a href="{{ route('blog.show', $post->slug) }}">
                        @if($post->featured_image_url)
                        <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" loading="lazy">
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
