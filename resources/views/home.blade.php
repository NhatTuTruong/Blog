@extends('layouts.app')

@section('title', \App\Support\SiteSeo::pageTitle('home'))
@section('description', \App\Support\SiteSeo::pageDescription('home'))
@section('canonical', route('home'))

@push('head')
    @php
        $websiteSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name'),
            'url' => config('app.url'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => route('home').'?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($websiteSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @if(isset($featuredPost) && $featuredPost?->featured_image_url)
        <link rel="preload" as="image" href="{{ $featuredPost->featured_image_url }}" fetchpriority="high">
    @endif
@endpush

@push('styles')
@include('partials.blog-category-tags-styles')
<style>
    /* ================================
       BANNER HOMEPAGE
       ================================ */
    .bh {
        --bh-ink: #0f172a;
        --bh-muted: #64748b;
        --bh-light: #f8fafc;
        --bh-card: #ffffff;
        --bh-accent: #2563eb;
        --bh-accent2: #3b82f6;
        --bh-border: #e2e8f0;
        background: var(--bh-card);
        color: var(--bh-ink);
    }

    .bh-wrap {
        max-width: 1280px;
        margin: 0 auto;
        padding: 0 2rem;
    }
    @media (max-width: 768px) {
        .bh-wrap { padding: 0 1rem; }
    }

    /* ================================
       HERO BANNER - MODERN 2-COLUMN
       ================================ */
    .bh-hero {
        --hero-primary: #2563eb;
        --hero-primary-light: #3b82f6;
        --hero-primary-soft: #60a5fa;
        --hero-primary-muted: #93c5fd;
        --hero-glow: rgba(37, 99, 235, 0.35);
        position: relative;
        min-height: 72vh;
        display: flex;
        align-items: center;
        overflow: hidden;
        background: linear-gradient(155deg, #030712 0%, #0a1628 45%, #0c1a3a 100%);
    }
    .bh-hero__bg {
        position: absolute;
        inset: 0;
        pointer-events: none;
    }
    .bh-hero__grid {
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(rgba(37, 99, 235, 0.07) 1px, transparent 1px),
            linear-gradient(90deg, rgba(37, 99, 235, 0.07) 1px, transparent 1px);
        background-size: 48px 48px;
        mask-image: radial-gradient(ellipse 90% 80% at 50% 40%, #000 20%, transparent 75%);
        -webkit-mask-image: radial-gradient(ellipse 90% 80% at 50% 40%, #000 20%, transparent 75%);
    }
    .bh-hero__aurora {
        position: absolute;
        border-radius: 50%;
        filter: blur(80px);
        opacity: 0.55;
        animation: hero-aurora 14s ease-in-out infinite;
    }
    .bh-hero__aurora--1 {
        width: 520px;
        height: 520px;
        top: -120px;
        left: -80px;
        background: radial-gradient(circle, rgba(37, 99, 235, 0.45) 0%, transparent 70%);
    }
    .bh-hero__aurora--2 {
        width: 480px;
        height: 480px;
        bottom: -100px;
        right: 5%;
        background: radial-gradient(circle, rgba(59, 130, 246, 0.35) 0%, rgba(99, 102, 241, 0.2) 50%, transparent 70%);
        animation-delay: -5s;
    }
    .bh-hero__aurora--3 {
        width: 320px;
        height: 320px;
        top: 35%;
        right: 28%;
        background: radial-gradient(circle, rgba(37, 99, 235, 0.25) 0%, transparent 70%);
        animation-delay: -9s;
    }
    @keyframes hero-aurora {
        0%, 100% { transform: translate(0, 0) scale(1); }
        33% { transform: translate(30px, -20px) scale(1.05); }
        66% { transform: translate(-20px, 15px) scale(0.95); }
    }
    .bh-hero__noise {
        position: absolute;
        inset: 0;
        opacity: 0.035;
        background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
    }
    .bh-hero__content {
        position: relative;
        z-index: 2;
        width: 100%;
        max-width: 1280px;
        margin: 0 auto;
        padding: 4.5rem 2rem;
        display: grid;
        grid-template-columns: 1.05fr 0.95fr;
        gap: 3rem;
        align-items: center;
    }
    @media (max-width: 900px) {
        .bh-hero__content {
            grid-template-columns: 1fr;
            text-align: center;
            gap: 2rem;
            padding: 3rem 1.5rem;
        }
    }
    .bh-hero__label {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.45rem 1rem 0.45rem 0.75rem;
        background: rgba(37, 99, 235, 0.1);
        border: 1px solid rgba(37, 99, 235, 0.35);
        border-radius: 999px;
        color: var(--hero-primary-muted);
        font-size: 0.78rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        margin-bottom: 1.5rem;
        box-shadow: 0 0 24px rgba(37, 99, 235, 0.12);
    }
    .bh-hero__label svg {
        width: 14px;
        height: 14px;
        color: var(--hero-primary-soft);
    }
    .bh-hero__title {
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(2.5rem, 5vw, 3.75rem);
        font-weight: 800;
        line-height: 1.08;
        letter-spacing: -0.035em;
        color: #f8fafc;
        margin: 0 0 1.25rem;
    }
    .bh-hero__title .highlight {
        background: linear-gradient(120deg, #60a5fa 0%, #3b82f6 45%, #2563eb 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .bh-hero__subtitle {
        font-size: 1.05rem;
        color: rgba(226, 232, 240, 0.62);
        line-height: 1.75;
        margin: 0 0 2rem;
        max-width: 460px;
    }
    @media (max-width: 900px) {
        .bh-hero__subtitle { margin: 0 auto 2rem; }
    }
    .bh-hero__search {
        display: flex;
        max-width: 500px;
        background: rgba(15, 23, 42, 0.65);
        border: 1px solid rgba(37, 99, 235, 0.22);
        border-radius: 999px;
        overflow: hidden;
        backdrop-filter: blur(16px);
        box-shadow:
            0 4px 24px rgba(0, 0, 0, 0.25),
            inset 0 1px 0 rgba(255, 255, 255, 0.06);
        transition: border-color 0.25s, box-shadow 0.25s;
    }
    .bh-hero__search:focus-within {
        border-color: rgba(37, 99, 235, 0.5);
        box-shadow:
            0 4px 32px rgba(37, 99, 235, 0.15),
            inset 0 1px 0 rgba(255, 255, 255, 0.08);
    }
    @media (max-width: 900px) {
        .bh-hero__search { margin: 0 auto; }
    }
    .bh-hero__search input {
        flex: 1;
        border: none;
        background: transparent;
        padding: 1rem 1.5rem;
        font-size: 0.95rem;
        color: #f1f5f9;
        outline: none;
    }
    .bh-hero__search input::placeholder {
        color: rgba(148, 163, 184, 0.75);
    }
    .bh-hero__search button {
        border: none;
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: #fff;
        padding: 0.75rem 1.35rem;
        margin: 0.35rem;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.88rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.45rem;
        transition: transform 0.2s, box-shadow 0.2s;
        box-shadow: 0 4px 16px rgba(37, 99, 235, 0.35);
    }
    .bh-hero__search button:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(37, 99, 235, 0.45);
    }
    .bh-hero__search button svg {
        width: 17px;
        height: 17px;
    }
    .bh-hero__cats {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1.5rem;
    }
    @media (max-width: 900px) {
        .bh-hero__cats { justify-content: center; }
    }
    .bh-hero__cat {
        padding: 0.48rem 1rem;
        background: rgba(15, 23, 42, 0.5);
        border: 1px solid rgba(148, 163, 184, 0.18);
        border-radius: 999px;
        color: rgba(226, 232, 240, 0.85);
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 500;
        transition: all 0.22s;
    }
    .bh-hero__cat:hover {
        border-color: rgba(37, 99, 235, 0.45);
        color: #fff;
        background: rgba(37, 99, 235, 0.12);
    }
    .bh-hero__cat.active {
        background: rgba(37, 99, 235, 0.18);
        border-color: rgba(96, 165, 250, 0.55);
        color: #eff6ff;
        box-shadow: 0 0 20px rgba(37, 99, 235, 0.2);
    }
    .bh-hero__stats {
        display: flex;
        gap: 2.5rem;
        margin-top: 2.25rem;
        padding-top: 2rem;
        border-top: 1px solid rgba(37, 99, 235, 0.15);
    }
    @media (max-width: 900px) {
        .bh-hero__stats { justify-content: center; }
    }
    .bh-hero__stat strong {
        display: block;
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.85rem;
        font-weight: 800;
        color: #f8fafc;
        line-height: 1;
        letter-spacing: -0.02em;
    }
    .bh-hero__stat span {
        font-size: 0.72rem;
        color: rgba(147, 197, 253, 0.7);
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-top: 0.35rem;
        display: block;
    }
    .bh-hero__cards {
        position: relative;
        height: 400px;
    }
    @media (max-width: 900px) {
        .bh-hero__cards { display: none; }
    }
    .bh-hero__cards::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 280px;
        height: 280px;
        transform: translate(-40%, -50%);
        border-radius: 50%;
        border: 1px solid rgba(37, 99, 235, 0.15);
        background: radial-gradient(circle, rgba(37, 99, 235, 0.08) 0%, transparent 70%);
    }
    .bh-hero__card {
        position: absolute;
        background: rgba(15, 23, 42, 0.75);
        border: 1px solid rgba(37, 99, 235, 0.2);
        border-radius: 18px;
        backdrop-filter: blur(20px);
        overflow: hidden;
        box-shadow:
            0 20px 40px rgba(0, 0, 0, 0.35),
            0 0 0 1px rgba(255, 255, 255, 0.04) inset;
        transition: transform 0.4s ease, box-shadow 0.4s ease;
    }
    .bh-hero__card:hover {
        box-shadow:
            0 28px 48px rgba(0, 0, 0, 0.4),
            0 0 32px rgba(37, 99, 235, 0.15);
    }
    .bh-hero__card:nth-child(1) {
        width: 230px;
        height: 290px;
        top: 10px;
        right: 70px;
        animation: card-float-1 7s ease-in-out infinite;
        z-index: 3;
    }
    .bh-hero__card:nth-child(2) {
        width: 200px;
        height: 250px;
        bottom: 10px;
        right: 10px;
        animation: card-float-2 7s ease-in-out infinite 1.2s;
        z-index: 2;
    }
    .bh-hero__card:nth-child(3) {
        width: 175px;
        height: 215px;
        top: 50px;
        right: -10px;
        animation: card-float-3 7s ease-in-out infinite 2.4s;
        z-index: 1;
    }
    @keyframes card-float-1 {
        0%, 100% { transform: rotate(-4deg) translateY(0); }
        50% { transform: rotate(-4deg) translateY(-12px); }
    }
    @keyframes card-float-2 {
        0%, 100% { transform: rotate(3deg) translateY(0); }
        50% { transform: rotate(3deg) translateY(-12px); }
    }
    @keyframes card-float-3 {
        0%, 100% { transform: rotate(-2deg) translateY(0); }
        50% { transform: rotate(-2deg) translateY(-12px); }
    }
    .bh-hero__card-img {
        width: 100%;
        height: 135px;
        object-fit: cover;
        border-bottom: 1px solid rgba(37, 99, 235, 0.12);
    }
    .bh-hero__card:nth-child(2) .bh-hero__card-img { height: 115px; }
    .bh-hero__card:nth-child(3) .bh-hero__card-img { height: 95px; }
    .bh-hero__card-body {
        padding: 0.85rem 0.9rem;
    }
    .bh-hero__card-cat {
        font-size: 0.62rem;
        font-weight: 700;
        color: var(--hero-primary-soft);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 0.35rem;
    }
    .bh-hero__card-title {
        font-size: 0.82rem;
        font-weight: 600;
        color: #f1f5f9;
        line-height: 1.35;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    @keyframes bh-fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* ================================
       SECTION
       ================================ */
    .bh-section {
        padding: 4rem 0;
    }
    .bh-section--alt {
        background: var(--bh-light);
    }
    .bh-section__header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }
    .bh-section__header h2 {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0;
        letter-spacing: -0.02em;
    }
    .bh-section__header a {
        color: var(--bh-accent);
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
    }
    .bh-section__header a:hover {
        text-decoration: underline;
    }

    /* ================================
       CARDS GRID
       ================================ */
    .bh-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 2rem;
    }
    @media (max-width: 1024px) {
        .bh-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 640px) {
        .bh-grid { grid-template-columns: 1fr; }
    }

    /* ================================
       CAROUSEL (Mobile)
       ================================ */
    @media (max-width: 640px) {
        .bh-grid--carousel,
        .bh-trending--carousel {
            display: flex !important;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            gap: 1rem;
            padding-bottom: 1rem;
            margin: 0 -1rem;
            padding-left: 1rem;
            padding-right: 1rem;
            /* Override grid from .bh-trending */
            grid-template-columns: unset !important;
        }
        .bh-grid--carousel::-webkit-scrollbar,
        .bh-trending--carousel::-webkit-scrollbar {
            display: none;
        }
        .bh-grid--carousel > *,
        .bh-trending--carousel > * {
            scroll-snap-align: start;
            flex-shrink: 0;
        }
        .bh-grid--carousel .bh-card {
            width: 280px;
        }
        .bh-trending--carousel .bh-trend {
            width: 280px;
            flex-direction: column;
        }
        .bh-trending--carousel .bh-trend__img {
            width: 100%;
            height: 140px;
        }
        .bh-trending--carousel .bh-trend__num {
            font-size: 1.5rem;
            min-width: 1.5rem;
        }
        /* Mobile typography fixes */
        .bh-section__header {
            margin-bottom: 1.25rem;
        }
        .bh-section__header h2 {
            font-size: 1.35rem;
        }
        .bh-section__header a {
            font-size: 0.8rem;
        }
        .bh-card__title {
            font-size: 0.95rem;
        }
        .bh-card__title a {
            -webkit-line-clamp: 2;
        }
        .bh-card__excerpt {
            font-size: 0.8rem;
            -webkit-line-clamp: 2;
        }
        .bh-card__meta {
            font-size: 0.7rem;
        }
        .bh-trend__title {
            font-size: 0.85rem;
            -webkit-line-clamp: 2;
        }
        .bh-trend__meta {
            font-size: 0.7rem;
        }
        .bh-trend__cat {
            font-size: 0.65rem;
        }
        .bh-featured__carousel-body .bh-card__title {
            font-size: 1.1rem !important;
        }
        .bh-featured__carousel-body .bh-card__excerpt {
            font-size: 0.85rem;
            -webkit-line-clamp: 2;
        }
        .bh-featured__carousel-body {
            padding: 1.25rem 1.25rem 1.5rem;
        }
        .bh-featured__carousel-dots {
            display: none !important;
        }
    }
    .bh-card {
        background: var(--bh-card);
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid var(--bh-border);
        transition: all 0.3s ease;
    }
    .bh-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        border-color: transparent;
    }
    .bh-card__img {
        width: 100%;
        aspect-ratio: 16/10;
        object-fit: cover;
        display: block;
        transition: transform 0.5s ease;
    }
    .bh-card:hover .bh-card__img {
        transform: scale(1.08);
    }
    .bh-card__media {
        overflow: hidden;
        position: relative;
    }
    .bh-card__body {
        padding: 1.5rem;
    }
    .bh-card__title {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.15rem;
        font-weight: 700;
        line-height: 1.4;
        margin: 0 0 0.75rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .bh-card__title a {
        color: var(--bh-ink);
        text-decoration: none;
        transition: color 0.2s;
    }
    .bh-card__title a:hover {
        color: var(--bh-accent);
    }
    .bh-card__excerpt {
        font-size: 0.9rem;
        color: var(--bh-muted);
        line-height: 1.6;
        margin: 0 0 1rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .bh-card__meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 1rem;
        border-top: 1px solid var(--bh-border);
        font-size: 0.8rem;
        color: var(--bh-muted);
    }
    .bh-card__read {
        color: var(--bh-accent);
        font-weight: 600;
        text-decoration: none;
    }
    .bh-card__read:hover {
        text-decoration: underline;
    }

    /* ================================
       FEATURED (EQUAL HEIGHT CAROUSEL)
       ================================ */
    .bh-featured {
        display: grid;
        grid-template-columns: 1.6fr 1fr;
        gap: 2rem;
        align-items: stretch;
    }
    @media (max-width: 900px) {
        .bh-featured { grid-template-columns: 1fr; }
    }

    /* Left Carousel */
    .bh-featured__carousel {
        position: relative;
        border-radius: 20px;
        overflow: hidden;
        background: var(--bh-card);
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        height: 100%;
        min-height: 500px;
    }
    .bh-featured__carousel-track {
        position: relative;
        height: 100%;
        min-height: 500px;
    }
    .bh-featured__carousel-slide {
        position: absolute;
        inset: 0;
        opacity: 0;
        transition: opacity 0.6s ease;
        pointer-events: none;
        overflow: hidden;
        background: #0f172a;
    }
    .bh-featured__carousel-slide.active {
        opacity: 1;
        pointer-events: auto;
    }
    .bh-featured__carousel-slide-link {
        display: block;
        position: relative;
        width: 100%;
        height: 100%;
        text-decoration: none;
        color: inherit;
    }
    .bh-featured__carousel-slide img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .bh-featured__carousel-body {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 2;
        padding: 1.75rem 1.75rem 3.75rem;
        background: linear-gradient(
            to top,
            rgba(15, 23, 42, 0.97) 0%,
            rgba(15, 23, 42, 0.82) 45%,
            rgba(15, 23, 42, 0.2) 100%
        );
    }
    .bh-featured__carousel-body .bh-card__cat {
        color: rgba(255, 255, 255, 0.88);
        margin-bottom: 0.35rem;
    }
    .bh-featured__carousel-body .bh-card__title {
        font-size: 1.5rem;
        -webkit-line-clamp: 2;
        color: #ffffff;
        margin-bottom: 0.5rem;
    }
    .bh-featured__carousel-body .bh-card__title a {
        color: inherit;
        text-decoration: none;
        text-shadow: 0 2px 12px rgba(0, 0, 0, 0.35);
    }
    .bh-featured__carousel-body .bh-card__excerpt {
        -webkit-line-clamp: 2;
        color: rgba(255, 255, 255, 0.86);
        margin-bottom: 0.75rem;
    }
    .bh-featured__carousel-body .bh-card__meta {
        color: rgba(255, 255, 255, 0.78);
        border-top-color: rgba(255, 255, 255, 0.18);
    }
    .bh-featured__carousel-body .bh-card__read {
        color: #ffffff;
    }
    .bh-featured__carousel-dots {
        position: absolute;
        bottom: 1.1rem;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 0.5rem;
        z-index: 12;
    }
    .bh-featured__carousel-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        border: none;
        background: rgba(255, 255, 255, 0.5);
        cursor: pointer;
        transition: all 0.3s;
        padding: 0;
    }
    .bh-featured__carousel-dot.active {
        background: #fff;
        transform: scale(1.2);
    }
    .bh-featured__carousel-arrows {
        position: absolute;
        top: 50%;
        left: 0;
        right: 0;
        transform: translateY(-50%);
        display: flex;
        justify-content: space-between;
        padding: 0 1rem;
        z-index: 10;
        pointer-events: none;
    }
    .bh-featured__carousel-arrow {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        border: none;
        background: rgba(255, 255, 255, 0.9);
        color: var(--bh-ink);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: auto;
        transition: all 0.2s;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    .bh-featured__carousel-arrow:hover {
        background: #fff;
        transform: scale(1.1);
    }

    /* Right Sidebar - overlay title on image */
    .bh-featured__sidebar {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        height: 100%;
        min-height: 500px;
    }
    .bh-featured__sidebar-item {
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 0;
        background: #0f172a;
        border-radius: 14px;
        border: 1px solid var(--bh-border);
        text-decoration: none;
        color: inherit;
        transition: all 0.3s;
        flex: 1;
        min-height: 0;
        overflow: hidden;
        isolation: isolate;
    }
    .bh-featured__sidebar-item:hover {
        border-color: var(--bh-accent);
        transform: translateX(4px);
        box-shadow: 0 4px 20px rgba(37, 99, 235, 0.18);
    }
    .bh-featured__sidebar-item img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 0;
        z-index: 0;
    }
    .bh-featured__sidebar-item-body {
        position: relative;
        z-index: 2;
        flex: 0 0 auto;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        gap: 0.35rem;
        min-width: 0;
        padding: 0.85rem 1rem 0.95rem;
        background: linear-gradient(
            to top,
            rgba(15, 23, 42, 0.96) 0%,
            rgba(15, 23, 42, 0.78) 55%,
            rgba(15, 23, 42, 0.15) 100%
        );
    }
    .bh-featured__sidebar-item .bh-card__cat {
        color: rgba(255, 255, 255, 0.88);
        font-size: 0.68rem;
        margin-bottom: 0.1rem;
    }
    .bh-featured__sidebar-item h4 {
        font-size: 0.92rem;
        font-weight: 650;
        line-height: 1.35;
        margin: 0;
        color: #ffffff;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-shadow: 0 1px 8px rgba(0, 0, 0, 0.35);
    }
    .bh-featured__sidebar-item span {
        font-size: 0.72rem;
        color: rgba(255, 255, 255, 0.78);
    }

    /* Mobile Carousel */
    @media (max-width: 900px) {
        .bh-featured__carousel {
            min-height: 400px;
        }
        .bh-featured__carousel-track {
            min-height: 400px;
        }
        .bh-featured__carousel-body {
            padding: 1.25rem 1.25rem 3.25rem;
        }
        .bh-featured__sidebar {
            min-height: 0;
            height: auto;
        }
        .bh-featured__sidebar-item {
            min-height: 132px;
        }
    }

    /* ================================
       TRENDING
       ================================ */
    .bh-trending {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }
    @media (max-width: 768px) {
        .bh-trending { grid-template-columns: 1fr; }
    }
    .bh-trend {
        display: flex;
        gap: 1.25rem;
        padding: 1.25rem;
        background: var(--bh-card);
        border-radius: 14px;
        border: 1px solid var(--bh-border);
        text-decoration: none;
        color: inherit;
        transition: all 0.3s;
    }
    .bh-trend:hover {
        border-color: var(--bh-accent);
        transform: translateX(4px);
    }
    .bh-trend__num {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 2rem;
        font-weight: 800;
        color: var(--bh-border);
        min-width: 2.5rem;
        text-align: center;
        transition: color 0.3s;
    }
    .bh-trend:hover .bh-trend__num {
        color: var(--bh-accent);
    }
    .bh-trend__img {
        width: 90px;
        height: 75px;
        object-fit: cover;
        border-radius: 10px;
        flex-shrink: 0;
    }
    .bh-trend__body {
        flex: 1;
    }
    .bh-trend__cat {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--bh-accent);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 0.35rem;
    }
    .bh-trend__title {
        font-size: 0.95rem;
        font-weight: 600;
        line-height: 1.4;
        margin: 0 0 0.5rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .bh-trend__meta {
        font-size: 0.75rem;
        color: var(--bh-muted);
    }

    /* ================================
       CTA BANNER
       ================================ */
    .bh-cta {
        position: relative;
        overflow: hidden;
        border-radius: 24px;
        padding: 4rem 2rem;
        text-align: center;
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        margin: 2rem 0;
    }
    .bh-cta::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 30% 50%, rgba(37, 99, 235, 0.3) 0%, transparent 50%),
            radial-gradient(circle at 70% 50%, rgba(139, 92, 246, 0.25) 0%, transparent 50%);
    }
    .bh-cta__inner {
        position: relative;
        z-index: 1;
        max-width: 600px;
        margin: 0 auto;
    }
    .bh-cta h2 {
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(1.75rem, 4vw, 2.5rem);
        font-weight: 800;
        color: #fff;
        margin: 0 0 1rem;
        letter-spacing: -0.03em;
    }
    .bh-cta p {
        color: rgba(255, 255, 255, 0.7);
        font-size: 1.1rem;
        margin: 0 0 2rem;
        line-height: 1.6;
    }
    .bh-cta a {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 1rem 2rem;
        background: linear-gradient(135deg, var(--bh-accent) 0%, var(--bh-accent2) 100%);
        color: #fff;
        text-decoration: none;
        font-weight: 700;
        font-size: 1rem;
        border-radius: 12px;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .bh-cta a:hover {
        transform: scale(1.05);
        box-shadow: 0 10px 40px rgba(37, 99, 235, 0.4);
    }

    /* ================================
       EMPTY
       ================================ */
    .bh-empty {
        text-align: center;
        padding: 5rem 2rem;
        background: var(--bh-light);
        border-radius: 20px;
    }
    .bh-empty h2 {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.5rem;
        margin: 0 0 0.5rem;
    }
    .bh-empty p {
        color: var(--bh-muted);
        margin: 0;
    }

    /* ================================
       FILTER RESULTS
       ================================ */
    .bh-results {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.25rem 1.5rem;
        background: var(--bh-light);
        border-radius: 14px;
        margin-bottom: 2rem;
    }
    .bh-results p { margin: 0; color: var(--bh-muted); }
    .bh-results strong { color: var(--bh-ink); }
    .bh-results a {
        color: var(--bh-accent);
        font-weight: 600;
        text-decoration: none;
    }
    .bh-results a:hover { text-decoration: underline; }

    /* Mobile */
    @media (max-width: 640px) {
        .bh-hero__stats { gap: 1.5rem; }
        .bh-hero__stat strong { font-size: 1.75rem; }
        .bh-hero__search { flex-direction: column; border-radius: 14px; }
        .bh-hero__search button { border-radius: 10px; }
    }

    /* ================================
       CATEGORY LAYOUTS
       ================================ */

    /* Layout 1: Magazine Grid */
    .bh-cat-magazine {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
    }
    .bh-cat-magazine .bh-card:first-child {
        grid-column: span 2;
        grid-row: span 2;
    }
    .bh-cat-magazine .bh-card:first-child .bh-card__img {
        aspect-ratio: 4/3;
    }
    .bh-cat-magazine .bh-card:first-child .bh-card__title {
        font-size: 1.5rem;
    }
    @media (max-width: 768px) {
        .bh-cat-magazine { grid-template-columns: repeat(2, 1fr); }
        .bh-cat-magazine .bh-card:first-child { grid-column: span 2; grid-row: span 1; }
    }
    @media (max-width: 500px) {
        .bh-cat-magazine { grid-template-columns: 1fr; }
        .bh-cat-magazine .bh-card:first-child { grid-column: span 1; }
    }

    /* Layout 2: List with Large Image */
    .bh-cat-list {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
    }
    .bh-cat-list__featured {
        position: relative;
        border-radius: 20px;
        overflow: hidden;
    }
    .bh-cat-list__featured img {
        width: 100%;
        aspect-ratio: 3/4;
        object-fit: cover;
        display: block;
        transition: transform 0.5s;
    }
    .bh-cat-list__featured:hover img { transform: scale(1.05); }
    .bh-cat-list__featured-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 2rem;
        background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 100%);
        color: #fff;
    }
    .bh-cat-list__featured-overlay .bh-card__cat {
        color: #93c5fd;
    }
    .bh-cat-list__featured-overlay .bh-card__title {
        color: #fff;
        font-size: 1.5rem;
    }
    .bh-cat-list__featured-overlay .bh-card__meta { color: rgba(255,255,255,0.7); border-color: rgba(255,255,255,0.2); }
    .bh-cat-list__items {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .bh-cat-list__item {
        display: flex;
        gap: 1rem;
        padding: 1rem;
        background: var(--bh-card);
        border-radius: 14px;
        border: 1px solid var(--bh-border);
        text-decoration: none;
        color: inherit;
        transition: all 0.3s;
    }
    .bh-cat-list__item:hover {
        border-color: var(--bh-accent);
        transform: translateX(4px);
        box-shadow: 0 4px 20px rgba(37, 99, 235, 0.1);
    }
    .bh-cat-list__item-thumb {
        width: 100px;
        height: 80px;
        flex-shrink: 0;
    }
    .bh-cat-list__item-body { flex: 1; }
    .bh-cat-list__item-body h4 {
        font-size: 0.95rem;
        font-weight: 600;
        line-height: 1.4;
        margin: 0 0 0.35rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .bh-cat-list__item-body span { font-size: 0.75rem; color: var(--bh-muted); }
    @media (max-width: 768px) {
        .bh-cat-list { grid-template-columns: 1fr; }
        .bh-cat-list__featured img { aspect-ratio: 16/10; }
    }

    /* Layout 3: Masonry Cards */
    .bh-cat-masonry {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
    }
    .bh-cat-masonry .bh-card:nth-child(3n+1) { transform: translateY(0); }
    .bh-cat-masonry .bh-card:nth-child(3n+2) { transform: translateY(30px); }
    .bh-cat-masonry .bh-card:nth-child(3n) { transform: translateY(15px); }
    .bh-cat-masonry .bh-card:hover { transform: translateY(0) !important; }
    @media (max-width: 900px) {
        .bh-cat-masonry { grid-template-columns: repeat(2, 1fr); }
        .bh-cat-masonry .bh-card { transform: none !important; }
    }
    @media (max-width: 500px) {
        .bh-cat-masonry { grid-template-columns: 1fr; }
    }

    /* Layout 4: Horizontal Scrolling */
    .bh-cat-scroll {
        display: flex;
        gap: 1.5rem;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 1rem;
        scrollbar-width: thin;
        scrollbar-color: var(--bh-border) transparent;
    }
    .bh-cat-scroll::-webkit-scrollbar { height: 6px; }
    .bh-cat-scroll::-webkit-scrollbar-track { background: transparent; }
    .bh-cat-scroll::-webkit-scrollbar-thumb { background: var(--bh-border); border-radius: 3px; }
    .bh-cat-scroll .bh-card {
        flex: 0 0 320px;
        scroll-snap-align: start;
    }
    @media (min-width: 768px) {
        .bh-cat-scroll { flex-wrap: wrap; overflow-x: visible; }
        .bh-cat-scroll .bh-card { flex: 1 1 calc(33.333% - 1rem); }
    }

    /* Layout 5: Zigzag / Alternating */
    .bh-cat-zigzag {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }
    .bh-cat-zigzag-item {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        align-items: center;
        padding: 2rem;
        background: var(--bh-card);
        border-radius: 20px;
        border: 1px solid var(--bh-border);
        text-decoration: none;
        color: inherit;
        transition: all 0.3s;
    }
    .bh-cat-zigzag-item:hover {
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        transform: translateY(-4px);
    }
    .bh-cat-zigzag-item:nth-child(even) { direction: rtl; }
    .bh-cat-zigzag-item:nth-child(even) > * { direction: ltr; }
    .bh-cat-zigzag-item__media {
        width: 100%;
        overflow: hidden;
        border-radius: 14px;
    }
    .bh-cat-zigzag-item__media img {
        width: 100%;
        aspect-ratio: 16/10;
        object-fit: cover;
        display: block;
        transition: transform 0.5s;
    }
    .bh-cat-zigzag-item:hover .bh-cat-zigzag-item__media img { transform: scale(1.03); }
    .bh-cat-zigzag-item-body { padding: 1rem 0; }
    .bh-cat-zigzag-item-body h3 {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.35rem;
        font-weight: 700;
        line-height: 1.3;
        margin: 0 0 0.75rem;
    }
    .bh-cat-zigzag-item-body p {
        font-size: 0.95rem;
        color: var(--bh-muted);
        line-height: 1.6;
        margin: 0 0 1rem;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .bh-cat-zigzag-item-body .bh-card__meta { border-top: none; padding-top: 0; }
    @media (max-width: 768px) {
        .bh-cat-zigzag-item { grid-template-columns: 1fr; gap: 1rem; }
        .bh-cat-zigzag-item:nth-child(even) { direction: ltr; }
    }

    /* ================================
       CATEGORY ZONE — SINGLE FIXED SIDEBAR
       ================================ */
    .bh-cat-zone {
        padding: 2rem 0 4rem;
    }
    .bh-cat-page-layout {
        display: grid;
        grid-template-columns: 1fr 300px;
        gap: 2.5rem;
        align-items: start;
    }
    @media (max-width: 1024px) {
        .bh-cat-page-layout { grid-template-columns: 1fr; }
    }
    .bh-cat-page-layout__main { min-width: 0; }

    .bh-cat-block {
        padding: 2rem 0;
        border-bottom: 1px solid var(--bh-border);
        scroll-margin-top: 6rem;
    }
    .bh-cat-block:first-child { padding-top: 0; }
    .bh-cat-block:last-child { border-bottom: none; padding-bottom: 0; }
    .bh-cat-block--alt {
        margin: 0 -2rem;
        padding: 2rem;
        background: var(--bh-light);
        border-bottom: none;
    }
    @media (max-width: 768px) {
        .bh-cat-block--alt { margin: 0 -1rem; padding: 1.5rem 1rem; }
    }

    .bh-cat-sidebar {
        position: sticky;
        top: 5.5rem;
        align-self: start;
        z-index: 10;
    }
    .bh-cat-sidebar__inner {
        background: linear-gradient(165deg, #0f172a 0%, #1e3a8a 55%, #1e40af 100%);
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow:
            0 24px 48px rgba(15, 23, 42, 0.28),
            0 0 0 1px rgba(96, 165, 250, 0.25),
            inset 0 1px 0 rgba(255, 255, 255, 0.08);
        overflow: hidden;
        position: relative;
        max-height: calc(100vh - 6.5rem);
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
    }
    .bh-cat-sidebar__inner::-webkit-scrollbar { width: 4px; }
    .bh-cat-sidebar__inner::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 4px;
    }
    .bh-cat-sidebar__inner::before {
        content: '';
        position: absolute;
        top: -40%;
        right: -30%;
        width: 220px;
        height: 220px;
        background: radial-gradient(circle, rgba(96, 165, 250, 0.35) 0%, transparent 70%);
        pointer-events: none;
    }
    @media (max-width: 1024px) {
        .bh-cat-sidebar {
            position: static;
            order: -1;
            margin-bottom: 1.5rem;
        }
    }
    .bh-cat-sidebar__widget {
        position: relative;
        z-index: 1;
        background: rgba(255, 255, 255, 0.07);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 14px;
        padding: 1.25rem;
        margin-bottom: 1rem;
        backdrop-filter: blur(8px);
    }
    .bh-cat-sidebar__widget:last-child { margin-bottom: 0; }
    .bh-cat-sidebar__title {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #93c5fd;
        margin: 0 0 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .bh-cat-sidebar__title::before {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #60a5fa;
        box-shadow: 0 0 10px rgba(96, 165, 250, 0.8);
    }
    .bh-cat-sidebar__cats {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .bh-cat-sidebar__cats li { margin: 0; }
    .bh-cat-sidebar__cats a {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.6rem 0.65rem;
        margin: 0 -0.65rem;
        font-size: 0.875rem;
        color: rgba(255, 255, 255, 0.78);
        text-decoration: none;
        border-radius: 8px;
        transition: background 0.15s, color 0.15s, transform 0.15s;
    }
    .bh-cat-sidebar__cats a:hover,
    .bh-cat-sidebar__cats a.active,
    .bh-cat-sidebar__cats a.is-current {
        color: #fff;
        background: rgba(37, 99, 235, 0.35);
        transform: translateX(3px);
    }
    .bh-cat-sidebar__cat-name {
        flex: 1;
        min-width: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .bh-cat-sidebar__cat-count {
        flex-shrink: 0;
        font-size: 0.72rem;
        font-weight: 700;
        background: rgba(255, 255, 255, 0.12);
        color: #bfdbfe;
        padding: 0.2rem 0.55rem;
        border-radius: 999px;
    }
    .bh-cat-sidebar__cats a:hover .bh-cat-sidebar__cat-count,
    .bh-cat-sidebar__cats a.active .bh-cat-sidebar__cat-count,
    .bh-cat-sidebar__cats a.is-current .bh-cat-sidebar__cat-count {
        background: rgba(255, 255, 255, 0.22);
        color: #fff;
    }
    .bh-cat-sidebar__trending {
        list-style: none;
        margin: 0;
        padding: 0;
        counter-reset: trend;
    }
    .bh-cat-sidebar__trending li {
        counter-increment: trend;
        margin: 0;
        padding: 0.65rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }
    .bh-cat-sidebar__trending li:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }
    .bh-cat-sidebar__trending a {
        display: flex;
        gap: 0.75rem;
        text-decoration: none;
        color: inherit;
        align-items: flex-start;
        padding: 0.35rem 0.5rem;
        margin: 0 -0.5rem;
        border-radius: 10px;
        transition: background 0.15s;
    }
    .bh-cat-sidebar__trending a:hover {
        background: rgba(255, 255, 255, 0.08);
    }
    .bh-cat-sidebar__trending a::before {
        content: counter(trend, decimal-leading-zero);
        font-family: 'Space Grotesk', sans-serif;
        font-size: 0.8rem;
        font-weight: 800;
        color: rgba(255, 255, 255, 0.25);
        min-width: 1.25rem;
        line-height: 1.4;
        transition: color 0.15s;
    }
    .bh-cat-sidebar__trending a:hover::before { color: #60a5fa; }
    .bh-cat-sidebar__trending img {
        width: 52px;
        height: 52px;
        object-fit: cover;
        border-radius: 10px;
        flex-shrink: 0;
        border: 2px solid rgba(255, 255, 255, 0.15);
    }
    .bh-cat-sidebar__trend-body {
        flex: 1;
        min-width: 0;
    }
    .bh-cat-sidebar__trend-title {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        line-height: 1.4;
        color: rgba(255, 255, 255, 0.92);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        transition: color 0.15s;
    }
    .bh-cat-sidebar__trending a:hover .bh-cat-sidebar__trend-title {
        color: #fff;
    }
    .bh-cat-sidebar__trend-meta {
        display: block;
        font-size: 0.72rem;
        color: rgba(255, 255, 255, 0.5);
        margin-top: 0.25rem;
    }
    @media (max-width: 1024px) {
        .bh-cat-sidebar__inner {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .bh-cat-sidebar__widget { margin-bottom: 0; }
    }
    @media (max-width: 640px) {
        .bh-cat-sidebar__inner { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
<div class="bh">
    {{-- HERO BANNER --}}
    <section class="bh-hero">
        <div class="bh-hero__bg" aria-hidden="true">
            <div class="bh-hero__grid"></div>
            <div class="bh-hero__aurora bh-hero__aurora--1"></div>
            <div class="bh-hero__aurora bh-hero__aurora--2"></div>
            <div class="bh-hero__aurora bh-hero__aurora--3"></div>
            <div class="bh-hero__noise"></div>
        </div>

        <div class="bh-hero__content">
            <div>
                <div class="bh-hero__label">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 12h10"/></svg>
                    Digital Magazine
                </div>
                <h1 class="bh-hero__title">
                    Discover Stories That <span class="highlight">Inspire</span> You
                </h1>
                <p class="bh-hero__subtitle">
                    Curated guides, reviews and insights from {{ config('app.name') }}. Explore by topic or search what matters to you.
                </p>

                <form class="bh-hero__search" action="{{ route('home') }}" method="get">
                    <input type="search" name="q" value="{{ $searchQuery }}" placeholder="Search articles, guides..." aria-label="Search">
                    <button type="submit">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Search
                    </button>
                </form>

                <div class="bh-hero__cats">
                    <a href="{{ route('home') }}" class="bh-hero__cat {{ !request('cat') ? 'active' : '' }}">All</a>
                    @foreach($featuredCategories->take(6) as $cat)
                        <a href="{{ $cat['url'] }}" class="bh-hero__cat {{ request('cat') === $cat['name'] ? 'active' : '' }}">
                            {{ $cat['name'] }}
                        </a>
                    @endforeach
                </div>

                <div class="bh-hero__stats">
                    <div class="bh-hero__stat">
                        <strong>{{ number_format($stats['posts']) }}</strong>
                        <span>Articles</span>
                    </div>
                    <div class="bh-hero__stat">
                        <strong>{{ number_format($stats['categories']) }}</strong>
                        <span>Topics</span>
                    </div>
                </div>
            </div>

            <div class="bh-hero__cards">
                @if($featuredPost)
                <div class="bh-hero__card">
                    <img src="{{ $featuredPost->featured_image_url ?? 'https://picsum.photos/seed/' . $featuredPost->id . '/400/300' }}" alt="" class="bh-hero__card-img">
                    @include('partials.blog-category-tags', ['post' => $featuredPost, 'variant' => 'overlay', 'compact' => true])
                    <div class="bh-hero__card-body">
                        <div class="bh-hero__card-title">{{ $featuredPost->title }}</div>
                    </div>
                </div>
                @endif
                @foreach($latestPosts->take(2) as $post)
                <div class="bh-hero__card">
                    <img src="{{ $post->featured_image_url ?? 'https://picsum.photos/seed/' . $post->id . '/400/300' }}" alt="" class="bh-hero__card-img">
                    @include('partials.blog-category-tags', ['post' => $post, 'variant' => 'overlay', 'compact' => true])
                    <div class="bh-hero__card-body">
                        <div class="bh-hero__card-title">{{ $post->title }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    @if($isFiltered)
        {{-- FILTERED RESULTS --}}
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
                    <a href="{{ route('home') }}">Clear filters ×</a>
                </div>

                @if($filteredPosts->isNotEmpty())
                    <div class="bh-grid">
                        @foreach($filteredPosts as $post)
                        <article class="bh-card">
                            <a href="{{ route('blog.show', $post->slug) }}" class="bh-card__media">
                                <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="bh-card__img" loading="lazy" decoding="async">
                                @include('partials.blog-category-tags', ['post' => $post, 'variant' => 'overlay'])
                            </a>
                            <div class="bh-card__body">
                                @if(!$post->featured_image_url)
                                    @include('partials.blog-category-tags', ['post' => $post, 'variant' => 'inline'])
                                @endif
                                <h3 class="bh-card__title">
                                    <a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a>
                                </h3>
                                @if($post->excerpt)
                                    <p class="bh-card__excerpt">{{ $post->excerpt }}</p>
                                @endif
                                <div class="bh-card__meta">
                                    <span>{{ $post->created_at?->format('M j, Y') }} · {{ $post->reading_minutes }} min</span>
                                    <span class="bh-card__read">Read →</span>
                                </div>
                            </div>
                        </article>
                        @endforeach
                    </div>
                @else
                    <div class="bh-empty">
                        <h2>No articles found</h2>
                        <p>Try a different keyword or browse topics below.</p>
                    </div>
                @endif
            </div>
        </section>
    @else
        {{-- FEATURED --}}
        @if($featuredPost || (isset($heroRotationPosts) && $heroRotationPosts->isNotEmpty()))
        @php
            $carouselPosts = collect([$featuredPost])->merge($heroRotationPosts ?? collect())->filter();
        @endphp
        <section class="bh-section">
            <div class="bh-wrap">
                <div class="bh-section__header">
                    <h2>Featured Story</h2>
                </div>
                <div class="bh-featured">
                    {{-- Left: Carousel --}}
                    <div class="bh-featured__carousel" id="featured-carousel">
                        <div class="bh-featured__carousel-track">
                            @foreach($carouselPosts as $index => $post)
                            <div class="bh-featured__carousel-slide {{ $index === 0 ? 'active' : '' }}" data-index="{{ $index }}">
                                <a href="{{ route('blog.show', $post->slug) }}" class="bh-featured__carousel-slide-link">
                                    <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" loading="{{ $index === 0 ? 'eager' : 'lazy' }}" decoding="async" @if($index === 0) fetchpriority="high" @endif>
                                    @include('partials.blog-category-tags', ['post' => $post, 'variant' => 'overlay'])
                                    <div class="bh-featured__carousel-body">
                                        <h3 class="bh-card__title">{{ $post->title }}</h3>
                                        @if($post->excerpt)
                                            <p class="bh-card__excerpt">{{ $post->excerpt }}</p>
                                        @endif
                                        <div class="bh-card__meta">
                                            <span>{{ $post->created_at?->format('F j, Y') }} · {{ $post->reading_minutes }} min</span>
                                            <span class="bh-card__read">Read →</span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            @endforeach
                        </div>

                        @if($carouselPosts->count() > 1)
                        <div class="bh-featured__carousel-arrows">
                            <button class="bh-featured__carousel-arrow" id="carousel-prev" aria-label="Previous">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <button class="bh-featured__carousel-arrow" id="carousel-next" aria-label="Next">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                        <div class="bh-featured__carousel-dots">
                            @foreach($carouselPosts as $index => $post)
                            <button class="bh-featured__carousel-dot {{ $index === 0 ? 'active' : '' }}" data-index="{{ $index }}" aria-label="Go to slide {{ $index + 1 }}"></button>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    {{-- Right: Sidebar --}}
                    <div class="bh-featured__sidebar">
                        @foreach($carouselPosts->skip(1)->take(4) as $post)
                        <a href="{{ route('blog.show', $post->slug) }}" class="bh-featured__sidebar-item">
                            <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" loading="lazy">
                            @include('partials.blog-category-tags', ['post' => $post, 'variant' => 'overlay', 'compact' => true])
                            <div class="bh-featured__sidebar-item-body">
                                <h4>{{ $post->title }}</h4>
                                <span>{{ $post->created_at?->format('M j, Y') }} · {{ $post->reading_minutes }} min</span>
                            </div>
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
        @endif

        {{-- LATEST + CATEGORY POSTS — single sticky sidebar --}}
        @php
            $showCategoryZone = $latestPosts->isNotEmpty()
                || (isset($categoryPosts) && $categoryPosts->isNotEmpty())
                || $trendingPosts->isNotEmpty()
                || $featuredCategories->contains(fn ($cat) => ($cat['count'] ?? 0) > 0);
        @endphp
        @if($showCategoryZone)
            @php
                $layouts = ['magazine', 'list', 'masonry', 'scroll', 'zigzag'];
            @endphp
            <div class="bh-cat-zone bh-section--alt">
                <div class="bh-wrap">
                    <div class="bh-cat-page-layout">
                        <div class="bh-cat-page-layout__main">
                            @if($latestPosts->isNotEmpty())
                            <div class="bh-cat-block" id="latest-articles">
                                <div class="bh-section__header">
                                    <h2>Latest Articles</h2>
                                    <a href="{{ route('blog.index') }}">View all →</a>
                                </div>
                                <div class="bh-grid bh-grid--carousel">
                                    @foreach($latestPosts->take(6) as $post)
                                    <article class="bh-card">
                                        <a href="{{ route('blog.show', $post->slug) }}" class="bh-card__media">
                                            <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="bh-card__img" loading="lazy" decoding="async">
                                            @include('partials.blog-category-tags', ['post' => $post, 'variant' => 'overlay'])
                                        </a>
                                        <div class="bh-card__body">
                                            @if(!$post->featured_image_url)
                                                @include('partials.blog-category-tags', ['post' => $post, 'variant' => 'inline'])
                                            @endif
                                            <h3 class="bh-card__title">
                                                <a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a>
                                            </h3>
                                            @if($post->excerpt)
                                                <p class="bh-card__excerpt">{{ $post->excerpt }}</p>
                                            @endif
                                            <div class="bh-card__meta">
                                                <span>{{ $post->created_at?->format('M j, Y') }} · {{ $post->reading_minutes }} min</span>
                                                <span class="bh-card__read">Read →</span>
                                            </div>
                                        </div>
                                    </article>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            @if(isset($categoryPosts))
                            @foreach($categoryPosts as $catIndex => $cat)
                            @php
                                $layout = $layouts[$catIndex % count($layouts)];
                                $posts = $cat['posts'];
                            @endphp
                            <div class="bh-cat-block {{ $loop->even ? 'bh-cat-block--alt' : '' }}" id="cat-{{ $cat['slug'] }}">
                                <div class="bh-section__header">
                                    <h2>{{ $cat['name'] }}</h2>
                                    <a href="{{ $cat['url'] }}">More →</a>
                                </div>

                                @if($layout === 'magazine')
                                <div class="bh-cat-magazine">
                                    @foreach($posts->take(5) as $post)
                                    <article class="bh-card">
                                        <a href="{{ route('blog.show', $post->slug) }}" class="bh-card__media">
                                            <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="bh-card__img" loading="lazy" decoding="async">
                                            @include('partials.blog-category-tags', ['post' => $post, 'variant' => 'overlay'])
                                        </a>
                                        <div class="bh-card__body">
                                            @if(!$post->featured_image_url)
                                                @include('partials.blog-category-tags', ['post' => $post, 'variant' => 'inline'])
                                            @endif
                                            <h3 class="bh-card__title">
                                                <a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a>
                                            </h3>
                                            @if($post->excerpt)
                                                <p class="bh-card__excerpt">{{ $post->excerpt }}</p>
                                            @endif
                                            <div class="bh-card__meta">
                                                <span>{{ $post->created_at?->format('M j, Y') }} · {{ $post->reading_minutes }} min</span>
                                                <span class="bh-card__read">Read →</span>
                                            </div>
                                        </div>
                                    </article>
                                    @endforeach
                                </div>

                                @elseif($layout === 'list')
                                @php $featured = $posts->first(); @endphp
                                <div class="bh-cat-list">
                                    <a href="{{ route('blog.show', $featured->slug) }}" class="bh-cat-list__featured">
                                        <img src="{{ $featured->featured_image_url }}" alt="{{ $featured->title }}">
                                        @include('partials.blog-category-tags', ['post' => $featured, 'variant' => 'overlay'])
                                        <div class="bh-cat-list__featured-overlay">
                                            <h3 class="bh-card__title">{{ $featured->title }}</h3>
                                            <div class="bh-card__meta">
                                                <span>{{ $featured->created_at?->format('F j, Y') }} · {{ $featured->reading_minutes }} min read</span>
                                            </div>
                                        </div>
                                    </a>
                                    <div class="bh-cat-list__items">
                                        @foreach($posts->skip(1)->take(4) as $post)
                                        <a href="{{ route('blog.show', $post->slug) }}" class="bh-cat-list__item">
                                            <span class="bh-cat-list__item-thumb">
                                                <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}">
                                                @include('partials.blog-category-tags', ['post' => $post, 'variant' => 'overlay', 'compact' => true])
                                            </span>
                                            <div class="bh-cat-list__item-body">
                                                <h4>{{ $post->title }}</h4>
                                                <span>{{ $post->created_at?->format('M j, Y') }} · {{ $post->reading_minutes }} min</span>
                                            </div>
                                        </a>
                                        @endforeach
                                    </div>
                                </div>

                                @elseif($layout === 'masonry')
                                <div class="bh-cat-masonry">
                                    @foreach($posts->take(6) as $post)
                                    <article class="bh-card">
                                        <a href="{{ route('blog.show', $post->slug) }}" class="bh-card__media">
                                            <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="bh-card__img" loading="lazy" decoding="async">
                                            @include('partials.blog-category-tags', ['post' => $post, 'variant' => 'overlay'])
                                        </a>
                                        <div class="bh-card__body">
                                            @if(!$post->featured_image_url)
                                                @include('partials.blog-category-tags', ['post' => $post, 'variant' => 'inline'])
                                            @endif
                                            <h3 class="bh-card__title">
                                                <a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a>
                                            </h3>
                                            <div class="bh-card__meta">
                                                <span>{{ $post->created_at?->format('M j, Y') }}</span>
                                                <span class="bh-card__read">Read →</span>
                                            </div>
                                        </div>
                                    </article>
                                    @endforeach
                                </div>

                                @elseif($layout === 'scroll')
                                <div class="bh-cat-scroll">
                                    @foreach($posts->take(6) as $post)
                                    <article class="bh-card">
                                        <a href="{{ route('blog.show', $post->slug) }}" class="bh-card__media">
                                            <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="bh-card__img" loading="lazy" decoding="async">
                                            @include('partials.blog-category-tags', ['post' => $post, 'variant' => 'overlay'])
                                        </a>
                                        <div class="bh-card__body">
                                            @if(!$post->featured_image_url)
                                                @include('partials.blog-category-tags', ['post' => $post, 'variant' => 'inline'])
                                            @endif
                                            <h3 class="bh-card__title">
                                                <a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a>
                                            </h3>
                                            <div class="bh-card__meta">
                                                <span>{{ $post->created_at?->format('M j, Y') }}</span>
                                                <span class="bh-card__read">Read →</span>
                                            </div>
                                        </div>
                                    </article>
                                    @endforeach
                                </div>

                                @elseif($layout === 'zigzag')
                                <div class="bh-cat-zigzag">
                                    @foreach($posts->take(4) as $post)
                                    <a href="{{ route('blog.show', $post->slug) }}" class="bh-cat-zigzag-item">
                                        <span class="bh-cat-zigzag-item__media">
                                            <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" loading="lazy">
                                            @include('partials.blog-category-tags', ['post' => $post, 'variant' => 'overlay'])
                                        </span>
                                        <div class="bh-cat-zigzag-item-body">
                                            <h3>{{ $post->title }}</h3>
                                            @if($post->excerpt)
                                                <p>{{ $post->excerpt }}</p>
                                            @endif
                                            <div class="bh-card__meta">
                                                <span>{{ $post->created_at?->format('F j, Y') }} · {{ $post->reading_minutes }} min read</span>
                                                <span class="bh-card__read">Read article →</span>
                                            </div>
                                        </div>
                                    </a>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                            @endforeach
                            @endif
                        </div>

                        @include('partials.home-category-sidebar')
                    </div>
                </div>
            </div>
        @endif

        {{-- EMPTY --}}
        @if(!$featuredPost && $latestPosts->isEmpty() && $trendingPosts->isEmpty())
        <section class="bh-section">
            <div class="bh-wrap">
                <div class="bh-empty">
                    <h2>No articles yet</h2>
                    <p>New stories will appear here once published. Check back soon!</p>
                </div>
            </div>
        </section>
        @endif

        {{-- CTA --}}
        <div class="bh-wrap">
            <div class="bh-cta">
                <div class="bh-cta__inner">
                    <h2>Never miss a story</h2>
                    <p>Get the latest articles, guides and insights delivered straight to your inbox.</p>
                    <a href="{{ route('blog.index') }}">Explore all articles →</a>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- Carousel Script --}}
<script>
(function() {
    var carousel = document.getElementById('featured-carousel');
    if (!carousel) return;

    var slides = carousel.querySelectorAll('.bh-featured__carousel-slide');
    var dots = carousel.querySelectorAll('.bh-featured__carousel-dot');
    var prevBtn = carousel.querySelector('#carousel-prev');
    var nextBtn = carousel.querySelector('#carousel-next');

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

    if (prevBtn) prevBtn.addEventListener('click', function() {
        clearInterval(timer);
        prev();
        startTimer();
    });

    if (nextBtn) nextBtn.addEventListener('click', function() {
        clearInterval(timer);
        next();
        startTimer();
    });

    // Touch/Swipe support
    var touchStartX = 0;
    var touchEndX = 0;

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

(function() {
    var blocks = document.querySelectorAll('.bh-cat-page-layout__main [id^="cat-"]');
    var links = document.querySelectorAll('[data-cat-anchor]');
    if (!blocks.length || !links.length) return;

    function setActive(slug) {
        links.forEach(function(link) {
            var isActive = link.getAttribute('data-cat-anchor') === slug;
            link.classList.toggle('is-current', isActive);
        });
    }

    function onScroll() {
        var offset = window.scrollY + 120;
        var current = null;
        blocks.forEach(function(block) {
            if (block.offsetTop <= offset) {
                current = block.id.replace('cat-', '');
            }
        });
        if (current) setActive(current);
    }

    links.forEach(function(link) {
        link.addEventListener('click', function(e) {
            var slug = link.getAttribute('data-cat-anchor');
            var target = document.getElementById('cat-' + slug);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                setActive(slug);
            }
        });
    });

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
})();
</script>
@endsection
