@extends('layouts.app')

@section('title', \App\Support\SiteSeo::pageTitle('contact'))
@section('description', \App\Support\SiteSeo::pageDescription('contact'))

@push('styles')
<style>
    .legal-container { max-width: 800px; margin: 0 auto; padding: 3rem 1.5rem; }
    .legal-container h1 {
        font-family: 'Space Grotesk', sans-serif;
        font-size: clamp(1.75rem, 4vw, 2.25rem);
        font-weight: 700;
        margin-bottom: 1.5rem;
    }
    .legal-container p { margin-bottom: 1rem; color: var(--text); }
    .legal-container p:last-child { margin-bottom: 0; }
    .legal-container a { color: var(--accent); text-decoration: none; }
    .legal-container a:hover { text-decoration: underline; }
</style>
@endpush

@section('content')
<div class="legal-container">
    @php
        $content = \App\Models\SiteContent::get('page_contact', \App\Models\SiteContent::defaultPageContact());
        $contactEmail = \App\Support\AdminSettings::get('site_contact_email', config('mail.from.address', 'contact@reviewshays.com'));
        $content = str_replace('[SITE_EMAIL]', (string) $contactEmail, $content);
    @endphp
    {!! $content !!}
</div>
@endsection
