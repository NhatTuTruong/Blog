<a href="{{ route('blog.show', $post->slug) }}" class="bh-card">
    <div class="bh-card__media">
        <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" loading="lazy">
    </div>
    <div class="bh-card__body">
        <div class="bh-card__top">
            @if($post->category)
                <span class="bh-card__badge">{{ $post->category }}</span>
            @endif
            <span class="bh-card__date">{{ $post->created_at?->format('M j, Y') }}</span>
        </div>
        <h3 class="bh-card__title">{{ $post->title }}</h3>
        @if($post->excerpt)
            <p class="bh-card__excerpt">{{ $post->excerpt }}</p>
        @endif
        <div class="bh-card__foot">
            <span class="bh-card__read">Read more →</span>
            <span>{{ $post->reading_minutes }} min</span>
        </div>
    </div>
</a>
