@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\BlogDeal> $couponDeals */
    $couponDeals = $couponDeals ?? collect();
@endphp

@if($couponDeals->isNotEmpty())
<section class="blog-coupon-codes" aria-label="Coupon codes">
    <div class="blog-coupon-codes__head">
        <span class="blog-coupon-codes__icon" aria-hidden="true">🎟️</span>
        <h2 class="blog-coupon-codes__title">Coupon Codes</h2>
        <p class="blog-coupon-codes__hint">Click the code to copy it — the shop link will open in a new tab after one second.</p>
    </div>
    <ul class="blog-coupon-codes__list">
        @foreach($couponDeals as $deal)
        <li>
            <button
                type="button"
                class="blog-coupon-codes__chip"
                data-copy-code="{{ $deal->coupon_code }}"
                data-shop-url="{{ $deal->shop_url }}"
                title="Copy {{ $deal->coupon_code }}"
            >
                <span class="blog-coupon-codes__chip-value">{{ $deal->coupon_code }}</span>
                <span class="blog-coupon-codes__chip-desc">{{ $deal->couponBlurb() }}</span>
                <span class="blog-coupon-codes__chip-action">Copy</span>
            </button>
        </li>
        @endforeach
    </ul>
</section>

@once
    @push('scripts')
    <script>
    (function () {
        function copyText(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                return navigator.clipboard.writeText(text);
            }
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
            } catch (e) {}
            document.body.removeChild(ta);
            return Promise.resolve();
        }

        document.querySelectorAll('.blog-coupon-codes__chip[data-copy-code]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var code = btn.getAttribute('data-copy-code') || '';
                var shopUrl = btn.getAttribute('data-shop-url') || '';
                if (!code) {
                    return;
                }

                copyText(code).then(function () {
                    btn.classList.add('is-copied');
                    var valueEl = btn.querySelector('.blog-coupon-codes__chip-value');
                    var actionEl = btn.querySelector('.blog-coupon-codes__chip-action');
                    var originalValue = valueEl ? valueEl.textContent : '';
                    var originalAction = actionEl ? actionEl.textContent : '';

                    if (valueEl) {
                        valueEl.textContent = 'Đã copy!';
                    }
                    if (actionEl) {
                        actionEl.textContent = 'Đã copy';
                    }

                    setTimeout(function () {
                        if (shopUrl) {
                            window.open(shopUrl, '_blank', 'noopener,noreferrer');
                        }
                    }, 1000);

                    setTimeout(function () {
                        btn.classList.remove('is-copied');
                        if (valueEl) {
                            valueEl.textContent = originalValue;
                        }
                        if (actionEl) {
                            actionEl.textContent = originalAction;
                        }
                    }, 2200);
                });
            });
        });
    })();
    </script>
    @endpush
@endonce
@endif
