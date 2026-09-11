@push('styles')
<style>
    .blog-gated-shell.is-locked .blog-gated-content {
        max-height: 14rem;
        overflow: hidden;
        pointer-events: none;
        user-select: none;
        -webkit-mask-image: linear-gradient(180deg, #000 40%, transparent 100%);
        mask-image: linear-gradient(180deg, #000 40%, transparent 100%);
    }
    .blog-gated-shell.is-locked.blog-gated-shell--hidden-until-unlock {
        display: none;
    }
    .blog-gate-modal {
        position: fixed;
        inset: 0;
        z-index: 1200;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.25rem;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(4px);
    }
    .blog-gate-modal[hidden] {
        display: none !important;
    }
    body.blog-gate-open {
        overflow: hidden;
    }
    .blog-gate-modal__panel {
        width: min(100%, 420px);
        background: #fff;
        border-radius: 1rem;
        padding: 1.75rem 1.5rem 1.5rem;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
        border: 1px solid rgba(15, 23, 42, 0.08);
    }
    .blog-gate-modal__title {
        font-family: 'Poppins', sans-serif;
        font-size: 1.25rem;
        font-weight: 650;
        color: #0f172a;
        margin: 0 0 0.5rem;
    }
    .blog-gate-modal__text {
        font-size: 0.9rem;
        color: #64748b;
        line-height: 1.55;
        margin: 0 0 1.25rem;
    }
    .blog-gate-modal__form {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    .blog-gate-modal__label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #334155;
    }
    .blog-gate-modal__input {
        width: 100%;
        border: 1px solid rgba(15, 23, 42, 0.15);
        border-radius: 0.65rem;
        padding: 0.65rem 0.85rem;
        font-size: 1rem;
        outline: none;
    }
    .blog-gate-modal__input:focus {
        border-color: #FF8F00;
        box-shadow: 0 0 0 3px rgba(255, 143, 0, 0.2);
    }
    .blog-gate-modal__submit {
        border: none;
        border-radius: 0.65rem;
        padding: 0.75rem 1rem;
        font-weight: 650;
        font-size: 0.95rem;
        color: #fff;
        cursor: pointer;
        background: linear-gradient(135deg, #F9A825 0%, #FF8F00 100%);
    }
    .blog-gate-modal__submit:disabled {
        opacity: 0.65;
        cursor: wait;
    }
    .blog-gate-modal__error {
        font-size: 0.82rem;
        color: #b91c1c;
        margin: 0;
    }
    .blog-gate-modal__fine {
        font-size: 0.72rem;
        color: #94a3b8;
        margin: 0.85rem 0 0;
        line-height: 1.45;
    }
    .blog-gate-modal__fine a {
        color: #64748b;
        text-decoration: underline;
    }
</style>
@endpush

<div class="blog-gate-modal" id="blog-email-gate-modal" role="dialog" aria-modal="true" aria-labelledby="blog-email-gate-title" hidden>
    <div class="blog-gate-modal__panel">
        <h2 class="blog-gate-modal__title" id="blog-email-gate-title">Continue reading</h2>
        <p class="blog-gate-modal__text">Enter your email to unlock the full article. We only use it to share updates related to our blog.</p>
        <form class="blog-gate-modal__form" id="blog-email-gate-form" novalidate>
            <label class="blog-gate-modal__label" for="blog-email-gate-input">Email address</label>
            <input type="email" class="blog-gate-modal__input" id="blog-email-gate-input" name="email" autocomplete="email" required placeholder="you@example.com">
            <p class="blog-gate-modal__error" id="blog-email-gate-error" hidden></p>
            <button type="submit" class="blog-gate-modal__submit" id="blog-email-gate-submit">View article</button>
        </form>
        <p class="blog-gate-modal__fine">By continuing you agree to our <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.</p>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var storageKey = 'blog_email_unlock_v1';
    var postId = {{ (int) $post->id }};
    var unlockUrl = @json(route('blogs.email-unlock', $post->slug));
    var shells = document.querySelectorAll('.blog-gated-shell');
    var modal = document.getElementById('blog-email-gate-modal');
    var form = document.getElementById('blog-email-gate-form');
    var input = document.getElementById('blog-email-gate-input');
    var errorEl = document.getElementById('blog-email-gate-error');
    var submitBtn = document.getElementById('blog-email-gate-submit');

    if (!shells.length || !modal || !form) {
        return;
    }

    function readUnlocks() {
        try {
            return JSON.parse(localStorage.getItem(storageKey) || '{}');
        } catch (e) {
            return {};
        }
    }

    function isUnlocked() {
        var map = readUnlocks();

        return !!map[String(postId)];
    }

    function persistUnlock() {
        var map = readUnlocks();
        map[String(postId)] = Date.now();
        localStorage.setItem(storageKey, JSON.stringify(map));
    }

    function unlockUi() {
        shells.forEach(function (shell) {
            shell.classList.remove('is-locked');
        });
        modal.setAttribute('hidden', 'hidden');
        document.body.classList.remove('blog-gate-open');
    }

    function lockUi() {
        shells.forEach(function (shell) {
            shell.classList.add('is-locked');
        });
        modal.removeAttribute('hidden');
        document.body.classList.add('blog-gate-open');
        window.requestAnimationFrame(function () {
            if (input) {
                input.focus();
            }
        });
    }

    function showError(message) {
        if (!errorEl) {
            return;
        }
        errorEl.textContent = message;
        errorEl.hidden = !message;
    }

    if (isUnlocked()) {
        unlockUi();
    } else {
        lockUi();
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        showError('');

        var email = (input && input.value ? input.value : '').trim();
        if (!email) {
            showError('Please enter your email address.');

            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
        }

        var token = document.querySelector('meta[name="csrf-token"]');
        fetch(unlockUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
            },
            body: JSON.stringify({ email: email }),
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data };
                }).catch(function () {
                    return { ok: res.ok, data: {} };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    var msg = (result.data && result.data.message) ? result.data.message : 'Could not verify email. Please try again.';
                    if (result.data && result.data.errors && result.data.errors.email) {
                        msg = result.data.errors.email[0];
                    }
                    showError(msg);

                    return;
                }

                persistUnlock();
                unlockUi();
            })
            .catch(function () {
                showError('Network error. Please try again.');
            })
            .finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            });
    });
})();
</script>
@endpush
