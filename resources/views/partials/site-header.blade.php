@php
    $navLinks = \App\Models\SiteContent::get('header_nav', \App\Models\SiteContent::defaultHeaderNav());
    $socialLinks = \App\Models\SiteContent::visibleSocialLinks();
    $blogCategories = \App\Models\BlogCategory::query()->active()->orderBy('sort_order')->orderBy('name')->limit(8)->get();
    $siteName = (string) config('app.name');
    $logoFirst = mb_substr($siteName, 0, 1);
    $logoRest = mb_substr($siteName, 1);
    $normalizeUrl = function ($url) {
        if (empty($url)) {
            return url('/');
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url(ltrim($url, '/'));
    };
    $navPath = function (?string $url): string {
        if ($url === null || trim($url) === '') {
            return '';
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return trim($url, '/');
        }

        return trim($path, '/');
    };
    $isActiveNav = function (array $link) use ($navPath): bool {
        $path = $navPath($link['url'] ?? '/');
        $currentPath = trim(request()->path(), '/');

        if ($path === '') {
            return $currentPath === '' && ! request()->filled('q') && ! request()->filled('cat');
        }

        return $currentPath === $path || str_starts_with($currentPath, $path.'/');
    };
    $isReviewNav = function (array $link) use ($navPath): bool {
        $path = $navPath($link['url'] ?? null);

        return $path === 'review' || str_starts_with($path, 'review/');
    };
@endphp
<header class="site-header">
    <div class="site-topbar">
        <div class="site-topbar__inner">
            @if($socialLinks->isNotEmpty())
            <div class="site-topbar__social">
                @foreach($socialLinks as $social)
                <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" class="social-icon social-icon--{{ $social['platform'] }}" aria-label="{{ ucfirst($social['platform']) }}">
                    {!! \App\Models\SiteContent::socialPlatformIcon($social['platform']) !!}
                </a>
                @endforeach
            </div>
            @else
            <span class="site-topbar__tagline">Reviews · Guides · Deals</span>
            @endif
            <div class="site-topbar__links">
                <a href="{{ url('/about') }}">About Us</a>
                <a href="{{ url('/privacy') }}">Privacy Policy</a>
                <a href="{{ url('/terms') }}">Terms</a>
            </div>
        </div>
    </div>

    <div class="site-navbar">
        <div class="site-navbar__inner">
            <a href="{{ url('/') }}" class="site-logo font-heading" aria-label="{{ $siteName }} home">
                <span class="site-logo__mark">{{ $logoFirst }}</span><span class="site-logo__text">{{ $logoRest }}</span>
            </a>

            <div class="site-navbar__actions">
                <button type="button"
                    class="site-header__search-toggle"
                    id="site-header-search-toggle"
                    aria-expanded="false"
                    aria-controls="site-header-search-panel"
                    aria-label="Open search">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </button>
                <button type="button"
                    class="site-header__toggle"
                    id="site-header-toggle"
                    aria-expanded="false"
                    aria-controls="site-nav"
                    aria-label="Open navigation menu">
                    <span class="site-header__toggle-bar" aria-hidden="true"></span>
                    <span class="site-header__toggle-bar" aria-hidden="true"></span>
                    <span class="site-header__toggle-bar" aria-hidden="true"></span>
                </button>
            </div>

            <nav class="site-nav" id="site-nav" aria-label="Main navigation">
                @foreach ($navLinks as $link)
                    @if($isReviewNav($link) && $blogCategories->isNotEmpty())
                    <div class="site-nav__dropdown">
                        <a href="{{ $normalizeUrl($link['url'] ?? '/review') }}" class="site-nav__link site-nav__link--has-menu {{ $isActiveNav($link) ? 'is-active' : '' }}">
                            {{ strtoupper($link['label'] ?? 'Review') }}
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                        </a>
                        <div class="site-nav__menu">
                            <a href="{{ route('review.index') }}">All Reviews</a>
                            @foreach($blogCategories as $cat)
                            <a href="{{ route('home', ['cat' => $cat->name]) }}">{{ $cat->name }}</a>
                            @endforeach
                        </div>
                    </div>
                    @else
                    <a href="{{ $normalizeUrl($link['url'] ?? '/') }}" class="site-nav__link {{ $isActiveNav($link) ? 'is-active' : '' }}">{{ strtoupper($link['label'] ?? 'Link') }}</a>
                    @endif
                @endforeach
            </nav>

            <form class="site-search" id="site-header-search-panel" action="{{ route('home') }}" method="get" role="search">
                <label for="site-header-search" class="sr-only">Search articles</label>
                <input id="site-header-search" type="search" name="q" value="{{ request('q') }}" placeholder="Search…" autocomplete="off">
                <button type="submit" aria-label="Search">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </button>
            </form>
        </div>
    </div>

    @if(($headerTickerPosts ?? collect())->isNotEmpty())
    <div class="site-ticker" aria-label="Trending articles">
        <div class="site-ticker__inner">
            <span class="site-ticker__label">Trending</span>
            <div class="site-ticker__viewport">
                <div class="site-ticker__track">
                    @foreach([1, 2] as $loopPass)
                        @foreach($headerTickerPosts as $post)
                        <a href="{{ $post->publicUrl() }}" class="site-ticker__item">{{ $post->title }}</a>
                        <span class="site-ticker__sep" aria-hidden="true">•</span>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif
</header>
<script>
(function () {
    var header = document.querySelector('.site-header');
    var toggle = document.getElementById('site-header-toggle');
    var searchToggle = document.getElementById('site-header-search-toggle');
    var searchForm = document.getElementById('site-header-search-panel');
    var searchInput = document.getElementById('site-header-search');
    var nav = document.getElementById('site-nav');
    if (!header || !toggle || !nav) return;

    var mq = window.matchMedia('(min-width: 992px)');

    function closeSearch() {
        header.classList.remove('site-header--search-open');
        if (searchToggle) {
            searchToggle.setAttribute('aria-expanded', 'false');
            searchToggle.setAttribute('aria-label', 'Open search');
        }
    }

    function openSearch() {
        closeMenu();
        header.classList.add('site-header--search-open');
        if (searchToggle) {
            searchToggle.setAttribute('aria-expanded', 'true');
            searchToggle.setAttribute('aria-label', 'Close search');
        }
        if (searchInput) {
            window.requestAnimationFrame(function () { searchInput.focus(); });
        }
    }

    function closeMenu() {
        header.classList.remove('site-header--nav-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open navigation menu');
    }

    function openMenu() {
        closeSearch();
        header.classList.add('site-header--nav-open');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Close navigation menu');
    }

    if (searchToggle && searchForm) {
        searchToggle.addEventListener('click', function () {
            if (header.classList.contains('site-header--search-open')) closeSearch();
            else openSearch();
        });
    }

    toggle.addEventListener('click', function () {
        if (header.classList.contains('site-header--nav-open')) closeMenu();
        else openMenu();
    });

    nav.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', closeMenu);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeMenu();
            closeSearch();
        }
    });

    document.addEventListener('click', function (e) {
        if (header.classList.contains('site-header--nav-open') && !header.contains(e.target)) {
            closeMenu();
        }
        if (header.classList.contains('site-header--search-open') && !header.contains(e.target)) {
            closeSearch();
        }
    });

    function onMqChange() {
        if (mq.matches) {
            closeMenu();
            closeSearch();
        }
    }

    if (mq.addEventListener) mq.addEventListener('change', onMqChange);
    else mq.addListener(onMqChange);
})();
</script>
