@extends('layouts.app')

@section('title', 'Terms of Use - ' . config('app.name'))
@section('description', 'Terms of use for our website and blog.')

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
    .legal-container h2 { font-size: 1.2rem; font-weight: 600; margin-top: 2rem; margin-bottom: 0.75rem; }
    .legal-container p { margin-bottom: 1rem; color: var(--text); }
    .legal-container ul { margin: 0.75rem 0 1.5rem; padding-left: 1.5rem; }
    .legal-container li { margin-bottom: 0.4rem; }
</style>
@endpush

@section('content')
<div class="legal-container">
    <h1 class="font-heading">Terms of Use</h1>
    <p class="updated">Last updated: {{ date('F d, Y') }}</p>

    <p>Welcome to <strong>{{ config('app.name') }}</strong>. By using this website, you agree to these Terms of Use.</p>

    <h2>Use of the Website</h2>
    <p>This site provides blog articles and related content for personal, non-commercial use. You may not scrape, copy, or redistribute our content for commercial purposes without permission.</p>

    <h2>Content Accuracy</h2>
    <p>We strive to publish accurate and helpful information. Articles may be updated or removed as topics evolve. We do not guarantee that every detail remains current at all times.</p>

    <h2>Third-Party Links</h2>
    <p>Articles may link to external websites. We are not responsible for their content, policies, or practices. Your use of third-party sites is at your own risk.</p>

    <h2>Limitation of Liability</h2>
    <p>We provide this site “as is.” We are not liable for any loss or damage arising from your use of our site or reliance on published content.</p>

    <h2>Changes</h2>
    <p>We may update these Terms from time to time. Continued use of the site after changes means you accept the updated Terms.</p>

    <h2>Contact</h2>
    <p>Questions about these Terms? Please use our <a href="{{ url('/contact') }}">Contact</a> page.</p>
</div>
@endsection
