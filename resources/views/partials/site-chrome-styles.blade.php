<style>
:root {
    --tr-accent-dark: #EF6C00;
    --tr-accent: #FF8F00;
    --tr-accent-mid: #F57C00;
    --tr-accent-light: #F9A825;
    --tr-accent-soft: rgba(255, 143, 0, 0.12);
    --tr-accent-border: rgba(255, 143, 0, 0.35);
    --tr-header-bg: #ffffff;
    --tr-header-soft: #FFF8F0;
    --tr-header-warm: #FFF3E0;
    --tr-text: #374151;
    --tr-text-muted: #bdbdbd;
    --tr-border-light: #fdecd0;
    --tr-dark: #0f1419;
    --tr-dark-elevated: #161d27;
    --tr-dark-card: #1a2332;
    --tr-border: rgba(255, 255, 255, 0.08);
}

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* ===== HEADER (white + orange) ===== */
.site-header {
    position: sticky;
    top: 0;
    z-index: 200;
    background: var(--tr-header-bg);
    box-shadow: 0 2px 16px rgba(255, 143, 0, 0.08);
}

.site-topbar {
    background: #000;
    border-bottom: 1px solid var(--tr-border-light);
}
.site-topbar__inner {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0.4rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
.site-topbar__tagline {
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--tr-accent-mid);
}
.site-topbar__social {
    display: flex;
    align-items: center;
    gap: 0.15rem;
}
.site-topbar__social .social-icon {
    width: 28px;
    height: 28px;
    color: var(--tr-text-muted);
    border-radius: 6px;
}
.site-topbar__social .social-icon:hover {
    color: var(--tr-accent);
    background: var(--tr-accent-soft);
    transform: none;
}
.site-topbar__links {
    display: flex;
    align-items: center;
    gap: 0.5rem 1rem;
    flex-wrap: wrap;
}
.site-topbar__links a {
    color: var(--tr-text-muted);
    text-decoration: none;
    font-size: 0.72rem;
    font-weight: 500;
    transition: color 0.2s;
}
.site-topbar__links a:hover { color: var(--tr-accent); }

