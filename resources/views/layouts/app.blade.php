<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        $seoTitleSuffix = \App\Support\AdminSettings::get('seo_title_suffix', '- ' . config('app.name'));
        $baseTitle = trim($__env->yieldContent('title', config('app.name')));
        $finalTitle = $baseTitle;
        if ($seoTitleSuffix !== '' && ! str_contains($baseTitle, $seoTitleSuffix)) {
            $finalTitle = trim($baseTitle . ' ' . $seoTitleSuffix);
        }
        $defaultMetaDescription = \App\Support\AdminSettings::get('seo_meta_description_default', 'Latest articles and insights from our blog.');
        $defaultOgImage = \App\Support\AdminSettings::get('seo_og_image_default', '');
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $finalTitle }}</title>
    <meta name="description" content="@yield('description', $defaultMetaDescription)">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-FF4K1DWWT7"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-FF4K1DWWT7');
    </script>

    @yield('head')
    @hasSection('og_image')
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('og_title', $finalTitle)">
    <meta property="og:description" content="@yield('og_description', $defaultMetaDescription)">
    <meta property="og:url" content="@yield('og_url', url()->current())">
    <meta property="og:image" content="@yield('og_image')">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', $finalTitle)">
    <meta name="twitter:description" content="@yield('og_description', $defaultMetaDescription)">
    <meta name="twitter:image" content="@yield('og_image')">
    
    @else
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $finalTitle }}">
    <meta property="og:description" content="@yield('description', $defaultMetaDescription)">
    <meta property="og:url" content="{{ url()->current() }}">
    @if(!empty($defaultOgImage))
    <meta property="og:image" content="{{ $defaultOgImage }}">
    @endif
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta name="twitter:card" content="{{ !empty($defaultOgImage) ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $finalTitle }}">
    <meta name="twitter:description" content="@yield('description', $defaultMetaDescription)">
    @if(!empty($defaultOgImage))
    <meta name="twitter:image" content="{{ $defaultOgImage }}">
    @endif
    @endif
    @php
        $organizationSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('app.name'),
            'url' => config('app.url'),
            'description' => $defaultMetaDescription,
        ];
    @endphp
    <script type="application/ld+json">{{ json_encode($organizationSchema) }}</script>
    @stack('head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #ffffff;
            --surface: #f9fafb;
            --surface-hover: #f3f4f6;
            --text: #111827;
            --text-dark: #111827;
            --text-muted: #6b7280;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --border: #e5e7eb;
            --radius: 12px;
            --radius-sm: 8px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DM Sans', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .font-heading { font-family: 'Space Grotesk', sans-serif; }

        .logo {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 1.35rem;
            color: var(--text);
            text-decoration: none;
            letter-spacing: -0.02em;
        }
        .logo span { color: var(--accent); }

        /* Main */
        main { flex: 1; }

        /* Pagination */
        .pagination-nav { margin-top: 2rem; }
        .pagination-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 0.4rem;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .pagination-list li { display: inline-flex; }
        .pagination-item {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.25rem;
            padding: 0.5rem 0.75rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            text-decoration: none;
            font-size: 0.9rem;
            transition: border-color 0.2s, color 0.2s;
        }
        .pagination-item:hover:not(.pagination-disabled):not(.pagination-current) {
            border-color: var(--accent);
            color: var(--accent);
        }
        .pagination-disabled, .pagination-current {
            color: var(--text-muted);
            cursor: default;
            pointer-events: none;
        }
        .pagination-current {
            background: var(--surface-hover);
            border-color: var(--accent);
            color: var(--accent);
            pointer-events: none;
        }
        .pagination-ellipsis {
            border: none;
            background: transparent;
        }
        .pagination-info {
            margin-top: 1rem;
            text-align: center;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        /* Cookie consent bar */
        .cookie-consent {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--text);
            color: #fff;
            padding: 1rem 1.5rem;
            z-index: 999;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.15);
        }
        .cookie-consent[hidden] { display: none; }
        .cookie-consent-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .cookie-consent p { margin: 0; font-size: 0.9rem; flex: 1; min-width: 200px; }
        .cookie-consent a { color: #93c5fd; text-decoration: underline; }
        .cookie-consent-btn {
            padding: 0.5rem 1.25rem;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }
        .cookie-consent-btn:hover { background: var(--accent-hover); }
    </style>
    @include('partials.site-chrome-styles')
    @stack('styles')
</head>
<body>
    @include('partials.site-header')

    <main>
        @yield('content')
    </main>

    @include('partials.site-footer')

    <div id="cookie-consent" class="cookie-consent" role="dialog" aria-label="Cookie notice" hidden>
        <div class="cookie-consent-inner">
            <p>We use cookies to improve your experience and analyze site traffic. See our <a href="{{ url('/cookie-policy') }}">Cookie Policy</a> and <a href="{{ url('/privacy') }}">Privacy Policy</a>. By continuing you agree to their use.</p>
            <button type="button" class="cookie-consent-btn" data-dismiss>OK</button>
        </div>
    </div>
    <script>
        (function() {
            if (localStorage.getItem('cookie-consent-accepted')) return;
            var bar = document.getElementById('cookie-consent');
            if (!bar) return;
            bar.removeAttribute('hidden');
            bar.querySelector('[data-dismiss]')?.addEventListener('click', function() {
                localStorage.setItem('cookie-consent-accepted', '1');
                bar.setAttribute('hidden', '');
            });
        })();
    </script>

    @stack('scripts')

    <button id="back-to-top" class="back-to-top" aria-label="Back to top" hidden>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="18 15 12 9 6 15"></polyline>
        </svg>
    </button>
    <style>
        .back-to-top {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            color: #fff;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.4);
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px) scale(0.8);
            transition: opacity 0.3s, visibility 0.3s, transform 0.3s;
            z-index: 999;
        }
        .back-to-top:not([hidden]) {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }
        .back-to-top:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
            transform: scale(1.1);
        }
        html {
            scroll-behavior: smooth;
            scroll-padding-top: 80px;
        }
    </style>
    <script>
        (function() {
            var btn = document.getElementById('back-to-top');
            if (!btn) return;
            function toggle() {
                btn.hidden = window.scrollY <= 400;
            }
            window.addEventListener('scroll', toggle, { passive: true });
            toggle();
            btn.addEventListener('click', function() {
                var start = window.scrollY;
                var duration = 600;
                var startTime = performance.now();
                function easeOutCubic(t) { return 1 - Math.pow(1 - t, 3); }
                function animate(currentTime) {
                    var elapsed = currentTime - startTime;
                    var progress = Math.min(elapsed / duration, 1);
                    window.scrollTo(0, start * (1 - easeOutCubic(progress)));
                    if (progress < 1) requestAnimationFrame(animate);
                }
                requestAnimationFrame(animate);
            });
        })();
    </script>
</body>
</html>
