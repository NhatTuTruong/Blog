@php
    $canonicalUrl = trim($__env->yieldContent('canonical', url()->current()));
    $pageDescription = trim($__env->yieldContent('description', $defaultMetaDescription ?? ''));
@endphp
<link rel="canonical" href="{{ $canonicalUrl }}">
<meta name="description" content="{{ $pageDescription }}">
@if(filled($robotsMeta ?? null))
<meta name="robots" content="{{ $robotsMeta }}">
@endif
@if(filled($googleSiteVerification ?? null))
<meta name="google-site-verification" content="{{ $googleSiteVerification }}">
@endif
<meta name="theme-color" content="#2563eb">