.site-navbar {
    background: var(--tr-header-bg);
    border-bottom: 3px solid transparent;
    border-image: linear-gradient(90deg, #F9A825, #FF8F00, #EF6C00) 1;
}
.site-navbar__inner {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0.9rem 1.5rem;
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 1.25rem 2rem;
}

.site-logo {
    display: inline-flex;
    align-items: baseline;
    text-decoration: none;
    font-family: 'Poppins', sans-serif;
    font-weight: 800;
    font-size: 1.5rem;
    letter-spacing: -0.03em;
    line-height: 1;
    flex-shrink: 0;
}
.site-logo__mark {
    color: var(--tr-accent) !important;
    position: relative;
}
.site-logo__mark::after {
    content: '';
    position: absolute;
    left: -2px;
    top: -4px;
    width: 9px;
    height: 9px;
    background: var(--tr-accent-light);
    opacity: 0.45;
    border-radius: 2px;
    transform: rotate(-12deg);
}
.site-logo__text {
    color: var(--tr-text) !important;
}
.site-logo--footer .site-logo__text { color: #fff !important; }
.site-logo--footer .site-logo__mark { color: var(--tr-accent-light) !important; }

.site-nav {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}
.site-nav__link {
    position: relative;
    color: var(--tr-text) !important;
    text-decoration: none;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    padding: 0.5rem 0.85rem 0.65rem;
    border-radius: 0;
    background: none;
    transition: color 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.site-nav__link::after {
    content: '';
    position: absolute;
    left: 0.85rem;
    right: 0.85rem;
    bottom: 0.2rem;
    height: 2px;
    background: linear-gradient(90deg, var(--tr-accent) 0%, var(--tr-accent-hover, #F9A825) 100%);
    border-radius: 2px;
    transform: scaleX(0);
    transform-origin: left center;
    transition: transform 0.38s ease;
}
.site-nav__link:hover,
.site-nav__link--has-menu:hover {
    color: var(--tr-accent) !important;
    background: none;
}
.site-nav__link:hover::after,
.site-nav__link--has-menu:hover::after {
    transform: scaleX(1);
}
.site-nav__link.is-active {
    color: var(--tr-accent) !important;
    background: none;
    box-shadow: none;
}
.site-nav__link.is-active::after {
    transform: scaleX(1);
}
@media (prefers-reduced-motion: reduce) {
    .site-nav__link::after {
        transition-duration: 0.15s;
    }
}
.site-nav__dropdown { position: relative; }
.site-nav__dropdown::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    top: 100%;
    height: 0.75rem;
    z-index: 51;
}
.site-nav__menu {
    display: none;
    position: absolute;
    top: calc(100% + 0.5rem);
    left: 50%;
    transform: translateX(-50%);
    min-width: 210px;
    background: #fff;
    border: 1px solid var(--tr-border-light);
    border-radius: 12px;
    padding: 0.45rem;
    box-shadow: 0 12px 32px rgba(255, 143, 0, 0.12);
    z-index: 52;
}
.site-nav__menu a {
    display: block;
    padding: 0.55rem 0.75rem;
    color: var(--tr-text);
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    letter-spacing: 0;
    text-transform: none;
    border-radius: 8px;
    transition: background 0.15s, color 0.15s;
}
.site-nav__menu a:hover {
    background: var(--tr-accent-soft);
    color: var(--tr-accent);
}
.site-nav__dropdown:hover .site-nav__menu,
.site-nav__dropdown:focus-within .site-nav__menu {
    display: block;
}

.site-search {
    display: flex;
    align-items: center;
    background: #fff;
    border: 1px solid var(--tr-border-light);
    border-radius: 10px;
    overflow: hidden;
    min-width: 210px;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.site-search:focus-within {
    border-color: var(--tr-accent);
    box-shadow: 0 0 0 3px rgba(255, 143, 0, 0.15);
}
.site-search input {
    flex: 1;
    border: none;
    background: transparent;
    padding: 0.55rem 0.25rem 0.55rem 0.9rem;
    color: var(--tr-text);
    font-size: 0.875rem;
    outline: none;
    min-width: 0;
}
.site-search input::placeholder { color: #9ca3af; }
.site-search button {
    border: none;
    background: linear-gradient(135deg, #F9A825 0%, #FF8F00 100%);
    color: #fff;
    padding: 0.45rem 0.7rem;
    margin: 4px;
    border-radius: 7px;
    cursor: pointer;
    display: flex;
    align-items: center;
    transition: filter 0.2s, box-shadow 0.2s;
    box-shadow: 0 2px 8px rgba(255, 143, 0, 0.25);
}
.site-search button:hover {
    filter: brightness(1.05);
    box-shadow: 0 3px 12px rgba(255, 143, 0, 0.35);
}

.site-ticker {
    background: var(--tr-header-warm);
    border-bottom: 1px solid var(--tr-border-light);
    overflow: hidden;
}
.site-ticker__inner {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0.5rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    overflow: hidden;
}
.site-ticker__label {
    flex-shrink: 0;
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #fff;
    background: linear-gradient(135deg, #F9A825 0%, #FF8F00 100%);
    padding: 0.28rem 0.6rem;
    border-radius: 5px;
    box-shadow: 0 2px 6px rgba(255, 143, 0, 0.25);
}
.site-ticker__viewport {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    position: relative;
    mask-image: linear-gradient(90deg, transparent 0%, #000 4%, #000 96%, transparent 100%);
    -webkit-mask-image: linear-gradient(90deg, transparent 0%, #000 4%, #000 96%, transparent 100%);
}
.site-ticker__track {
    display: inline-flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 0;
    width: max-content;
    white-space: nowrap;
    will-change: transform;
    animation: site-ticker-scroll 45s linear infinite;
}
.site-ticker__track:hover {
    animation-play-state: paused;
}
.site-ticker__item {
    flex: 0 0 auto;
    padding: 0 1.25rem;
    font-size: 0.84rem;
    font-weight: 500;
    color: var(--tr-text);
    text-decoration: none;
    white-space: nowrap;
    transition: color 0.2s;
}
.site-ticker__item:hover {
    color: var(--tr-accent);
}
.site-ticker__sep {
    flex: 0 0 auto;
    color: rgba(255, 143, 0, 0.45);
    font-size: 0.75rem;
    white-space: nowrap;
    user-select: none;
}
@keyframes site-ticker-scroll {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}
@media (prefers-reduced-motion: reduce) {
    .site-ticker__track {
        animation-duration: 120s;
    }
}

.site-navbar__actions {
    display: contents;
}

.site-header__toggle,
.site-header__search-toggle {
    display: none;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 5px;
    width: 42px;
    height: 42px;
    padding: 0;
    border: 1px solid var(--tr-accent-border);
    border-radius: 10px;
    background: var(--tr-accent-soft);
    color: var(--tr-accent);
    cursor: pointer;
    flex-shrink: 0;
}

.site-header__search-toggle {
    flex-direction: row;
}
.site-header__toggle-bar {
    display: block;
    width: 20px;
    height: 2px;
    background: currentColor;
    border-radius: 1px;
    transition: transform 0.25s ease, opacity 0.2s ease;
}
.site-header--nav-open .site-header__toggle-bar:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.site-header--nav-open .site-header__toggle-bar:nth-child(2) { opacity: 0; }
.site-header--nav-open .site-header__toggle-bar:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

@media (max-width: 991px) {
    .site-topbar__inner {
        padding-top: 0.28rem;
        padding-bottom: 0.28rem;
    }
    .site-topbar__social .social-icon {
        width: 24px;
        height: 24px;
    }
    .site-topbar__links a {
        font-size: 0.68rem;
    }
    .site-navbar__inner {
        position: relative;
        grid-template-columns: 1fr auto;
        grid-template-rows: auto;
        padding-top: 0.55rem;
        padding-bottom: 0.55rem;
        gap: 0.65rem;
    }
    .site-logo {
        grid-column: 1;
        grid-row: 1;
        font-size: 1.25rem;
    }
    .site-navbar__actions {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        grid-column: 2;
        grid-row: 1;
    }
    .site-header__toggle,
    .site-header__search-toggle {
        display: flex;
        width: 38px;
        height: 38px;
        border-radius: 9px;
    }
    .site-header--search-open .site-header__search-toggle {
        background: linear-gradient(135deg, #F9A825 0%, #FF8F00 100%);
        color: #fff;
        border-color: transparent;
    }
    .site-search {
        display: none;
        position: absolute;
        top: calc(100% + 0.45rem);
        left: 0;
        right: 0;
        z-index: 20;
        min-width: 0;
        width: 100%;
        box-shadow: 0 10px 28px rgba(15, 20, 25, 0.12);
    }
    .site-header--search-open .site-search {
        display: flex;
    }
    .site-nav {
        display: none;
        grid-column: 1 / -1;
        grid-row: 2;
        flex-direction: column;
        align-items: stretch;
        gap: 0;
        padding: 0.5rem 0 0;
        border-top: 1px solid var(--tr-border-light);
        margin-top: 0.35rem;
        background: #fff;
    }
    .site-header--nav-open .site-nav { display: flex; }
    .site-nav__link {
        padding: 0.85rem 0.5rem 0.95rem;
        border-bottom: 1px solid var(--tr-border-light);
        letter-spacing: 0.06em;
    }
    .site-nav__link::after {
        left: 0.5rem;
        right: 0.5rem;
        bottom: 0.55rem;
    }
    .site-nav__dropdown::after {
        display: none;
    }
    .site-nav__dropdown .site-nav__menu {
        position: static;
        transform: none;
        display: block;
        box-shadow: none;
        border: 1px solid var(--tr-border-light);
        background: var(--tr-header-soft);
        margin: 0.25rem 0 0.5rem;
        padding: 0.25rem;
    }
    .site-topbar__links { gap: 0.75rem; }
}

@media (max-width: 520px) {
    .site-topbar__inner,
    .site-navbar__inner,
    .site-ticker__inner { padding-left: 1rem; padding-right: 1rem; }
    .site-topbar__tagline { display: none; }
}

/* ===== FOOTER ===== */
.site-footer {
    position: relative;
    background: linear-gradient(180deg, var(--tr-dark-elevated) 0%, #080b10 100%);
    margin-top: auto;
    color: #e2e8f0;
}
.site-footer__accent {
    height: 4px;
    background: linear-gradient(90deg, #F9A825 0%, #FF8F00 50%, #EF6C00 100%);
}
.site-footer .footer-inner {
    max-width: 1280px;
    margin: 0 auto;
    padding: 3.5rem 1.5rem 2rem;
}
.site-footer .footer-grid {
    display: grid;
    grid-template-columns: 1.3fr 0.9fr 1fr 1.2fr;
    gap: 2.5rem 2rem;
    margin-bottom: 2rem;
}
@media (max-width: 992px) {
    .site-footer .footer-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 560px) {
    .site-footer .footer-grid { grid-template-columns: 1fr; gap: 2rem; }
}
.site-footer .footer-brand p {
    margin-top: 1rem;
    color: #94a3b8;
    font-size: 0.9rem;
    max-width: 300px;
    line-height: 1.65;
}
.footer-brand__social {
    display: flex;
    gap: 0.35rem;
    margin-top: 1.25rem;
}
.footer-brand__social .social-icon {
    width: 36px;
    height: 36px;
    color: var(--tr-text-muted);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.04);
}
.footer-brand__social .social-icon:hover {
    color: var(--tr-accent-light);
    background: var(--tr-accent-soft);
    transform: translateY(-2px);
}

.site-footer .footer-col h4,
.site-footer .footer-stories h4 {
    font-family: 'Poppins', sans-serif;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--tr-accent-light);
    margin-bottom: 1.125rem;
}
.site-footer .footer-col ul { list-style: none; margin: 0; padding: 0; }
.site-footer .footer-col li { margin-bottom: 0.55rem; }
.site-footer .footer-col a {
    color: rgba(255, 255, 255, 0.82);
    text-decoration: none;
    font-size: 0.9rem;
    transition: color 0.2s, padding-left 0.2s;
}
.site-footer .footer-col a:hover {
    color: var(--tr-accent-light);
    padding-left: 4px;
}

.footer-gallery__grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
}
.footer-gallery__item {
    display: block;
    aspect-ratio: 1;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid var(--tr-border);
    transition: transform 0.2s, border-color 0.2s;
}
.footer-gallery__item:hover {
    transform: scale(1.04);
    border-color: var(--tr-accent-border);
}
.footer-gallery__item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.site-footer .footer-story-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    text-decoration: none;
    margin-bottom: 0.875rem;
    padding-bottom: 0.875rem;
    border-bottom: 1px solid var(--tr-border);
    transition: transform 0.2s;
}
.site-footer .footer-story-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}
.site-footer .footer-story-item:hover { transform: translateX(4px); }
.site-footer .footer-story-thumb {
    width: 52px;
    height: 52px;
    border-radius: 10px;
    object-fit: cover;
    flex-shrink: 0;
    border: 1px solid var(--tr-border);
}
.site-footer .footer-story-cat {
    font-size: 0.62rem;
    font-weight: 700;
    color: var(--tr-accent-light);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    display: block;
    margin-bottom: 0.2rem;
}
.site-footer .footer-story-title {
    font-size: 0.8125rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.9);
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.site-footer .footer-bottom {
    padding-top: 1.5rem;
    border-top: 1px solid var(--tr-border);
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
}
.site-footer .footer-bottom p {
    color: #64748b;
    font-size: 0.8125rem;
    margin: 0;
}
.site-footer .footer-legal-links a {
    color: #94a3b8;
    text-decoration: none;
}
.site-footer .footer-legal-links a:hover { color: var(--tr-accent-light); }
.site-footer .footer-legal-links span {
    margin: 0 0.35rem;
    color: #475569;
}

/* Shared social icons */
.social-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: color 0.2s, background 0.2s, transform 0.2s;
}
.social-icon svg { width: 18px; height: 18px; }
</style>
