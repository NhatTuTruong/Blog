@php
    $navLinks = \App\Models\SiteContent::get('header_nav', \App\Models\SiteContent::defaultHeaderNav());
    $normalizeUrl = function ($url) {
        if (empty($url)) {
            return url('/');
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url(ltrim($url, '/'));
    };
@endphp
<header class="site-header">
    <div class="header-inner">
        <a href="{{ url('/') }}" class="logo font-heading">{{ config('app.name') }}<span>.</span></a>
        <div class="site-header__actions">
            <button type="button"
                class="site-header__toggle"
                id="site-header-toggle"
                aria-expanded="false"
                aria-controls="site-nav"
                aria-label="Mở menu điều hướng">
                <span class="site-header__toggle-bar" aria-hidden="true"></span>
                <span class="site-header__toggle-bar" aria-hidden="true"></span>
                <span class="site-header__toggle-bar" aria-hidden="true"></span>
            </button>
        </div>
        <nav class="nav-links" id="site-nav" aria-label="Điều hướng chính">
            @foreach ($navLinks as $link)
                <a href="{{ $normalizeUrl($link['url'] ?? '/') }}">{{ $link['label'] ?? 'Link' }}</a>
            @endforeach
        </nav>
    </div>
</header>
<script>
(function () {
    var header = document.querySelector('.site-header');
    var toggle = document.getElementById('site-header-toggle');
    var nav = document.getElementById('site-nav');
    if (!header || !toggle || !nav) return;

    var mq = window.matchMedia('(min-width: 769px)');

    function closeMenu() {
        header.classList.remove('site-header--nav-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Mở menu điều hướng');
    }

    function openMenu() {
        header.classList.add('site-header--nav-open');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Đóng menu điều hướng');
    }

    toggle.addEventListener('click', function () {
        if (header.classList.contains('site-header--nav-open')) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    nav.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', closeMenu);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeMenu();
    });

    document.addEventListener('click', function (e) {
        if (!header.classList.contains('site-header--nav-open')) return;
        if (!header.contains(e.target)) closeMenu();
    });

    function onMqChange() {
        if (mq.matches) closeMenu();
    }

    if (mq.addEventListener) {
        mq.addEventListener('change', onMqChange);
    } else {
        mq.addListener(onMqChange);
    }
})();
</script>
