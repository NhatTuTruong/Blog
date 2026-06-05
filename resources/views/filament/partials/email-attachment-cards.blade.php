@php
    /** @var array<int, array<string, mixed>> $attachments */
    $attachments = $attachments ?? [];
    $emptyMessage = $emptyMessage ?? 'Không có tệp đính kèm.';
    $emptyHint = $emptyHint ?? null;
@endphp

@if ($attachments === [])
    <div class="email-attachment-cards-empty">
        <p>{{ $emptyMessage }}</p>
        @if ($emptyHint)
            <p class="email-attachment-cards-empty__hint">{{ $emptyHint }}</p>
        @endif
    </div>
@else
    <div class="email-attachment-cards-grid">
        @foreach ($attachments as $attachment)
            @php
                $previewUrl = $attachment['preview_url'] ?? null;
                $downloadUrl = $attachment['download_url'] ?? null;
                $canPreview = (bool) ($attachment['can_preview'] ?? false) && filled($previewUrl);
                $name = (string) ($attachment['name'] ?? 'file');
                $contentType = (string) ($attachment['content_type'] ?? '');
                $isImage = $contentType !== '' && str_starts_with($contentType, 'image/');
                if (! $isImage) {
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true) && filled($previewUrl);
                }
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $metaNote = $attachment['meta_note'] ?? null;
            @endphp
            <article class="email-attachment-card {{ $isImage && $previewUrl ? 'email-attachment-card--image' : '' }}">
                @if ($isImage && $previewUrl)
                    <a href="{{ $previewUrl }}" target="_blank" rel="noopener noreferrer" class="email-attachment-card__thumb">
                        <img src="{{ $previewUrl }}" alt="{{ $name }}" loading="lazy">
                    </a>
                @else
                    <div class="email-attachment-card__icon" aria-hidden="true">
                        @if ($ext === 'pdf')
                            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path d="M7 3h7l5 5v13a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>
                        @else
                            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path d="M7 4h10l4 4v12a1 1 0 01-1 1H7a1 1 0 01-1-1V4z"/><path d="M17 4v4h4"/></svg>
                        @endif
                    </div>
                @endif

                <div class="email-attachment-card__body">
                    <p class="email-attachment-card__name" title="{{ $name }}">{{ $name }}</p>
                    <p class="email-attachment-card__meta">
                        @if (! empty($attachment['size_label']))
                            {{ $attachment['size_label'] }}
                        @endif
                        @if (! empty($attachment['content_type']))
                            @if (! empty($attachment['size_label'])) · @endif
                            {{ Str::limit($attachment['content_type'], 24) }}
                        @endif
                        @if ($metaNote && empty($attachment['size_label']) && empty($attachment['content_type']))
                            {{ $metaNote }}
                        @elseif ($metaNote)
                            · {{ $metaNote }}
                        @endif
                    </p>
                    @if ($canPreview || filled($downloadUrl))
                        <div class="email-attachment-card__actions">
                            @if ($canPreview)
                                <a href="{{ $previewUrl }}" target="_blank" rel="noopener noreferrer" class="email-attachment-card__btn email-attachment-card__btn--primary">
                                    Xem
                                </a>
                            @endif
                            @if (filled($downloadUrl))
                                <a href="{{ $downloadUrl }}" class="email-attachment-card__btn">
                                    Tải xuống
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
@endif

@once
<style>
    .email-attachment-cards-empty {
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        border: 1px dashed rgba(148, 163, 184, 0.35);
        background: rgba(148, 163, 184, 0.06);
        font-size: 0.8125rem;
        color: rgb(148 163 184);
    }
    .email-attachment-cards-empty__hint {
        margin-top: 0.35rem;
        font-size: 0.75rem;
        opacity: 0.85;
    }
    .email-attachment-cards-grid {
        display: grid;
        grid-template-columns: repeat(1, minmax(0, 1fr));
        gap: 0.5rem;
    }
    @media (min-width: 640px) {
        .email-attachment-cards-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (min-width: 1024px) {
        .email-attachment-cards-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    .email-attachment-card {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 0.65rem;
        align-items: start;
        padding: 0.6rem 0.7rem;
        border-radius: 0.5rem;
        border: 1px solid rgba(148, 163, 184, 0.2);
        background: rgba(255, 255, 255, 0.03);
    }
    .email-attachment-card--image { grid-template-columns: 4.5rem 1fr; }
    .email-attachment-card__thumb {
        display: block;
        width: 4.5rem;
        height: 4.5rem;
        border-radius: 0.4rem;
        overflow: hidden;
        background: rgba(0, 0, 0, 0.2);
    }
    .email-attachment-card__thumb img { width: 100%; height: 100%; object-fit: cover; }
    .email-attachment-card__icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 0.4rem;
        background: rgba(16, 185, 129, 0.12);
        color: rgb(52 211 153);
    }
    .email-attachment-card__body { min-width: 0; }
    .email-attachment-card__name {
        margin: 0;
        font-size: 0.8125rem;
        font-weight: 600;
        line-height: 1.3;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .email-attachment-card__meta {
        margin: 0.2rem 0 0.35rem;
        font-size: 0.6875rem;
        line-height: 1.35;
        opacity: 0.65;
    }
    .email-attachment-card__actions { display: flex; flex-wrap: wrap; gap: 0.35rem; }
    .email-attachment-card__btn {
        display: inline-flex;
        padding: 0.25rem 0.55rem;
        font-size: 0.6875rem;
        font-weight: 600;
        border-radius: 0.35rem;
        text-decoration: none;
        border: 1px solid rgba(148, 163, 184, 0.35);
        transition: background 0.15s;
    }
    .email-attachment-card__btn:hover { background: rgba(148, 163, 184, 0.12); }
    .email-attachment-card__btn--primary {
        border-color: transparent;
        background: rgb(5 150 105);
        color: #fff;
    }
    .email-attachment-card__btn--primary:hover { background: rgb(16 185 129); }
</style>
@endonce
