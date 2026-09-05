@if(filled($gaId = env('GOOGLE_ANALYTICS_ID', 'G-FF4K1DWWT7')))
<script>
(function () {
    var gaId = @json($gaId);
    var loaded = false;

    function loadAnalytics() {
        if (loaded) {
            return;
        }
        loaded = true;

        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () {
            window.dataLayer.push(arguments);
        };
        window.gtag('js', new Date());
        window.gtag('config', gaId, { send_page_view: true });

        var script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(gaId);
        document.head.appendChild(script);
    }

    if (localStorage.getItem('cookie-consent-accepted')) {
        if ('requestIdleCallback' in window) {
            requestIdleCallback(loadAnalytics, { timeout: 3000 });
        } else {
            window.addEventListener('load', function () {
                setTimeout(loadAnalytics, 1500);
            }, { once: true });
        }
    } else {
        document.addEventListener('cookie-consent-accepted', loadAnalytics, { once: true });
    }
})();
</script>
@endif
