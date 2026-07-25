@extends('layouts.app')

@section('title', config('app.name') . ' — Blog & Articles')
@section('description', 'Discover guides, stories and trending articles. Search by topic or browse featured categories.')

@push('styles')
<style>
    .blog-home {
        --bh-max: 1240px;
        --bh-ink: #0c0a09;
        --bh-muted: #78716c;
        --bh-line: #e7e5e4;
        --bh-paper: #fafaf9;
        --bh-card: #ffffff;
        --bh-accent: #059669;
        --bh-accent-soft: rgba(5, 150, 105, 0.1);
        --bh-glow: rgba(16, 185, 129, 0.15);
        --bh-radius: 1.25rem;
        --bh-shadow: 0 1px 2px rgba(12, 10, 9, 0.04), 0 12px 40px rgba(12, 10, 9, 0.06);
        background: var(--bh-paper);
    }

    .bh-wrap { max-width: var(--bh-max); margin: 0 auto; padding: 0 1.25rem; }

    /* —— Hero (impact) —— */
    .bh-hero {
        position: relative;
        padding: clamp(3rem, 8vw, 5.5rem) 0 clamp(2.5rem, 6vw, 4rem);
        overflow: hidden;
        color: #f8fafc;
        background: #060a09;
        border-bottom: none;
    }
    .bh-hero__aurora {
        position: absolute;
        inset: -40% -20%;
        background:
            radial-gradient(ellipse 55% 45% at 15% 20%, rgba(16, 185, 129, 0.35), transparent 55%),
            radial-gradient(ellipse 45% 40% at 85% 10%, rgba(6, 182, 212, 0.22), transparent 50%),
            radial-gradient(ellipse 50% 50% at 50% 100%, rgba(5, 150, 105, 0.18), transparent 55%);
        animation: bh-aurora 14s ease-in-out infinite alternate;
        pointer-events: none;
    }
    @keyframes bh-aurora {
        0% { transform: translate(0, 0) scale(1); }
        100% { transform: translate(2%, -3%) scale(1.05); }
    }
    .bh-hero__grid {
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
        background-size: 72px 72px;
        mask-image: radial-gradient(ellipse 80% 70% at 50% 30%, #000 20%, transparent 75%);
        pointer-events: none;
    }
    .bh-hero__noise {
        position: absolute;
        inset: 0;
        opacity: 0.35;
        background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        pointer-events: none;
        mix-blend-mode: overlay;
    }
    .bh-hero__inner { position: relative; z-index: 1; }
    .bh-hero__layout {
        display: grid;
        grid-template-columns: 1fr minmax(280px, 380px);
        gap: clamp(2rem, 5vw, 3.5rem);
        align-items: center;
    }
    @media (max-width: 960px) {
        .bh-hero__layout { grid-template-columns: 1fr; }
        .bh-hero__spotlight { max-width: 26rem; }
    }
    .bh-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        padding: 0.35rem 0.85rem 0.35rem 0.5rem;
        margin-bottom: 1.25rem;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: #6ee7b7;
        background: rgba(16, 185, 129, 0.12);
        border: 1px solid rgba(52, 211, 153, 0.25);
        border-radius: 999px;
    }
    .bh-eyebrow::before {
        content: '';
        width: 0.45rem;
        height: 0.45rem;
        border-radius: 50%;
        background: #34d399;
        box-shadow: 0 0 12px #34d399;
        animation: bh-pulse 2s ease-in-out infinite;
    }
    @keyframes bh-pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.6; transform: scale(0.85); }
    }
    .bh-hero h1 {
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(2.35rem, 5.5vw, 4.5rem);
        font-weight: 700;
        line-height: 1.05;
        letter-spacing: -0.04em;
        color: #fff;
        max-width: 12ch;
    }
    .bh-hero h1 em {
        font-style: normal;
        background: linear-gradient(120deg, #6ee7b7 0%, #2dd4bf 45%, #a7f3d0 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .bh-hero__lead {
        margin-top: 1.15rem;
        font-size: clamp(1rem, 2vw, 1.15rem);
        line-height: 1.65;
        color: rgba(248, 250, 252, 0.72);
        max-width: 36rem;
    }
    .bh-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin: 1.75rem 0 1.5rem;
    }
    .bh-stat {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1.15rem;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 1rem;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        min-width: 8.5rem;
    }
    .bh-stat__icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.65rem;
        background: rgba(16, 185, 129, 0.2);
        color: #6ee7b7;
        flex-shrink: 0;
    }
    .bh-stat strong {
        display: block;
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.35rem;
        font-weight: 700;
        color: #fff;
        line-height: 1.1;
    }
    .bh-stat span {
        font-size: 0.72rem;
        color: rgba(248, 250, 252, 0.5);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 600;
    }

    .bh-search {
        display: flex;
        align-items: stretch;
        gap: 0.5rem;
        padding: 0.45rem;
        background: rgba(255, 255, 255, 0.07);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 1.1rem;
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
        max-width: 100%;
    }
    .bh-search__field {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding: 0 0.85rem;
        min-width: 0;
    }
    .bh-search__field svg { flex-shrink: 0; color: rgba(248, 250, 252, 0.45); }
    .bh-search input {
        flex: 1;
        border: none;
        outline: none;
        background: transparent;
        font-size: 1rem;
        color: #fff;
        min-width: 0;
        padding: 0.8rem 0;
    }
    .bh-search input::placeholder { color: rgba(248, 250, 252, 0.4); }
    .bh-search button {
        border: none;
        cursor: pointer;
        font-weight: 700;
        font-size: 0.92rem;
        padding: 0 1.6rem;
        border-radius: 0.75rem;
        color: #042f1e;
        background: linear-gradient(135deg, #6ee7b7, #34d399);
        box-shadow: 0 4px 20px rgba(52, 211, 153, 0.35);
        transition: transform 0.15s, box-shadow 0.2s;
        white-space: nowrap;
    }
    .bh-search button:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 28px rgba(52, 211, 153, 0.45);
    }
    .bh-search button:active { transform: scale(0.98); }

    .bh-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1.25rem;
    }
    .bh-pill {
        padding: 0.45rem 0.95rem;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: rgba(255, 255, 255, 0.05);
        color: rgba(248, 250, 252, 0.88);
        font-size: 0.84rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s;
    }
    .bh-pill:hover {
        border-color: rgba(52, 211, 153, 0.5);
        color: #a7f3d0;
        background: rgba(16, 185, 129, 0.15);
    }
    .bh-pill--active {
        background: #fff;
        border-color: #fff;
        color: #042f1e;
        font-weight: 700;
    }

    .bh-hero__spotlight {
        display: block;
        position: relative;
        text-decoration: none;
        color: #fff;
        border-radius: 1.35rem;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow:
            0 0 0 1px rgba(255, 255, 255, 0.05) inset,
            0 24px 60px rgba(0, 0, 0, 0.45);
        transform: perspective(800px) rotateY(-4deg) rotateX(2deg);
        transition: transform 0.4s ease, box-shadow 0.4s ease;
    }
    .bh-hero__spotlight:hover {
        transform: perspective(800px) rotateY(0) rotateX(0) translateY(-6px);
        box-shadow: 0 32px 70px rgba(0, 0, 0, 0.5), 0 0 40px rgba(16, 185, 129, 0.15);
    }
    @media (max-width: 960px) {
        .bh-hero__spotlight { transform: none; }
        .bh-hero__spotlight:hover { transform: translateY(-4px); }
    }
    .bh-hero__spotlight-img {
        width: 100%;
        aspect-ratio: 4/5;
        object-fit: cover;
        display: block;
    }
    .bh-hero__spotlight-shade {
        position: absolute;
        inset: 0;
        background: linear-gradient(0deg, rgba(6, 10, 9, 0.95) 0%, rgba(6, 10, 9, 0.2) 55%, transparent 100%);
    }
    .bh-hero__spotlight-body {
        position: absolute;
        inset: auto 0 0 0;
        padding: 1.35rem 1.4rem 1.5rem;
    }
    .bh-hero__spotlight-badge {
        display: inline-block;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        padding: 0.3rem 0.6rem;
        border-radius: 0.35rem;
        background: linear-gradient(135deg, #10b981, #059669);
        margin-bottom: 0.65rem;
    }
    .bh-hero__spotlight-cat {
        display: block;
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #6ee7b7;
        margin-bottom: 0.35rem;
    }
    .bh-hero__spotlight-title {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.15rem;
        font-weight: 700;
        line-height: 1.3;
        margin: 0 0 0.5rem;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .bh-hero__spotlight-meta {
        font-size: 0.8rem;
        color: rgba(248, 250, 252, 0.6);
    }
    .bh-hero__scroll {
        display: flex;
        justify-content: center;
        margin-top: 2rem;
        padding-bottom: 0.5rem;
    }
    .bh-hero__scroll span {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: rgba(248, 250, 252, 0.4);
    }
    .bh-hero__scroll svg {
        animation: bh-bounce 2s ease-in-out infinite;
    }
    @keyframes bh-bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(4px); }
    }

    /* —— Section —— */
    .bh-section { padding: 3.5rem 0; margin-top: -1px; }
    .bh-section--line { border-top: 1px solid var(--bh-line); }
    .bh-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.75rem;
        flex-wrap: wrap;
    }
    .bh-head h2 {
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(1.5rem, 3vw, 2rem);
        font-weight: 700;
        letter-spacing: -0.03em;
        color: var(--bh-ink);
        margin: 0;
    }
    .bh-head p {
        margin: 0.35rem 0 0;
        color: var(--bh-muted);
        font-size: 0.95rem;
    }
    .bh-link {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--bh-accent);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    .bh-link:hover { opacity: 0.8; }

    /* —— Categories bento —— */
    .bh-bento {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 0.85rem;
    }
    .bh-bento__item {
        grid-column: span 3;
        position: relative;
        min-height: 7.5rem;
        border-radius: var(--bh-radius);
        overflow: hidden;
        text-decoration: none;
        color: #fff;
        border: 1px solid var(--bh-line);
        transition: transform 0.25s, box-shadow 0.25s;
    }
    .bh-bento__item:first-child { grid-column: span 6; min-height: 10rem; }
    .bh-bento__item:nth-child(2) { grid-column: span 6; min-height: 10rem; }
    .bh-bento__item:hover {
        transform: translateY(-3px);
        box-shadow: var(--bh-shadow);
    }
    @media (max-width: 900px) {
        .bh-bento__item,
        .bh-bento__item:first-child,
        .bh-bento__item:nth-child(2) { grid-column: span 6; min-height: 8rem; }
    }
    @media (max-width: 560px) {
        .bh-bento__item,
        .bh-bento__item:first-child,
        .bh-bento__item:nth-child(2) { grid-column: span 12; }
    }
    .bh-bento__bg {
        position: absolute;
        inset: 0;
        background-size: cover;
        background-position: center;
        transition: transform 0.5s;
    }
    .bh-bento__item:hover .bh-bento__bg { transform: scale(1.05); }
    .bh-bento__overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(12,10,9,0.15) 0%, rgba(12,10,9,0.75) 100%);
    }
    .bh-bento__body {
        position: relative;
        z-index: 1;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 1.1rem 1.15rem;
    }
    .bh-bento__name {
        font-family: 'Space Grotesk', sans-serif;
        font-weight: 700;
        font-size: 1.05rem;
        line-height: 1.2;
    }
    .bh-bento__meta { font-size: 0.78rem; opacity: 0.85; margin-top: 0.2rem; }

    /* —— Featured cover —— */
    .bh-cover {
        display: block;
        position: relative;
        border-radius: calc(var(--bh-radius) + 0.25rem);
        overflow: hidden;
        text-decoration: none;
        color: #fff;
        min-height: 28rem;
        border: 1px solid var(--bh-line);
        box-shadow: var(--bh-shadow);
        transition: transform 0.3s, box-shadow 0.3s;
    }
    .bh-cover:hover {
        transform: translateY(-4px);
        box-shadow: 0 24px 60px rgba(12, 10, 9, 0.14);
    }
    @media (max-width: 768px) { .bh-cover { min-height: 22rem; } }
    .bh-cover__img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .bh-cover__shade {
        position: absolute;
        inset: 0;
        background: linear-gradient(0deg, rgba(12,10,9,0.92) 0%, rgba(12,10,9,0.35) 45%, rgba(12,10,9,0.1) 100%);
    }
    .bh-cover__content {
        position: absolute;
        inset: auto 0 0 0;
        padding: 2rem 2.25rem;
        max-width: 42rem;
    }
    @media (max-width: 640px) { .bh-cover__content { padding: 1.5rem; } }
    .bh-cover__tag {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        background: var(--bh-accent);
        color: #fff;
        padding: 0.35rem 0.7rem;
        border-radius: 0.35rem;
        margin-bottom: 0.85rem;
    }
    .bh-cover__cat {
        display: block;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        opacity: 0.85;
        margin-bottom: 0.5rem;
    }
    .bh-cover__title {
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(1.75rem, 4vw, 2.75rem);
        font-weight: 700;
        line-height: 1.12;
        letter-spacing: -0.03em;
        margin: 0 0 0.75rem;
    }
    .bh-cover__excerpt {
        font-size: 1rem;
        line-height: 1.55;
        opacity: 0.88;
        margin: 0 0 1rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .bh-cover__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        font-size: 0.85rem;
        opacity: 0.8;
        margin-bottom: 1rem;
    }
    .bh-cover__cta {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-weight: 700;
        font-size: 0.95rem;
        padding: 0.65rem 1.15rem;
        background: #fff;
        color: var(--bh-ink);
        border-radius: 0.6rem;
    }

    /* —— Featured Grid (carousel + cover) —— */
    .bh-featured-grid {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 1.5rem;
        align-items: stretch;
    }
    @media (max-width: 900px) {
        .bh-featured-grid { grid-template-columns: 1fr; }
    }
    .bh-featured-grid__main {
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
    }
    .bh-featured-grid__main .bh-cover__content {
        padding: 1.5rem;
    }
    .bh-featured-grid__main .bh-cover__title {
        font-size: clamp(1.25rem, 3vw, 1.75rem);
    }

    /* Side carousel */
    .bh-featured-carousel {
        position: relative;
        border-radius: calc(var(--bh-radius) + 0.25rem);
        overflow: hidden;
        border: 1px solid var(--bh-line);
        box-shadow: var(--bh-shadow);
        min-height: 28rem;
        touch-action: pan-y;
    }
    .bh-featured-carousel__track {
        position: relative;
        overflow: hidden;
        height: 100%;
        min-height: 28rem;
    }
    .bh-featured-carousel__slide {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        text-decoration: none;
        color: #fff;
        opacity: 0;
        transition: opacity 0.6s ease;
        pointer-events: none;
    }
    .bh-featured-carousel__slide.active {
        opacity: 1;
        pointer-events: auto;
    }
    .bh-featured-carousel__img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .bh-featured-carousel__shade {
        position: absolute;
        inset: 0;
        background: linear-gradient(0deg, rgba(12,10,9,0.92) 0%, rgba(12,10,9,0.2) 60%, transparent 100%);
    }
    .bh-featured-carousel__body {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 1.5rem;
    }
    .bh-featured-carousel__badge {
        display: inline-block;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        background: var(--bh-accent);
        padding: 0.25rem 0.5rem;
        border-radius: 0.3rem;
        margin-bottom: 0.5rem;
    }
    .bh-featured-carousel__cat {
        display: block;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        opacity: 0.85;
        margin-bottom: 0.4rem;
    }
    .bh-featured-carousel__title {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.15rem;
        font-weight: 700;
        line-height: 1.3;
        margin: 0 0 0.6rem;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .bh-featured-carousel__meta {
        font-size: 0.75rem;
        opacity: 0.7;
    }
    .bh-featured-carousel__dots {
        position: absolute;
        bottom: 1rem;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 0.6rem;
        z-index: 10;
    }
    .bh-featured-carousel__dot {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        border: none;
        background: rgba(255,255,255,0.4);
        cursor: pointer;
        transition: background 0.3s, transform 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        -webkit-tap-highlight-color: transparent;
    }
    .bh-featured-carousel__dot::after {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #fff;
        opacity: 0.8;
    }
    .bh-featured-carousel__dot.active {
        background: rgba(255,255,255,0.9);
        transform: scale(1.1);
    }
    .bh-featured-carousel__dot.active::after {
        opacity: 1;
    }
    @media (max-width: 768px) {
        .bh-featured-carousel__dot {
            width: 32px;
            height: 32px;
        }
        .bh-featured-carousel__dot::after {
            width: 8px;
            height: 8px;
        }
    }

    /* —— Trending list —— */
    .bh-trend {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    @media (max-width: 900px) { .bh-trend { grid-template-columns: 1fr; } }
    .bh-trend__card {
        display: grid;
        grid-template-columns: auto 7.5rem 1fr;
        gap: 1rem;
        align-items: center;
        padding: 1rem;
        background: var(--bh-card);
        border: 1px solid var(--bh-line);
        border-radius: var(--bh-radius);
        text-decoration: none;
        color: inherit;
        transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
    }
    .bh-trend__card:hover {
        border-color: rgba(5, 150, 105, 0.35);
        box-shadow: var(--bh-shadow);
        transform: translateX(4px);
    }
    @media (max-width: 520px) {
        .bh-trend__card { grid-template-columns: auto 5.5rem 1fr; gap: 0.75rem; }
    }
    .bh-trend__num {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 2rem;
        font-weight: 700;
        color: var(--bh-line);
        line-height: 1;
        min-width: 2rem;
        text-align: center;
    }
    .bh-trend__card:hover .bh-trend__num { color: var(--bh-accent); }
    .bh-trend__thumb {
        width: 100%;
        aspect-ratio: 4/3;
        object-fit: cover;
        border-radius: 0.75rem;
        background: var(--bh-paper);
    }
    .bh-trend__cat {
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--bh-accent);
        margin-bottom: 0.3rem;
    }
    .bh-trend__title {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.35;
        margin: 0 0 0.4rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .bh-trend__meta {
        font-size: 0.8rem;
        color: var(--bh-muted);
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    /* —— Article grid —— */
    .bh-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
    }
    @media (max-width: 900px) { .bh-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 560px) { .bh-grid { grid-template-columns: 1fr; } }
    .bh-card {
        display: flex;
        flex-direction: column;
        background: var(--bh-card);
        border: 1px solid var(--bh-line);
        border-radius: var(--bh-radius);
        overflow: hidden;
        text-decoration: none;
        color: inherit;
        transition: transform 0.25s, box-shadow 0.25s;
    }
    .bh-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--bh-shadow);
    }
    .bh-card__media {
        position: relative;
        overflow: hidden;
        aspect-ratio: 16/10;
        background: var(--bh-paper);
    }
    .bh-card__media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s;
    }
    .bh-card:hover .bh-card__media img { transform: scale(1.06); }
    .bh-card__body { padding: 1.15rem 1.2rem 1.25rem; flex: 1; display: flex; flex-direction: column; }
    .bh-card__top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.55rem;
    }
    .bh-card__badge {
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--bh-accent);
        background: var(--bh-accent-soft);
        padding: 0.22rem 0.5rem;
        border-radius: 0.3rem;
    }
    .bh-card__date { font-size: 0.78rem; color: var(--bh-muted); }
    .bh-card__title {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.08rem;
        font-weight: 700;
        line-height: 1.35;
        margin: 0 0 0.5rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .bh-card__excerpt {
        font-size: 0.88rem;
        color: var(--bh-muted);
        line-height: 1.5;
        margin: 0 0 0.85rem;
        flex: 1;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .bh-card__foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--bh-accent);
        padding-top: 0.85rem;
        border-top: 1px solid var(--bh-line);
    }
    .bh-card__read { display: inline-flex; align-items: center; gap: 0.3rem; }

    /* —— CTA —— */
    .bh-cta {
        position: relative;
        overflow: hidden;
        border-radius: calc(var(--bh-radius) + 0.25rem);
        padding: 3rem 2rem;
        text-align: center;
        background: var(--bh-ink);
        color: #fff;
    }
    .bh-cta::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 20% 50%, rgba(16, 185, 129, 0.25), transparent 50%),
            radial-gradient(circle at 80% 50%, rgba(13, 148, 136, 0.2), transparent 45%);
        pointer-events: none;
    }
    .bh-cta__inner { position: relative; z-index: 1; max-width: 32rem; margin: 0 auto; }
    .bh-cta h2 {
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(1.5rem, 3vw, 2.1rem);
        font-weight: 700;
        letter-spacing: -0.03em;
        margin: 0 0 0.65rem;
    }
    .bh-cta p { color: rgba(255,255,255,0.75); margin: 0 0 1.5rem; font-size: 1rem; }
    .bh-cta a {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.75rem 1.5rem;
        background: #fff;
        color: var(--bh-ink);
        font-weight: 700;
        text-decoration: none;
        border-radius: 0.65rem;
        transition: transform 0.15s;
    }
    .bh-cta a:hover { transform: scale(1.03); }

    /* —— Filter / empty —— */
    .bh-results {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 1rem 1.15rem;
        background: var(--bh-card);
        border: 1px solid var(--bh-line);
        border-radius: var(--bh-radius);
        margin-bottom: 1.5rem;
    }
    .bh-results p { margin: 0; color: var(--bh-muted); font-size: 0.95rem; }
    .bh-results strong { color: var(--bh-ink); }
    .bh-clear {
        font-size: 0.88rem;
        font-weight: 700;
        color: var(--bh-accent);
        text-decoration: none;
    }
    .bh-empty {
        text-align: center;
        padding: 4rem 1.5rem;
        background: var(--bh-card);
        border: 1px dashed var(--bh-line);
        border-radius: var(--bh-radius);
        color: var(--bh-muted);
    }
    .bh-empty h2 {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.35rem;
        color: var(--bh-ink);
        margin: 0 0 0.5rem;
    }

    @media (max-width: 640px) {
        .bh-search { flex-direction: column; }
        .bh-search button { width: 100%; padding: 0.85rem; }
        .bh-hero { padding: 2.25rem 0 1.75rem; }
        .bh-section { padding: 2.5rem 0; }
        .bh-stats { margin: 1.25rem 0 1rem; }
    }

    /* —— Category Featured Layout (Layout 1: Green) —— */
    .bh-cat-featured { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
    @media (max-width: 768px) { .bh-cat-featured { grid-template-columns: 1fr; } }
    .bh-cat-hero { position: relative; border-radius: var(--bh-radius); overflow: hidden; aspect-ratio: 4/3; display: block; }
    .bh-cat-hero img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease; }
    .bh-cat-hero:hover img { transform: scale(1.04); }
    .bh-cat-hero__shade { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.75) 0%, transparent 60%); }
    .bh-cat-hero__body { position: absolute; bottom: 0; left: 0; right: 0; padding: 1.5rem; color: #fff; }
    .bh-cat-hero__tag { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #fff; margin-bottom: 0.75rem; }
    .bh-cat-hero__body h3 { font-size: 1.35rem; font-weight: 700; line-height: 1.3; margin-bottom: 0.5rem; }
    .bh-cat-hero__body p { font-size: 0.8rem; opacity: 0.85; }
    .bh-cat-grid { display: flex; flex-direction: column; gap: 1rem; }
    .bh-cat-card { display: flex; gap: 1rem; border-radius: var(--bh-radius); overflow: hidden; background: var(--bh-card); box-shadow: var(--bh-shadow); transition: transform 0.2s, box-shadow 0.2s; }
    .bh-cat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
    .bh-cat-card img { width: 100px; height: 80px; object-fit: cover; flex-shrink: 0; }
    .bh-cat-card__body { padding: 0.75rem 0.75rem 0.75rem 0; display: flex; flex-direction: column; justify-content: center; }
    .bh-cat-card__body h4 { font-size: 0.9rem; font-weight: 600; line-height: 1.4; margin-bottom: 0.25rem; color: var(--bh-ink); }
    .bh-cat-card__body span { font-size: 0.75rem; color: var(--bh-muted); }

    /* —— Category Horizontal Layout (Layout 2: Red) —— */
    .bh-cat-horizontal { display: grid; grid-template-columns: 1.4fr 1fr; gap: 1.5rem; }
    @media (max-width: 768px) { .bh-cat-horizontal { grid-template-columns: 1fr; } }
    .bh-cat-h-card { display: flex; flex-direction: column; border-radius: var(--bh-radius); overflow: hidden; background: var(--bh-card); box-shadow: var(--bh-shadow); transition: transform 0.2s; }
    .bh-cat-h-card:hover { transform: translateY(-3px); }
    .bh-cat-h-card img { width: 100%; height: 200px; object-fit: cover; }
    .bh-cat-h-card__body { padding: 1.25rem; }
    .bh-cat-h-card__body h3 { font-size: 1.1rem; font-weight: 700; line-height: 1.4; margin-bottom: 0.5rem; color: var(--bh-ink); }
    .bh-cat-h-card__body p { font-size: 0.85rem; color: var(--bh-muted); line-height: 1.6; margin-bottom: 0.75rem; }
    .bh-cat-h-card__meta { font-size: 0.75rem; color: var(--bh-muted); }
    .bh-cat-h-list { display: flex; flex-direction: column; gap: 1rem; }
    .bh-cat-h-item { display: flex; gap: 1rem; padding: 0.75rem; border-radius: var(--bh-radius); background: var(--bh-card); box-shadow: var(--bh-shadow); transition: background 0.2s; }
    .bh-cat-h-item:hover { background: #fef2f2; }
    .bh-cat-h-item img { width: 80px; height: 70px; object-fit: cover; border-radius: 0.5rem; flex-shrink: 0; }
    .bh-cat-h-item div { display: flex; flex-direction: column; justify-content: center; }
    .bh-cat-h-item h4 { font-size: 0.85rem; font-weight: 600; line-height: 1.4; margin-bottom: 0.25rem; color: var(--bh-ink); }
    .bh-cat-h-item span { font-size: 0.7rem; color: var(--bh-muted); }

    /* —— Category Masonry Layout (Layout 3: Blue) —— */
    .bh-cat-masonry { display: grid; grid-template-columns: repeat(4, 1fr); grid-auto-rows: 200px; gap: 1rem; }
    @media (max-width: 900px) { .bh-cat-masonry { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 600px) { .bh-cat-masonry { grid-template-columns: 1fr; } }
    .bh-cat-m-card { position: relative; border-radius: var(--bh-radius); overflow: hidden; display: block; }
    .bh-cat-m-card img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease; }
    .bh-cat-m-card:hover img { transform: scale(1.06); }
    .bh-cat-m-card--tall { grid-row: span 2; }
    .bh-cat-m-card__body { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 50%); padding: 1rem; display: flex; flex-direction: column; justify-content: flex-end; color: #fff; }
    .bh-cat-m-card__cat { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.3rem; }
    .bh-cat-m-card__body h4 { font-size: 0.9rem; font-weight: 600; line-height: 1.3; margin-bottom: 0.25rem; }
    .bh-cat-m-card__body span { font-size: 0.7rem; opacity: 0.75; }

    /* Remove underlines from all links in category sections */
    .bh-cat-featured a, .bh-cat-grid a, .bh-cat-horizontal a, .bh-cat-masonry a,
    .bh-cat-h-list a { text-decoration: none; }
    .bh-cat-title { color: inherit; }

    /* —— Carousel for Mobile —— */
    .bh-carousel {
        overflow: hidden;
        position: relative;
    }
    .bh-carousel__track {
        display: flex;
        gap: 1rem;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        padding-bottom: 0.5rem;
    }
    .bh-carousel__track::-webkit-scrollbar { display: none; }
    .bh-carousel__slide {
        flex: 0 0 calc(85% - 1rem);
        scroll-snap-align: start;
    }
    @media (min-width: 640px) {
        .bh-carousel__track {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            overflow-x: visible;
            scroll-snap-type: none;
        }
        .bh-carousel__slide { flex: none; width: 100%; }
    }
    @media (min-width: 900px) {
        .bh-carousel.bh-carousel--trending .bh-carousel__track {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    .bh-carousel__slide .bh-trend__card,
    .bh-carousel__slide .bh-card {
        width: 100%;
    }

    /* —— Hero Carousel (rotating featured posts) —— */
    .bh-hero-carousel {
        position: relative;
        max-width: 26rem;
        border-radius: 1.35rem;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow:
            0 0 0 1px rgba(255, 255, 255, 0.05) inset,
            0 24px 60px rgba(0, 0, 0, 0.45);
        transform: perspective(800px) rotateY(-4deg) rotateX(2deg);
        transition: transform 0.4s ease, box-shadow 0.4s ease;
    }
    .bh-hero-carousel:hover {
        transform: perspective(800px) rotateY(0) rotateX(0) translateY(-6px);
        box-shadow: 0 32px 70px rgba(0, 0, 0, 0.5), 0 0 40px rgba(16, 185, 129, 0.15);
    }
    @media (max-width: 960px) {
        .bh-hero-carousel { transform: none; }
        .bh-hero-carousel:hover { transform: translateY(-4px); }
    }
    .bh-hero-carousel__track {
        position: relative;
        overflow: hidden;
        border-radius: 0;
    }
    .bh-hero-carousel__slide {
        display: block;
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        text-decoration: none;
        color: #fff;
        opacity: 0;
        transform: scale(1.05);
        transition: opacity 0.8s ease, transform 0.8s ease;
        pointer-events: none;
    }
    .bh-hero-carousel__slide.active {
        opacity: 1;
        transform: scale(1);
        pointer-events: auto;
        position: relative;
    }
    .bh-hero-carousel__slide .bh-hero__spotlight-img {
        aspect-ratio: 4/5;
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .bh-hero-carousel__slide .bh-hero__spotlight-shade {
        position: absolute;
        inset: 0;
        background: linear-gradient(0deg, rgba(6, 10, 9, 0.95) 0%, rgba(6, 10, 9, 0.2) 55%, transparent 100%);
    }
    .bh-hero-carousel__slide .bh-hero__spotlight-body {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 1.35rem 1.4rem 1.5rem;
    }
    .bh-hero-carousel__dots {
        position: absolute;
        bottom: 1rem;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 0.5rem;
        z-index: 10;
    }
    .bh-hero-carousel__dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        border: none;
        background: rgba(255,255,255,0.4);
        cursor: pointer;
        transition: background 0.3s, transform 0.3s;
    }
    .bh-hero-carousel__dot.active {
        background: #fff;
        transform: scale(1.2);
    }
</style>
@endpush

@section('content')
<div class="blog-home">
    {{-- Hero --}}
    <section class="bh-hero">
        <div class="bh-hero__aurora" aria-hidden="true"></div>
        <div class="bh-hero__grid" aria-hidden="true"></div>
        <div class="bh-hero__noise" aria-hidden="true"></div>
        <div class="bh-wrap bh-hero__inner">
            <div class="bh-hero__layout">
                <div class="bh-hero__copy">
                    <div class="bh-eyebrow">Digital Magazine</div>
                    <h1 class="font-heading">Stories <em>worth</em> your time.</h1>
                    <p class="bh-hero__lead">
                        Curated guides, reviews and insights from {{ config('app.name') }} — explore by topic or search what matters to you.
                    </p>

                    <div class="bh-stats">
                        <div class="bh-stat">
                            <span class="bh-stat__icon" aria-hidden="true">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h10M4 18h7"/></svg>
                            </span>
                            <div>
                                <strong>{{ number_format($stats['posts']) }}</strong>
                                <span>Articles</span>
                            </div>
                        </div>
                        <div class="bh-stat">
                            <span class="bh-stat__icon" aria-hidden="true">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                            </span>
                            <div>
                                <strong>{{ number_format($stats['categories']) }}</strong>
                                <span>Topics</span>
                            </div>
                        </div>
                    </div>

                    <form class="bh-search" action="{{ route('home') }}" method="get" role="search">
                        <div class="bh-search__field">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>
                            <input type="search" name="q" value="{{ $searchQuery }}" placeholder="Search articles, brands, guides…" aria-label="Search articles">
                        </div>
                        @if($selectedCategory)
                            <input type="hidden" name="cat" value="{{ $selectedCategory }}">
                        @endif
                        <button type="submit">Search</button>
                    </form>

                    @if($featuredCategories->isNotEmpty())
                        <nav class="bh-pills" aria-label="Quick topics">
                            <a href="{{ route('home') }}" class="bh-pill {{ !$selectedCategory && !$searchQuery ? 'bh-pill--active' : '' }}">All topics</a>
                            @foreach($featuredCategories->take(8) as $cat)
                                <a href="{{ $cat['url'] }}" class="bh-pill {{ $selectedCategory === $cat['name'] ? 'bh-pill--active' : '' }}">{{ $cat['name'] }}</a>
                            @endforeach
                        </nav>
                    @endif
                </div>

                @if(!$isFiltered && ($featuredPost || (isset($heroRotationPosts) && $heroRotationPosts->isNotEmpty())))
                    <div class="bh-hero-carousel" id="hero-carousel">
                        @php
                            $carouselPosts = collect([$featuredPost])->merge($heroRotationPosts ?? collect())->filter();
                        @endphp
                        <div class="bh-hero-carousel__track">
                            @foreach($carouselPosts as $index => $post)
                                <a href="{{ route('blog.show', $post->slug) }}" class="bh-hero-carousel__slide {{ $index === 0 ? 'active' : '' }}" data-index="{{ $index }}">
                                    <img
                                        src="{{ $post->featured_image_url }}"
                                        alt="{{ $post->title }}"
                                        class="bh-hero__spotlight-img"
                                        loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                                    >
                                    <div class="bh-hero__spotlight-shade"></div>
                                    <div class="bh-hero__spotlight-body">
                                        <span class="bh-hero__spotlight-badge">{{ $index === 0 ? 'Featured' : 'Trending' }}</span>
                                        @if($post->category)
                                            <span class="bh-hero__spotlight-cat">{{ $post->category }}</span>
                                        @endif
                                        <h2 class="bh-hero__spotlight-title">{{ $post->title }}</h2>
                                        <p class="bh-hero__spotlight-meta">
                                            {{ $post->reading_minutes }} min read
                                            @if($post->views_count > 0)
                                                · {{ number_format($post->views_count) }} views
                                            @endif
                                        </p>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                        @if($carouselPosts->count() > 1)
                            <div class="bh-hero-carousel__dots">
                                @foreach($carouselPosts as $index => $post)
                                    <button class="bh-hero-carousel__dot {{ $index === 0 ? 'active' : '' }}" data-index="{{ $index }}" aria-label="Go to slide {{ $index + 1 }}"></button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            @if(!$isFiltered)
                <div class="bh-hero__scroll" aria-hidden="true">
                    <span>
                        Scroll to explore
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                    </span>
                </div>
            @endif
        </div>
    </section>

    <script>
        (function() {
            var carousel = document.getElementById('hero-carousel');
            if (!carousel) return;
            var slides = carousel.querySelectorAll('.bh-hero-carousel__slide');
            var dots = carousel.querySelectorAll('.bh-hero-carousel__dot');
            if (slides.length <= 1) return;

            var current = 0;
            var timer;

            function goTo(index) {
                slides[current].classList.remove('active');
                dots[current].classList.remove('active');
                current = index;
                slides[current].classList.add('active');
                dots[current].classList.add('active');
            }

            function next() {
                goTo((current + 1) % slides.length);
            }

            function startTimer() {
                timer = setInterval(next, 5000);
            }

            dots.forEach(function(dot, i) {
                dot.addEventListener('click', function() {
                    clearInterval(timer);
                    goTo(i);
                    startTimer();
                });
            });

            startTimer();
        })();
    </script>

    @if($isFiltered)
        <section class="bh-section">
            <div class="bh-wrap">
                <div class="bh-results">
                    <p>
                        @if($searchQuery && $selectedCategory)
                            Results for <strong>"{{ $searchQuery }}"</strong> in <strong>{{ $selectedCategory }}</strong>
                        @elseif($searchQuery)
                            Results for <strong>"{{ $searchQuery }}"</strong>
                        @elseif($selectedCategory)
                            Topic: <strong>{{ $selectedCategory }}</strong>
                        @endif
                        — {{ $filteredPosts->count() }} {{ Str::plural('article', $filteredPosts->count()) }}
                    </p>
                    <a href="{{ route('home') }}" class="bh-clear">Clear filters ×</a>
                </div>

                @if($filteredPosts->isNotEmpty())
                    <div class="bh-grid">
                        @foreach($filteredPosts as $post)
                            @include('partials.home-post-card', ['post' => $post])
                        @endforeach
                    </div>
                @else
                    <div class="bh-empty">
                        <h2>No articles found</h2>
                        <p>Try a different keyword or browse topics below.</p>
                        <p style="margin-top:1rem;"><a href="{{ route('home') }}" class="bh-link">Back to home →</a></p>
                    </div>
                @endif
            </div>
        </section>
    @else
        @if($featuredPost)
        <section class="bh-section">
            <div class="bh-wrap">
                <div class="bh-head">
                    <div>
                        <h2 class="font-heading">Featured Story</h2>
                        <p>Most read — hand-picked for you</p>
                    </div>
                </div>
                <div class="bh-featured-grid">
                    <div class="bh-featured-carousel" id="featured-carousel">
                        <div class="bh-featured-carousel__track">
                            @php
                                $featuredSubPosts = $heroRotationPosts->take(4);
                            @endphp
                            @foreach($featuredSubPosts as $index => $post)
                                <a href="{{ route('blog.show', $post->slug) }}" class="bh-featured-carousel__slide {{ $index === 0 ? 'active' : '' }}" data-index="{{ $index }}">
                                    <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="bh-featured-carousel__img" loading="lazy">
                                    <div class="bh-featured-carousel__shade"></div>
                                    <div class="bh-featured-carousel__body">
                                        <span class="bh-featured-carousel__badge">Trending</span>
                                        @if($post->category)
                                            <span class="bh-featured-carousel__cat">{{ $post->category }}</span>
                                        @endif
                                        <h4 class="bh-featured-carousel__title">{{ $post->title }}</h4>
                                        <span class="bh-featured-carousel__meta">{{ $post->reading_minutes }} min · {{ number_format($post->views_count) }} views</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                        @if($featuredSubPosts->count() > 1)
                            <div class="bh-featured-carousel__dots">
                                @foreach($featuredSubPosts as $index => $post)
                                    <button class="bh-featured-carousel__dot {{ $index === 0 ? 'active' : '' }}" data-index="{{ $index }}"></button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <a href="{{ route('blog.show', $featuredPost->slug) }}" class="bh-cover bh-featured-grid__main">
                        <img src="{{ $featuredPost->featured_image_url }}" alt="{{ $featuredPost->title }}" class="bh-cover__img" loading="eager">
                        <div class="bh-cover__shade"></div>
                        <div class="bh-cover__content">
                            <span class="bh-cover__tag">Editor's Pick</span>
                            @if($featuredPost->category)
                                <span class="bh-cover__cat">{{ $featuredPost->category }}</span>
                            @endif
                            <h3 class="bh-cover__title">{{ $featuredPost->title }}</h3>
                            @if($featuredPost->excerpt)
                                <p class="bh-cover__excerpt">{{ $featuredPost->excerpt }}</p>
                            @endif
                            <div class="bh-cover__meta">
                                <span>{{ $featuredPost->created_at?->format('F j, Y') }}</span>
                                <span>{{ $featuredPost->reading_minutes }} min read</span>
                                @if($featuredPost->views_count > 0)
                                    <span>{{ number_format($featuredPost->views_count) }} views</span>
                                @endif
                            </div>
                            <span class="bh-cover__cta">Read article →</span>
                        </div>
                    </a>
                </div>
            </div>
        </section>
        @endif

        <script>
            (function() {
                var carousel = document.getElementById('featured-carousel');
                if (!carousel) return;
                var slides = carousel.querySelectorAll('.bh-featured-carousel__slide');
                var dots = carousel.querySelectorAll('.bh-featured-carousel__dot');
                if (slides.length <= 1) return;

                var current = 0;
                var timer;
                var touchStartX = 0;
                var touchEndX = 0;

                function goTo(index) {
                    slides[current].classList.remove('active');
                    dots[current].classList.remove('active');
                    current = index;
                    slides[current].classList.add('active');
                    dots[current].classList.add('active');
                }

                function next() { goTo((current + 1) % slides.length); }
                function prev() { goTo((current - 1 + slides.length) % slides.length); }

                function startTimer() { timer = setInterval(next, 5000); }

                dots.forEach(function(dot, i) {
                    dot.addEventListener('click', function() {
                        clearInterval(timer);
                        goTo(i);
                        startTimer();
                    });
                });

                carousel.addEventListener('touchstart', function(e) {
                    touchStartX = e.changedTouches[0].screenX;
                    clearInterval(timer);
                }, { passive: true });

                carousel.addEventListener('touchend', function(e) {
                    touchEndX = e.changedTouches[0].screenX;
                    var diff = touchStartX - touchEndX;
                    if (Math.abs(diff) > 50) {
                        if (diff > 0) next();
                        else prev();
                    }
                    startTimer();
                }, { passive: true });

                startTimer();
            })();
        </script>

        @if($latestPosts->isNotEmpty())
        <section class="bh-section">
            <div class="bh-wrap">
                <div class="bh-head">
                    <div>
                        <h2 class="font-heading">Latest Articles</h2>
                        <p>Fresh stories from the blog</p>
                    </div>
                    <a href="{{ route('blog.index') }}" class="bh-link">See all →</a>
                </div>
                <div class="bh-carousel">
                    <div class="bh-carousel__track">
                        @foreach($latestPosts as $post)
                            <div class="bh-carousel__slide">
                                @include('partials.home-post-card', ['post' => $post])
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
        @endif

        @if($featuredCategories->isNotEmpty())
        <section class="bh-section bh-section--line" id="categories">
            <div class="bh-wrap">
                <div class="bh-head">
                    <div>
                        <h2 class="font-heading">Explore Topics</h2>
                        <p>Jump into a category that interests you</p>
                    </div>
                    <a href="{{ route('blog.index') }}" class="bh-link">All articles →</a>
                </div>
                <div class="bh-bento">
                    @foreach($featuredCategories as $cat)
                        <a href="{{ $cat['url'] }}" class="bh-bento__item">
                            <div class="bh-bento__bg" style="background-image: url('{{ $cat['icon'] }}');"></div>
                            <div class="bh-bento__overlay"></div>
                            <div class="bh-bento__body">
                                <div class="bh-bento__name">{{ $cat['name'] }}</div>
                                <div class="bh-bento__meta">
                                    {{ $cat['count'] > 0 ? $cat['count'].' '.Str::plural('article', $cat['count']) : 'Explore →' }}
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        {{-- Category Posts: Each category with different UI layout --}}
        @if(isset($categoryPosts) && $categoryPosts->isNotEmpty())
            @php
                $uiStyles = [
                    ['bg' => '#f0fdf4', 'accent' => '#059669', 'layout' => 'grid'],
                    ['bg' => '#fef2f2', 'accent' => '#dc2626', 'layout' => 'horizontal'],
                    ['bg' => '#eff6ff', 'accent' => '#2563eb', 'layout' => 'masonry'],
                    ['bg' => '#fff7ed', 'accent' => '#ea580c', 'layout' => 'cards'],
                ];
            @endphp
            @foreach($categoryPosts as $catIndex => $cat)
                @php
                    $style = $uiStyles[$catIndex % count($uiStyles)];
                    $firstPost = $cat['posts']->first();
                    $otherPosts = $cat['posts']->skip(1);
                @endphp

                <section class="bh-section bh-section--line">
                    <div class="bh-wrap">
                        <div class="bh-head">
                            <div>
                                <h2 class="font-heading bh-cat-title">{{ $cat['name'] }}</h2>
                                <p>{{ $cat['posts']->count() }} {{ Str::plural('article', $cat['posts']->count()) }}</p>
                            </div>
                            <a href="{{ $cat['url'] }}" class="bh-link">View all →</a>
                        </div>

                        @if($style['layout'] === 'grid' && $firstPost)
                            {{-- Layout 1: Featured + Grid --}}
                            <div class="bh-cat-featured">
                                <a href="{{ route('blog.show', $firstPost->slug) }}" class="bh-cat-hero">
                                    <img src="{{ $firstPost->featured_image_url }}" alt="{{ $firstPost->title }}" loading="lazy">
                                    <div class="bh-cat-hero__shade"></div>
                                    <div class="bh-cat-hero__body">
                                        <span class="bh-cat-hero__tag" style="background: {{ $style['accent'] }};">Latest</span>
                                        <h3>{{ $firstPost->title }}</h3>
                                        <p>{{ $firstPost->created_at?->format('M j, Y') }} · {{ $firstPost->reading_minutes }} min</p>
                                    </div>
                                </a>
                                <div class="bh-cat-grid">
                                    @foreach($otherPosts->take(3) as $post)
                                        <a href="{{ route('blog.show', $post->slug) }}" class="bh-cat-card">
                                            <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" loading="lazy">
                                            <div class="bh-cat-card__body">
                                                <h4>{{ $post->title }}</h4>
                                                <span>{{ $post->created_at?->format('M j') }}</span>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @elseif($style['layout'] === 'horizontal' && $firstPost)
                            {{-- Layout 2: Horizontal Cards --}}
                            <div class="bh-cat-horizontal">
                                <a href="{{ route('blog.show', $firstPost->slug) }}" class="bh-cat-h-card bh-cat-h-card--large">
                                    <img src="{{ $firstPost->featured_image_url }}" alt="{{ $firstPost->title }}" loading="lazy">
                                    <div class="bh-cat-h-card__body">
                                        <h3>{{ $firstPost->title }}</h3>
                                        <p>{{ Str::limit(strip_tags($firstPost->excerpt ?? $firstPost->content ?? ''), 120) }}</p>
                                        <span class="bh-cat-h-card__meta">{{ $firstPost->created_at?->format('M j, Y') }} · {{ $firstPost->reading_minutes }} min</span>
                                    </div>
                                </a>
                                @if($otherPosts->isNotEmpty())
                                <div class="bh-cat-h-list">
                                    @foreach($otherPosts->take(3) as $post)
                                        <a href="{{ route('blog.show', $post->slug) }}" class="bh-cat-h-item">
                                            <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" loading="lazy">
                                            <div>
                                                <h4>{{ $post->title }}</h4>
                                                <span>{{ $post->created_at?->format('M j, Y') }}</span>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        @elseif($style['layout'] === 'masonry')
                            {{-- Layout 3: Masonry-style --}}
                            <div class="bh-cat-masonry">
                                @foreach($cat['posts']->take(4) as $index => $post)
                                    <a href="{{ route('blog.show', $post->slug) }}" class="bh-cat-m-card {{ $index === 0 ? 'bh-cat-m-card--tall' : '' }}">
                                        <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" loading="lazy">
                                        <div class="bh-cat-m-card__body">
                                            @if($post->category)
                                                <span class="bh-cat-m-card__cat" style="color: {{ $style['accent'] }};">{{ $post->category }}</span>
                                            @endif
                                            <h4>{{ $post->title }}</h4>
                                            <span>{{ $post->created_at?->format('M j, Y') }} · {{ $post->reading_minutes }} min</span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            {{-- Layout 4: Clean Cards --}}
                            <div class="bh-grid">
                                @foreach($cat['posts']->take(4) as $post)
                                    @include('partials.home-post-card', ['post' => $post])
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>
            @endforeach
        @endif

        @if($trendingPosts->isNotEmpty())
        <section class="bh-section bh-section--line">
            <div class="bh-wrap">
                <div class="bh-head">
                    <div>
                        <h2 class="font-heading">Trending Now</h2>
                        <p>What readers are loving this week</p>
                    </div>
                    <a href="{{ route('blog.index') }}" class="bh-link">View blog →</a>
                </div>
                <div class="bh-carousel">
                    <div class="bh-carousel__track">
                        @foreach($trendingPosts as $index => $post)
                            <div class="bh-carousel__slide">
                                <a href="{{ route('blog.show', $post->slug) }}" class="bh-trend__card">
                                    <span class="bh-trend__num">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                    <img src="{{ $post->featured_image_url }}" alt="" class="bh-trend__thumb" loading="lazy">
                                    <div>
                                        @if($post->category)
                                            <div class="bh-trend__cat">{{ $post->category }}</div>
                                        @endif
                                        <h3 class="bh-trend__title">{{ $post->title }}</h3>
                                        <div class="bh-trend__meta">
                                            <span>{{ $post->created_at?->format('M j, Y') }}</span>
                                            @if($post->views_count > 0)
                                                <span>{{ number_format($post->views_count) }} views</span>
                                            @endif
                                            <span>{{ $post->reading_minutes }} min</span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
        @endif

        @if(!$featuredPost && $trendingPosts->isEmpty() && $latestPosts->isEmpty())
            <section class="bh-section">
                <div class="bh-wrap">
                    <div class="bh-empty">
                        <h2>No articles yet</h2>
                        <p>New stories will appear here once published. Check back soon!</p>
                    </div>
                </div>
            </section>
        @endif
    @endif

    <section class="bh-section" style="padding-top: 0;">
        <div class="bh-wrap">
            <div class="bh-cta">
                <div class="bh-cta__inner">
                    <h2 class="font-heading">Never miss a story</h2>
                    <p>Every guide, review and insight — all in one place.</p>
                    <a href="{{ route('blog.index') }}">Browse all articles →</a>
                </div>
            </div>
        </div>
    </section>

    @if($isFiltered && $featuredCategories->isNotEmpty())
        <section class="bh-section bh-section--line" style="padding-top: 0;">
            <div class="bh-wrap">
                <div class="bh-head">
                    <div><h2 class="font-heading">Browse Topics</h2></div>
                </div>
                <div class="bh-bento">
                    @foreach($featuredCategories as $cat)
                        <a href="{{ $cat['url'] }}" class="bh-bento__item">
                            <div class="bh-bento__bg" style="background-image: url('{{ $cat['icon'] }}');"></div>
                            <div class="bh-bento__overlay"></div>
                            <div class="bh-bento__body">
                                <div class="bh-bento__name">{{ $cat['name'] }}</div>
                                <div class="bh-bento__meta">{{ $cat['count'] }} {{ Str::plural('article', $cat['count']) }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</div>
@endsection
