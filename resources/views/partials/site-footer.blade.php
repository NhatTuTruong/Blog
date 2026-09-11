@php
    $brandDescription = \App\Models\SiteContent::get('footer_brand_description', 'Articles, guides and stories — updated regularly.');
    $columns = \App\Models\SiteContent::get('footer_columns', \App\Models\SiteContent::defaultFooterColumns());
    $copyright = \App\Models\SiteContent::get('footer_copyright', '© ' . date('Y') . ' ' . config('app.name') . '. All rights reserved.');
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
    $featuredPosts = $footerFeaturedPosts ?? collect();
    $galleryPosts = $footerGalleryPosts ?? collect();
    $quickLinks = collect($columns)->first()['links'] ?? [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'Review', 'url' => '/review'],
        ['label' => 'Blog', 'url' => '/blogs'],
        ['label' => 'Categories', 'url' => '/categories'],
        ['label' => 'Deals', 'url' => '/deals'],
        ['label' => 'About', 'url' => '/about'],
        ['label' => 'Contact', 'url' => '/contact'],
    ];
@endphp
<footer class="site-footer">
    <div class="site-footer__accent" aria-hidden="true"></div>
    <div class="footer-inner">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="{{ url('/') }}" class="site-logo site-logo--footer font-heading" aria-label="{{ $siteName }} home">
                    <span class="site-logo__mark">{{ $logoFirst }}</span><span class="site-logo__text">{{ $logoRest }}</span>
                </a>
                <p>{{ $brandDescription }}</p>
                @php $footerSocialLinks = \App\Models\SiteContent::visibleSocialLinks(); @endphp
                @if($footerSocialLinks->isNotEmpty())
                    <div class="footer-brand__social">
                        @foreach($footerSocialLinks as $social)
                        <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" class="social-icon social-icon--{{ $social['platform'] }}" aria-label="{{ ucfirst($social['platform']) }}">
                            {!! \App\Models\SiteContent::socialPlatformIcon($social['platform']) !!}
                        </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    @foreach($quickLinks as $link)
                        <li><a href="{{ $normalizeUrl($link['url'] ?? '/') }}">{{ $link['label'] ?? 'Link' }}</a></li>
                    @endforeach
                </ul>
            </div>

            @if($galleryPosts->isNotEmpty())
            <div class="footer-col footer-gallery">
                <h4>Gallery</h4>
                <div class="footer-gallery__grid">
                    @foreach($galleryPosts as $post)
                    <a href="{{ $post->publicUrl() }}" class="footer-gallery__item" title="{{ $post->title }}">
                        <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" loading="lazy" decoding="async">
                    </a>
                    @endforeach
                </div>
            </div>
            @else
            @foreach(collect($columns)->skip(1)->take(1) as $col)
            <div class="footer-col">
                <h4>{{ $col['title'] ?? 'Links' }}</h4>
                <ul>
                    @foreach($col['links'] ?? [] as $link)
                        <li><a href="{{ $normalizeUrl($link['url'] ?? '/') }}">{{ $link['label'] ?? 'Link' }}</a></li>
                    @endforeach
                </ul>
            </div>
            @endforeach
            @endif

            @if($featuredPosts->count() > 0)
            <div class="footer-stories">
                <h4>Recent Posts</h4>
                @foreach($featuredPosts as $post)
                <a href="{{ $post->publicUrl() }}" class="footer-story-item">
                    <img src="{{ $post->featuredImageUrl }}"
                         alt="{{ $post->title }}"
                         class="footer-story-thumb"
                         loading="lazy"
                         decoding="async">
                    <div class="footer-story-info">
                        @if($post->category)
                        <span class="footer-story-cat">{{ $post->category }}</span>
                        @endif
                        <span class="footer-story-title">{{ $post->title }}</span>
                    </div>
                </a>
                @endforeach
            </div>
            @endif
        </div>

        <div class="footer-bottom">
            <p>{!! nl2br(e($copyright)) !!}</p>
            <p class="footer-legal-links">
                <a href="{{ url('/privacy') }}">Privacy</a>
                <span aria-hidden="true">·</span>
                <a href="{{ url('/terms') }}">Terms</a>
                <span aria-hidden="true">·</span>
                <a href="{{ url('/cookie-policy') }}">Cookies</a>
            </p>
        </div>
    </div>
</footer>
