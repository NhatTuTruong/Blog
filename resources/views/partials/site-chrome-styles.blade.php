<style>
/* Public chrome — header + footer */
.site-header {
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    box-shadow: 0 4px 24px rgba(15, 23, 42, 0.06);
    position: sticky;
    top: 0;
    z-index: 100;
    backdrop-filter: blur(12px);
}
.site-header .header-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0.875rem 1.5rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem 1rem;
}
.site-header .logo {
    font-family: 'Space Grotesk', 'DM Sans', system-ui, sans-serif;
    font-weight: 700;
    font-size: 1.35rem;
    color: #0f172a !important;
    text-decoration: none;
    letter-spacing: -0.03em;
    flex: 1 1 auto;
    min-width: 0;
}
.site-header .logo span {
    color: #2563eb !important;
}
.site-header__actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
}
.site-header__cta {
    display: none;
    align-items: center;
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: #fff !important;
        font-weight: 600;
        font-size: 0.875rem;
        border-radius: 999px;
        text-decoration: none;
        box-shadow: 0 2px 12px rgba(37, 99, 235, 0.35);
        transition: transform 0.2s, box-shadow 0.2s;
}
.site-header__cta:hover {
    color: #fff !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.4);
}
.site-header__toggle {
    display: none;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 6px;
    width: 44px;
    height: 44px;
    padding: 0;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
    color: #0f172a;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.2s;
    -webkit-tap-highlight-color: transparent;
}
.site-header__toggle:hover { background: #f1f5f9; }
.site-header__social {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.social-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    color: #475569;
    text-decoration: none;
    transition: color 0.2s, background 0.2s, transform 0.2s;
}
.social-icon svg {
    width: 18px;
    height: 18px;
}
.social-icon:hover {
    color: #2563eb;
    background: #eff6ff;
    transform: translateY(-2px);
}
.social-icon--facebook:hover { color: #1877f2; background: #e7f3ff; }
.social-icon--instagram:hover { color: #e1306c; background: #fce7f3; }
.social-icon--twitter:hover { color: #000; background: #f3f4f6; }
.social-icon--youtube:hover { color: #ff0000; background: #fee2e2; }
.social-icon--tiktok:hover { color: #000; background: #f0f0f0; }
.social-icon--pinterest:hover { color: #e60023; background: #ffeef0; }
.social-icon--linkedin:hover { color: #0a66c2; background: #e8f0fe; }
@media (max-width: 768px) {
    .site-header__social {
        gap: 0;
    }
    .social-icon {
        width: 32px;
        height: 32px;
    }
    .social-icon svg {
        width: 16px;
        height: 16px;
    }
}
.site-header__toggle-bar {
    display: block;
    width: 20px;
    height: 2px;
    background: currentColor;
    border-radius: 1px;
    transition: transform 0.25s ease, opacity 0.2s ease;
}
.site-header--nav-open .site-header__toggle-bar:nth-child(1) { transform: translateY(8px) rotate(45deg); }
.site-header--nav-open .site-header__toggle-bar:nth-child(2) { opacity: 0; }
.site-header--nav-open .site-header__toggle-bar:nth-child(3) { transform: translateY(-8px) rotate(-45deg); }
.site-header .nav-links {
    display: flex;
    align-items: center;
    gap: 0.25rem 1.25rem;
    flex-wrap: wrap;
}
.site-header .nav-links a {
    color: #475569 !important;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9375rem;
    padding: 0.35rem 0;
    border-bottom: 2px solid transparent;
    transition: color 0.2s, border-color 0.2s;
}
.site-header .nav-links a:hover {
    color: #2563eb !important;
    border-bottom-color: #2563eb;
}

@media (min-width: 769px) {
    .site-header__cta { display: inline-flex; }
    .site-header .header-inner { flex-wrap: nowrap; }
    .site-header .logo { flex: 0 1 auto; }
    .site-header__toggle { display: none !important; }
    .site-header .nav-links {
        display: flex !important;
        width: auto;
        flex-basis: auto;
        order: unset;
        margin: 0;
        padding: 0;
        border-top: none;
        background: transparent;
    }
    .site-header .nav-links a {
        padding: 0.35rem 0;
        border-bottom: 2px solid transparent;
    }
}

@media (max-width: 768px) {
    .site-header__toggle { display: flex; }
    .site-header .nav-links {
        display: none;
        flex-direction: column;
        align-items: stretch;
        gap: 0;
        width: 100%;
        flex-basis: 100%;
        order: 3;
        padding-top: 0.5rem;
        margin: 0 -1.5rem -0.875rem;
        padding-left: 1.5rem;
        padding-right: 1.5rem;
        padding-bottom: 0.75rem;
        border-top: 1px solid #e2e8f0;
        background: #f8fafc;
    }
    .site-header--nav-open .nav-links { display: flex; }
    .site-header .nav-links a {
        padding: 0.75rem 0;
        border-bottom: 1px solid #e2e8f0;
        font-size: 1rem;
    }
    .site-header .nav-links a:last-child { border-bottom: none; }
}

.site-footer {
    background: linear-gradient(180deg, #0f172a 0%, #020617 100%);
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    margin-top: auto;
    color: #e2e8f0;
}
.site-footer .footer-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 3.5rem 1.5rem 2rem;
}
.site-footer .footer-grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr 1fr 1.4fr;
    gap: 2.5rem 2rem;
    margin-bottom: 2rem;
}
@media (max-width: 900px) {
    .site-footer .footer-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 520px) {
    .site-footer .footer-grid { grid-template-columns: 1fr 1fr; gap: 1.5rem 1rem; }
    .site-footer .footer-brand { grid-column: 1 / -1; }
    .site-footer .footer-stories { grid-column: 1 / -1; }
}
.site-footer .footer-brand .logo {
    font-family: 'Space Grotesk', 'DM Sans', sans-serif;
    font-size: 1.25rem;
    font-weight: 700;
    color: #fff !important;
}
.site-footer .footer-brand .logo span { color: #60a5fa !important; }
.site-footer .footer-brand p {
    margin-top: 0.875rem;
    color: #94a3b8;
    font-size: 0.9rem;
    max-width: 280px;
    line-height: 1.6;
}
.site-footer .footer-social-link {
    display: inline-flex;
    margin-top: 1rem;
    color: #94a3b8;
    transition: color 0.2s;
}
.site-footer .footer-social-link:hover { color: #60a5fa; }
.site-footer .footer-col h4 {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
    margin-bottom: 1.125rem;
}
.site-footer .footer-col ul { list-style: none; margin: 0; padding: 0; }
.site-footer .footer-col li { margin-bottom: 0.625rem; }
.site-footer .footer-col a {
    color: #e2e8f0;
    text-decoration: none;
    font-size: 0.9375rem;
    transition: color 0.2s;
}
.site-footer .footer-col a:hover { color: #60a5fa; }

/* Featured Stories column */
.site-footer .footer-stories {}
.site-footer .footer-stories h4 {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
    margin-bottom: 1.125rem;
}
.site-footer .footer-story-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    text-decoration: none;
    margin-bottom: 0.875rem;
    padding-bottom: 0.875rem;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    transition: transform 0.2s;
}
.site-footer .footer-story-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}
.site-footer .footer-story-item:hover {
    transform: translateX(4px);
}
.site-footer .footer-story-thumb {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    object-fit: cover;
    flex-shrink: 0;
}
.site-footer .footer-story-info {
    flex: 1;
    min-width: 0;
}
.site-footer .footer-story-cat {
    font-size: 0.65rem;
    font-weight: 700;
    color: #60a5fa;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: block;
    margin-bottom: 0.2rem;
}
.site-footer .footer-story-title {
    font-size: 0.8125rem;
    font-weight: 500;
    color: #e2e8f0;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.site-footer .footer-disclosure {
    padding: 1.25rem 0;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}
.site-footer .footer-disclosure-text {
    font-size: 0.8125rem;
    color: #94a3b8;
    line-height: 1.6;
    max-width: 720px;
}
.site-footer .footer-disclosure-text a {
    color: #60a5fa;
    text-decoration: underline;
    text-underline-offset: 2px;
}
.site-footer .footer-disclosure-text a:hover { color: #93c5fd; }
.site-footer .footer-bottom {
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}
.site-footer .footer-bottom p {
    color: #64748b;
    font-size: 0.8125rem;
}
.site-footer .footer-legal-links {
    margin-top: 0.5rem;
    font-size: 0.8125rem;
}
.site-footer .footer-legal-links a {
    color: #94a3b8;
    text-decoration: none;
}
.site-footer .footer-legal-links a:hover {
    color: #60a5fa;
}
.site-footer .footer-legal-links span {
    margin: 0 0.35rem;
    color: #475569;
}
</style>
