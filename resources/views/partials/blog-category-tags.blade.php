@php
    $names = $post->category_names_list;
    $variant = $variant ?? 'overlay';
    $compact = $compact ?? false;
@endphp

@if($names !== [])
<div @class([
    'bh-card__tags',
    'bh-card__tags--overlay' => $variant === 'overlay',
    'bh-card__tags--inline' => $variant === 'inline',
    'bh-card__tags--text' => $variant === 'text',
    'bh-card__tags--compact' => $compact,
])>
    @foreach($names as $name)
        <span class="bh-card__tag">{{ $name }}</span>
    @endforeach
</div>
@endif
