@props([
    'variant' => 'header',
])

@php
    $name = \App\Support\SiteSeo::plainText((string) config('app.name'));
    $first = mb_substr($name, 0, 1, 'UTF-8');
    $rest = mb_substr($name, 1, null, 'UTF-8');
    $variantClass = $variant === 'footer' ? 'site-text-logo--footer' : 'site-text-logo--header';
@endphp

<a href="{{ url('/') }}" {{ $attributes->class(['site-text-logo', 'font-heading', $variantClass]) }}>
    <span class="site-text-logo__accent" aria-hidden="true"></span>
    <span class="site-text-logo__text">
        @if ($first !== '')
            <span class="site-text-logo__first">{{ $first }}</span>
        @endif
        @if ($rest !== '')
            <span class="site-text-logo__rest">{{ $rest }}</span>
        @endif
    </span>
</a>
