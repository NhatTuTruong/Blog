@extends('layouts.app')

@section('title', \App\Support\SiteSeo::pageTitle('categories'))
@section('description', \App\Support\SiteSeo::pageDescription('categories'))
@section('canonical', route('categories.index'))

@push('styles')
<style>
    .cat-page {
        background: #fff;
        color: #0f172a;
    }
    .cat-page__hero {
        background: #fff;
        border-bottom: 2px solid rgba(255, 143, 0, 0.2);
        padding: 2.25rem 1.5rem;
    }
    .cat-page__hero-inner {
        position: relative;
        max-width: 1280px;
        margin: 0 auto;
        background: #fff;
        border: 1px solid rgba(255, 143, 0, 0.22);
        border-left: 4px solid #FF8F00;
        border-radius: 16px;
        padding: 2rem 2.25rem;
        box-shadow: 0 8px 32px rgba(255, 143, 0, 0.07);
        overflow: hidden;
    }
    .cat-page__hero-inner::before {
        content: '';
        position: absolute;
        top: -30%;
        right: -5%;
        width: 220px;
        height: 220px;
        background: radial-gradient(circle, rgba(255, 143, 0, 0.07) 0%, transparent 70%);
        pointer-events: none;
    }
    .cat-page__label {
        position: relative;
        z-index: 1;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #EF6C00;
        background: rgba(255, 143, 0, 0.08);
        border: 1px solid rgba(255, 143, 0, 0.35);
        border-radius: 999px;
        padding: 0.38rem 0.9rem;
        margin-bottom: 1rem;
    }
    .cat-page__label::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #FF8F00;
        box-shadow: 0 0 0 3px rgba(255, 143, 0, 0.2);
    }
    .cat-page__title {
        position: relative;
        z-index: 1;
        font-family: 'Poppins', sans-serif;
        font-size: clamp(1.85rem, 4vw, 2.75rem);
        font-weight: 700;
        line-height: 1.12;
        letter-spacing: -0.025em;
        margin: 0 0 0.65rem;
        color: #1a2332;
    }
    .cat-page__subtitle {
        position: relative;
        z-index: 1;
        margin: 0;
        max-width: 40rem;
        color: #64748b;
        font-size: 1.02rem;
        line-height: 1.65;
    }
    @media (max-width: 640px) {
        .cat-page__hero { padding: 1.5rem 1rem; }
        .cat-page__hero-inner { padding: 1.5rem 1.25rem; border-radius: 12px; }
    }
    .cat-page__body {
        max-width: 1280px;
        margin: 0 auto;
        padding: 2rem 1.5rem 4rem;
    }
    .cat-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 1rem;
    }
    @media (max-width: 1280px) {
        .cat-grid { grid-template-columns: repeat(4, 1fr); }
    }
    @media (max-width: 900px) {
        .cat-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 600px) {
        .cat-grid { grid-template-columns: repeat(2, 1fr); }
    }
    .cat-card {
        display: flex;
        flex-direction: column;
        text-decoration: none;
        color: inherit;
        background: #fff;
        border: 1px solid rgba(129, 128, 127, 0.18);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(15, 23, 42, 0.04);
        transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
    }
    .cat-card:hover {
        transform: translateY(-3px);
        border-color: rgba(255, 143, 0, 0.45);
        box-shadow: 0 10px 24px rgba(255, 143, 0, 0.12);
    }
    .cat-card__image {
        aspect-ratio: 4 / 3;
        overflow: hidden;
        background: #f8fafc;
        border-bottom: 1px solid rgba(255, 143, 0, 0.1);
    }
    .cat-card__image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform 0.35s ease;
    }
    .cat-card:hover .cat-card__image img {
        transform: scale(1.05);
    }
    .cat-card__body {
        padding: 0.75rem 0.85rem 0.9rem;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    .cat-card__name {
        font-family: 'Poppins', sans-serif;
        font-size: 0.82rem;
        font-weight: 600;
        line-height: 1.35;
        color: #1a2332;
        margin: 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .cat-card__count {
        font-size: 0.72rem;
        font-weight: 500;
        color: #8a8a8a;
    }
    .cat-page__empty {
        text-align: center;
        padding: 3rem 1rem;
        color: #64748b;
        border: 1px dashed rgba(255, 143, 0, 0.25);
        border-radius: 14px;
        background: #FFFBF5;
    }
</style>
@endpush

@section('content')
<div class="cat-page">
    <section class="cat-page__hero">
        <div class="cat-page__hero-inner">
            <h1 class="cat-page__title">Categories</h1>
            <p class="cat-page__subtitle">Explore all review topics and find articles by category.</p>
        </div>
    </section>

    <div class="cat-page__body">
        @if($categories->isEmpty())
            <div class="cat-page__empty">
                <p>No categories available yet.</p>
            </div>
        @else
            <div class="cat-grid">
                @foreach($categories as $category)
                <a href="{{ $category['url'] }}" class="cat-card">
                    <div class="cat-card__image">
                        <img src="{{ $category['image'] }}" alt="{{ $category['name'] }}" loading="lazy" decoding="async">
                    </div>
                    <div class="cat-card__body">
                        <h2 class="cat-card__name">{{ $category['name'] }}</h2>
                        <span class="cat-card__count">{{ number_format($category['count']) }} {{ $category['count'] === 1 ? 'article' : 'articles' }}</span>
                    </div>
                </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
