@extends('layouts.app')

@section('title', 'Cookie Policy - ' . config('app.name'))
@section('description', 'How we use cookies and similar technologies on our website.')

@push('styles')
<style>
    .legal-container { max-width: 800px; margin: 0 auto; padding: 3rem 1.5rem; }
    .legal-container h1 {
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(1.75rem, 4vw, 2.25rem);
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    .legal-container .updated { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem; }
    .legal-container h2 {
        font-size: 1.2rem;
        font-weight: 600;
        margin-top: 2rem;
        margin-bottom: 0.75rem;
    }
    .legal-container p { margin-bottom: 1rem; color: var(--text); }
    .legal-container p:last-child { margin-bottom: 0; }
    .legal-container a { color: var(--primary); }
</style>
@endpush

@section('content')
<div class="legal-container">
    @php
        $content = \App\Models\SiteContent::get('page_cookie', \App\Models\SiteContent::defaultPageCookiePolicy());
        $content = str_replace('[COOKIE_DATE]', date('F d, Y'), $content);
        $content = str_replace('[SITE_NAME]', config('app.name'), $content);
    @endphp
    {!! $content !!}
</div>
@endsection
