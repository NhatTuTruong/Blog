@if(app()->environment('production'))
    <script>
        (function () {
            if (window.__adminErrorToastInstalled) return;
            window.__adminErrorToastInstalled = true;

            function ensureToast() {
                var el = document.getElementById('admin-error-toast');
                if (el) return el;

                el = document.createElement('div');
                el.id = 'admin-error-toast';
                el.style.position = 'fixed';
                el.style.right = '16px';
                el.style.top = '16px';
                el.style.zIndex = '99999';
                el.style.maxWidth = '420px';
                el.style.padding = '12px 14px';
                el.style.borderRadius = '12px';
                el.style.background = 'rgba(17,24,39,0.95)';
                el.style.color = '#fff';
                el.style.boxShadow = '0 12px 28px rgba(0,0,0,0.18)';
                el.style.fontSize = '14px';
                el.style.lineHeight = '1.4';
                el.style.display = 'none';
                el.style.cursor = 'pointer';
                el.addEventListener('click', function () {
                    el.style.display = 'none';
                });

                document.body.appendChild(el);
                return el;
            }

            var hideTimer = null;
            function showToast(message) {
                var el = ensureToast();
                el.textContent = message || 'Có lỗi hệ thống. Vui lòng thử lại sau.';
                el.style.display = 'block';
                if (hideTimer) clearTimeout(hideTimer);
                hideTimer = setTimeout(function () {
                    el.style.display = 'none';
                }, 4000);
            }

            // Generic JS runtime errors
            window.addEventListener('error', function () {
                showToast();
            });
            window.addEventListener('unhandledrejection', function () {
                showToast();
            });

            // Detect failed fetch/XHR (best-effort)
            if (window.fetch) {
                var _fetch = window.fetch;
                window.fetch = function () {
                    return _fetch.apply(this, arguments).then(function (res) {
                        if (!res || (res.status >= 500)) {
                            showToast();
                        }
                        return res;
                    }).catch(function (err) {
                        showToast();
                        throw err;
                    });
                };
            }

            if (window.XMLHttpRequest && window.XMLHttpRequest.prototype) {
                var _open = window.XMLHttpRequest.prototype.open;
                var _send = window.XMLHttpRequest.prototype.send;
                window.XMLHttpRequest.prototype.open = function () {
                    this.__adminToastTrack = true;
                    return _open.apply(this, arguments);
                };
                window.XMLHttpRequest.prototype.send = function () {
                    if (this.__adminToastTrack) {
                        this.addEventListener('load', function () {
                            if (this.status >= 500) showToast();
                        });
                        this.addEventListener('error', function () {
                            showToast();
                        });
                    }
                    return _send.apply(this, arguments);
                };
            }

            var initialMessage = @json(session('admin_error_toast'));
            if (initialMessage) showToast(initialMessage);
        })();
    </script>
@endif

