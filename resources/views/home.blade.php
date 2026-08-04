@extends('layouts.app')

@section('title', config('app.name') . ' — Blog & Articles')
@section('description', 'Discover guides, stories and trending articles. Search by topic or browse featured categories.')

@push('styles')
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
        position: relative;
        min-height: 70vh;
        display: flex;
        align-items: center;
        overflow: hidden;
        background: #0f172a;
    }
    .bh-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 80% 60% at 20% 20%, rgba(37, 99, 235, 0.4) 0%, transparent 50%),
            radial-gradient(ellipse 60% 50% at 80% 80%, rgba(139, 92, 246, 0.3) 0%, transparent 50%),
            radial-gradient(ellipse 40% 40% at 50% 50%, rgba(59, 130, 246, 0.15) 0%, transparent 50%);
    }
    .bh-hero__particles {
        position: absolute;
        inset: 0;
        overflow: hidden;
    }
    .bh-hero__particle {
        position: absolute;
        width: 4px;
        height: 4px;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        animation: particle-float 20s linear infinite;
    }
    @keyframes particle-float {
        0% { transform: translateY(100vh) scale(0); opacity: 0; }
        10% { opacity: 1; }
        90% { opacity: 1; }
        100% { transform: translateY(-10vh) scale(1); opacity: 0; }
    }
    .bh-hero__content {
        position: relative;
        z-index: 2;
        width: 100%;
        max-width: 1280px;
        margin: 0 auto;
        padding: 4rem 2rem;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
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
        padding: 0.5rem 1rem;
        background: rgba(37, 99, 235, 0.2);
        border: 1px solid rgba(37, 99, 235, 0.4);
        border-radius: 999px;
        color: #60a5fa;
        font-size: 0.8rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
    }
    .bh-hero__label svg {
        width: 14px;
        height: 14px;
    }
    .bh-hero__title {
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(2.5rem, 5vw, 4rem);
        font-weight: 800;
        line-height: 1.1;
        letter-spacing: -0.03em;
        color: #fff;
        margin: 0 0 1.25rem;
    }
    .bh-hero__title .highlight {
        background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 50%, #60a5fa 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        background-size: 200% 200%;
        animation: gradient-shift 4s ease infinite;
    }
    @keyframes gradient-shift {
        0%, 100% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
    }
    .bh-hero__subtitle {
        font-size: 1.15rem;
        color: rgba(255, 255, 255, 0.65);
        line-height: 1.7;
        margin: 0 0 2rem;
        max-width: 480px;
    }
    @media (max-width: 900px) {
        .bh-hero__subtitle { margin: 0 auto 2rem; }
    }
    .bh-hero__search {
        display: flex;
        max-width: 480px;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 14px;
        overflow: hidden;
        backdrop-filter: blur(12px);
    }
    @media (max-width: 900px) {
        .bh-hero__search { margin: 0 auto; }
    }
    .bh-hero__search input {
        flex: 1;
        border: none;
        background: transparent;
        padding: 1rem 1.25rem;
        font-size: 0.95rem;
        color: #fff;
        outline: none;
    }
    .bh-hero__search input::placeholder {
        color: rgba(255, 255, 255, 0.45);
    }
    .bh-hero__search button {
        border: none;
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: #fff;
        padding: 0.875rem 1.5rem;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s;
    }
    .bh-hero__search button:hover {
        background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    }
    .bh-hero__search button svg {
        width: 18px;
        height: 18px;
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
        padding: 0.5rem 1rem;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 999px;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 500;
        transition: all 0.25s;
    }
    .bh-hero__cat:hover,
    .bh-hero__cat.active {
        background: rgba(255, 255, 255, 0.12);
        border-color: rgba(37, 99, 235, 0.5);
        color: #fff;
        transform: translateY(-2px);
    }
    .bh-hero__stats {
        display: flex;
        gap: 2rem;
        margin-top: 2rem;
    }
    @media (max-width: 900px) {
        .bh-hero__stats { justify-content: center; }
    }
    .bh-hero__stat strong {
        display: block;
        font-family: 'Space Grotesk', sans-serif;
        font-size: 1.75rem;
        font-weight: 800;
        color: #fff;
        line-height: 1;
    }
    .bh-hero__stat span {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.5);
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .bh-hero__cards {
        position: relative;
        height: 380px;
    }
    @media (max-width: 900px) {
        .bh-hero__cards { display: none; }
    }
    .bh-hero__card {
        position: absolute;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        backdrop-filter: blur(12px);
        overflow: hidden;
        transition: transform 0.4s ease;
    }
    .bh-hero__card:nth-child(1) {
        width: 220px;
        height: 280px;
        top: 0;
        right: 60px;
        animation: card-float 6s ease-in-out infinite;
    }
    .bh-hero__card:nth-child(2) {
        width: 190px;
        height: 240px;
        bottom: 0;
        right: 20px;
        animation: card-float 6s ease-in-out infinite 1s;
    }
    .bh-hero__card:nth-child(3) {
        width: 170px;
        height: 200px;
        top: 30px;
        right: 0;
        animation: card-float 6s ease-in-out infinite 2s;
    }
    @keyframes card-float {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        50% { transform: translateY(-15px) rotate(1deg); }
    }
    .bh-hero__card-img {
        width: 100%;
        height: 130px;
        object-fit: cover;
    }
    .bh-hero__card:nth-child(2) .bh-hero__card-img { height: 110px; }
    .bh-hero__card:nth-child(3) .bh-hero__card-img { height: 90px; }
    .bh-hero__card-body {
        padding: 0.75rem;
    }
    .bh-hero__card-cat {
        font-size: 0.65rem;
        font-weight: 700;
        color: #60a5fa;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.25rem;
    }
    .bh-hero__card-title {
        font-size: 0.8rem;
        font-weight: 600;
        color: #fff;
        line-height: 1.3;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Categories Pills */
    .bh-hero__cats {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.75rem;
        animation: bh-fadeInUp 0.8s ease-out 0.4s both;
    }
    .bh-hero__cat {
        padding: 0.6rem 1.25rem;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 999px;
        color: rgba(255, 255, 255, 0.9);
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.3s;
    }
    .bh-hero__cat:hover,
    .bh-hero__cat.active {
        background: rgba(255, 255, 255, 0.95);
        color: var(--bh-ink);
        border-color: transparent;
        transform: translateY(-2px);
    }

    /* Stats */
    .bh-hero__stats {
        display: flex;
        justify-content: center;
        gap: 3rem;
        margin-top: 3rem;
        animation: bh-fadeInUp 0.8s ease-out 0.5s both;
    }
    .bh-hero__stat {
        text-align: center;
        color: #fff;
    }
    .bh-hero__stat strong {
        display: block;
        font-family: 'Space Grotesk', sans-serif;
        font-size: 2.5rem;
        font-weight: 800;
        letter-spacing: -0.03em;
        background: linear-gradient(135deg, #fff 0%, #93c5fd 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .bh-hero__stat span {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.6);
        text-transform: uppercase;
        letter-spacing: 0.1em;
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
    .bh-card__tag {
        position: absolute;
        top: 1rem;
        left: 1rem;
        background: var(--bh-accent);
        color: #fff;
        padding: 0.35rem 0.75rem;
        font-size: 0.7rem;
        font-weight: 700;
        border-radius: 8px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .bh-card__body {
        padding: 1.5rem;
    }
    .bh-card__cat {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--bh-accent);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 0.5rem;
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
        display: flex;
        flex-direction: column;
    }
    .bh-featured__carousel-slide.active {
        opacity: 1;
        pointer-events: auto;
    }
    .bh-featured__carousel-slide img {
        width: 100%;
        flex: 1;
        object-fit: cover;
        min-height: 350px;
    }
    .bh-featured__carousel-body {
        padding: 1.5rem;
        background: var(--bh-card);
    }
    .bh-featured__carousel-body .bh-card__title {
        font-size: 1.5rem;
        -webkit-line-clamp: 2;
    }
    .bh-featured__carousel-body .bh-card__excerpt {
        -webkit-line-clamp: 3;
    }
    .bh-featured__carousel-dots {
        position: absolute;
        bottom: 1rem;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 0.5rem;
        z-index: 10;
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

    /* Right Sidebar - Equal Height */
    .bh-featured__sidebar {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        height: 100%;
    }
    .bh-featured__sidebar-item {
        display: flex;
        gap: 1rem;
        padding: 1rem;
        background: var(--bh-card);
        border-radius: 14px;
        border: 1px solid var(--bh-border);
        text-decoration: none;
        color: inherit;
        transition: all 0.3s;
        flex: 1;
        min-height: 0;
    }
    .bh-featured__sidebar-item:hover {
        border-color: var(--bh-accent);
        transform: translateX(4px);
        box-shadow: 0 4px 20px rgba(37, 99, 235, 0.1);
    }
    .bh-featured__sidebar-item img {
        width: 100px;
        height: auto;
        object-fit: cover;
        border-radius: 10px;
        flex-shrink: 0;
        align-self: center;
    }
    .bh-featured__sidebar-item-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-width: 0;
    }
    .bh-featured__sidebar-item h4 {
        font-size: 0.95rem;
        font-weight: 600;
        line-height: 1.4;
        margin: 0 0 0.5rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .bh-featured__sidebar-item span {
        font-size: 0.75rem;
        color: var(--bh-muted);
    }

    /* Mobile Carousel */
    @media (max-width: 900px) {
        .bh-featured__carousel {
            min-height: 400px;
        }
        .bh-featured__carousel-track {
            min-height: 400px;
        }
        .bh-featured__carousel-slide img {
            min-height: 280px;
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
    .bh-cat-list__item img {
        width: 100px;
        height: 80px;
        object-fit: cover;
        border-radius: 10px;
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
    .bh-cat-zigzag-item img {
        width: 100%;
        aspect-ratio: 16/10;
        object-fit: cover;
        border-radius: 14px;
        transition: transform 0.5s;
    }
    .bh-cat-zigzag-item:hover img { transform: scale(1.03); }
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
</style>
@endpush

@section('content')
<div class="bh">
    {{-- HERO BANNER --}}
    <section class="bh-hero">
        <div class="bh-hero__particles">
            <div class="bh-hero__particle" style="left: 10%; animation-delay: 0s;"></div>
            <div class="bh-hero__particle" style="left: 20%; animation-delay: 2s;"></div>
            <div class="bh-hero__particle" style="left: 35%; animation-delay: 4s;"></div>
            <div class="bh-hero__particle" style="left: 50%; animation-delay: 1s;"></div>
            <div class="bh-hero__particle" style="left: 65%; animation-delay: 3s;"></div>
            <div class="bh-hero__particle" style="left: 80%; animation-delay: 5s;"></div>
            <div class="bh-hero__particle" style="left: 90%; animation-delay: 2.5s;"></div>
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
                    <div class="bh-hero__card-body">
                        @if($featuredPost->category)
                        <div class="bh-hero__card-cat">{{ $featuredPost->category }}</div>
                        @endif
                        <div class="bh-hero__card-title">{{ $featuredPost->title }}</div>
                    </div>
                </div>
                @endif
                @foreach($latestPosts->take(2) as $post)
                <div class="bh-hero__card">
                    <img src="{{ $post->featured_image_url ?? 'https://picsum.photos/seed/' . $post->id . '/400/300' }}" alt="" class="bh-hero__card-img">
                    <div class="bh-hero__card-body">
                        @if($post->category)
                        <div class="bh-hero__card-cat">{{ $post->category }}</div>
                        @endif
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
                                <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="bh-card__img" loading="lazy">
                            </a>
                            <div class="bh-card__body">
                                @if($post->category)
                                    <div class="bh-card__cat">{{ $post->category }}</div>
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
                                <a href="{{ route('blog.show', $post->slug) }}">
                                    <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" loading="{{ $index === 0 ? 'eager' : 'lazy' }}">
                                </a>
                                <div class="bh-featured__carousel-body">
                                    @if($post->category)
                                        <div class="bh-card__cat">{{ $post->category }}</div>
                                    @endif
                                    <h3 class="bh-card__title">
                                        <a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a>
                                    </h3>
                                    @if($post->excerpt)
                                        <p class="bh-card__excerpt">{{ $post->excerpt }}</p>
                                    @endif
                                    <div class="bh-card__meta">
                                        <span>{{ $post->created_at?->format('F j, Y') }} · {{ $post->reading_minutes }} min</span>
                                        <span class="bh-card__read">Read →</span>
                                    </div>
                                </div>
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
                            <div class="bh-featured__sidebar-item-body">
                                @if($post->category)
                                    <div class="bh-card__cat">{{ $post->category }}</div>
                                @endif
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

        {{-- LATEST --}}
        @if($latestPosts->isNotEmpty())
        <section class="bh-section bh-section--alt">
            <div class="bh-wrap">
                <div class="bh-section__header">
                    <h2>Latest Articles</h2>
                    <a href="{{ route('blog.index') }}">View all →</a>
                </div>
                <div class="bh-grid bh-grid--carousel">
                    @foreach($latestPosts->take(6) as $post)
                    <article class="bh-card">
                        <a href="{{ route('blog.show', $post->slug) }}" class="bh-card__media">
                            <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="bh-card__img" loading="lazy">
                            @if($post->category)
                                <span class="bh-card__tag">{{ $post->category }}</span>
                            @endif
                        </a>
                        <div class="bh-card__body">
                            @if(!$post->featured_image_url)
                                @if($post->category)
                                    <div class="bh-card__cat">{{ $post->category }}</div>
                                @endif
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
        </section>
        @endif

        {{-- CATEGORY POSTS --}}
        @if(isset($categoryPosts) && $categoryPosts->isNotEmpty())
            @php
                $layouts = ['magazine', 'list', 'masonry', 'scroll', 'zigzag'];
            @endphp
            @foreach($categoryPosts as $catIndex => $cat)
            @php
                $layout = $layouts[$catIndex % count($layouts)];
                $posts = $cat['posts'];
            @endphp
            <section class="bh-section {{ $loop->even ? 'bh-section--alt' : '' }}">
                <div class="bh-wrap">
                    <div class="bh-section__header">
                        <h2>{{ $cat['name'] }}</h2>
                        <a href="{{ $cat['url'] }}">More →</a>
                    </div>

                    @if($layout === 'magazine')
                        <div class="bh-cat-magazine">
                            @foreach($posts->take(5) as $post)
                            <article class="bh-card">
                                <a href="{{ route('blog.show', $post->slug) }}" class="bh-card__media">
                                    <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="bh-card__img" loading="lazy">
                                    @if($post->category)
                                        <span class="bh-card__tag">{{ $post->category }}</span>
                                    @endif
                                </a>
                                <div class="bh-card__body">
                                    @if(!$post->featured_image_url && $post->category)
                                        <div class="bh-card__cat">{{ $post->category }}</div>
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
                                <div class="bh-cat-list__featured-overlay">
                                    @if($featured->category)
                                        <div class="bh-card__cat">{{ $featured->category }}</div>
                                    @endif
                                    <h3 class="bh-card__title">{{ $featured->title }}</h3>
                                    <div class="bh-card__meta">
                                        <span>{{ $featured->created_at?->format('F j, Y') }} · {{ $featured->reading_minutes }} min read</span>
                                    </div>
                                </div>
                            </a>
                            <div class="bh-cat-list__items">
                                @foreach($posts->skip(1)->take(4) as $post)
                                <a href="{{ route('blog.show', $post->slug) }}" class="bh-cat-list__item">
                                    <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}">
                                    <div class="bh-cat-list__item-body">
                                        @if($post->category)
                                            <div class="bh-card__cat">{{ $post->category }}</div>
                                        @endif
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
                                    <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="bh-card__img" loading="lazy">
                                    @if($post->category)
                                        <span class="bh-card__tag">{{ $post->category }}</span>
                                    @endif
                                </a>
                                <div class="bh-card__body">
                                    @if(!$post->featured_image_url && $post->category)
                                        <div class="bh-card__cat">{{ $post->category }}</div>
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
                                    <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="bh-card__img" loading="lazy">
                                    @if($post->category)
                                        <span class="bh-card__tag">{{ $post->category }}</span>
                                    @endif
                                </a>
                                <div class="bh-card__body">
                                    @if(!$post->featured_image_url && $post->category)
                                        <div class="bh-card__cat">{{ $post->category }}</div>
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
                                <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" loading="lazy">
                                <div class="bh-cat-zigzag-item-body">
                                    @if($post->category)
                                        <div class="bh-card__cat">{{ $post->category }}</div>
                                    @endif
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
            </section>
            @endforeach
        @endif

        {{-- TRENDING --}}
        @if($trendingPosts->isNotEmpty())
        <section class="bh-section bh-section--alt">
            <div class="bh-wrap">
                <div class="bh-section__header">
                    <h2>Trending Now</h2>
                    <a href="{{ route('blog.index') }}">View all →</a>
                </div>
                <div class="bh-trending bh-trending--carousel">
                    @foreach($trendingPosts->take(4) as $index => $post)
                    <a href="{{ route('blog.show', $post->slug) }}" class="bh-trend">
                        <span class="bh-trend__num">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <img src="{{ $post->featured_image_url }}" alt="" class="bh-trend__img" loading="lazy">
                        <div class="bh-trend__body">
                            @if($post->category)
                                <div class="bh-trend__cat">{{ $post->category }}</div>
                            @endif
                            <p class="bh-trend__title">{{ $post->title }}</p>
                            <div class="bh-trend__meta">
                                {{ $post->created_at?->format('M j, Y') }}
                                @if($post->views_count > 0)
                                    · {{ number_format($post->views_count) }} views
                                @endif
                            </div>
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
        </section>
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
</script>
@endsection
