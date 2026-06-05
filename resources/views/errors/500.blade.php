@php
    $content = \App\Models\SiteContent::get('error_500', \App\Models\SiteContent::defaultErrorContent('500'));
@endphp
@extends('layouts.app')
@section('title', ($content['title'] ?? 'Lỗi máy chủ') . ' - ' . config('app.name'))
@push('styles')
<style>
    .error-page { text-align: center; padding: 4rem 1.5rem; max-width: 560px; margin: 0 auto; }
    .error-page .error-code { font-family: 'Space Grotesk', sans-serif; font-size: 6rem; font-weight: 700; line-height: 1; color: #dc2626; margin-bottom: 0.5rem; }
    .error-page .error-title { font-size: 1.5rem; font-weight: 600; color: var(--text); margin-bottom: 0.75rem; }
    .error-page .error-message { color: var(--text-muted); margin-bottom: 2rem; }
    .error-page .error-actions a { display: inline-flex; align-items: center; padding: 0.75rem 1.5rem; background: var(--primary); color: #fff; text-decoration: none; border-radius: var(--radius-sm); font-weight: 500; transition: background 0.2s; }
    .error-page .error-actions a:hover { background: var(--primary-dark); }
</style>
@endpush
@section('content')
    <div class="error-page">
        <div class="error-code">500</div>
        <h1 class="error-title">{{ $content['title'] ?? 'Lỗi máy chủ' }}</h1>
        <p class="error-message">{{ $content['message'] ?? 'Đã xảy ra lỗi. Chúng tôi đang khắc phục.' }}</p>
        <div class="error-actions">
            <a href="{{ url('/') }}">Back to home page</a>
        </div>
    </div>
@endsection
