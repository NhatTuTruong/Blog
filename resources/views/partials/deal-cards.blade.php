@php
    $deals = $deals ?? collect();
    $filter = $filter ?? null;
    $sectionTitle = $sectionTitle ?? 'Coupons & Discount Deals';
    $showFilters = $showFilters ?? false;
    $sectionId = $sectionId ?? null;
    $linkToPost = $linkToPost ?? true;
@endphp

@if($deals->isNotEmpty())
<section class="deal-section" @if($sectionId) id="{{ $sectionId }}" @endif aria-label="{{ $sectionTitle }}">
    <div class="deal-section__head">
        @if($showFilters)
        <div class="deal-filters">
            <a href="{{ route('deals.index') }}" class="deal-filter {{ ($filter ?? 'all') === 'all' ? 'is-active' : '' }}">All</a>
            <a href="{{ route('deals.index', ['type' => 'coupon']) }}" class="deal-filter {{ ($filter ?? '') === 'coupon' ? 'is-active' : '' }}">Coupon Codes</a>
            <a href="{{ route('deals.index', ['type' => 'discount']) }}" class="deal-filter {{ ($filter ?? '') === 'discount' ? 'is-active' : '' }}">Discount Deals</a>
        </div>
        @endif
    </div>

    <div class="deal-grid">
        @foreach($deals as $deal)
        @php $postUrl = $linkToPost ? $deal->postUrl() : null; @endphp
        <article class="deal-card {{ $postUrl ? 'deal-card--clickable' : '' }}">
            @if($postUrl)
            <a href="{{ $postUrl }}" class="deal-card__stretched-link" aria-label="View article: {{ $deal->title }}"></a>
            @endif
            <div class="deal-card__top">
                <div class="deal-card__media">
                    @if($deal->brandImageUrl())
                        <img src="{{ $deal->brandImageUrl() }}" alt="" loading="lazy" decoding="async">
                    @else
                        <span class="deal-card__media-fallback">{{ $deal->brandInitial() }}</span>
                    @endif
                </div>
                <div class="deal-card__meta">
                    <span class="deal-card__badge">Deal</span>
                    <span class="deal-card__type">{{ $deal->typeLabel() }}</span>
                </div>
            </div>

            <div class="deal-card__body">
                <h3 class="deal-card__title">{{ $deal->title }}</h3>
                @if(filled($deal->description))
                    <p class="deal-card__desc">{{ $deal->description }}</p>
                @endif

                <div class="deal-card__footer">
                    @if($deal->isCouponDeal())
                        <button type="button" class="deal-card__code" data-copy-code="{{ $deal->coupon_code }}" title="Click to copy code" onclick="event.stopPropagation();">
                            <span class="deal-card__code-label">Code</span>
                            <span class="deal-card__code-value">{{ $deal->coupon_code }}</span>
                        </button>
                    @endif
                    <a href="{{ $deal->shop_url }}" class="deal-card__shop" target="_blank" rel="noopener noreferrer sponsored" onclick="event.stopPropagation();">Shop Now</a>
                </div>
            </div>
        </article>
        @endforeach
    </div>
</section>
@endif

@once
    @push('scripts')
    <script>
    (function () {
        document.querySelectorAll('[data-copy-code]').forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                var code = btn.getAttribute('data-copy-code') || '';
                if (!code) return;
                navigator.clipboard.writeText(code).then(function () {
                    btn.classList.add('is-copied');
                    var valueEl = btn.querySelector('.deal-card__code-value');
                    if (valueEl) {
                        var original = valueEl.textContent;
                        valueEl.textContent = 'Copied!';
                        setTimeout(function () {
                            valueEl.textContent = original;
                            btn.classList.remove('is-copied');
                        }, 1500);
                    }
                }).catch(function () {});
            });
        });
    })();
    </script>
    @endpush
@endonce
