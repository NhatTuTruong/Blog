@php
    use App\Models\FacebookQueueItem;
    use App\Models\InstagramQueueItem;
    use App\Models\PinterestQueueItem;
    use App\Support\PublicStorage;
    use App\Support\SocialMediaMediaType;

    /** @var InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $record */
    $record = $getRecord();

    $isVideo = filled($record->video_path ?? null)
        || SocialMediaMediaType::normalize($record->media_type ?? null) === SocialMediaMediaType::VIDEO;

    $imagePath = filled($record->image_path ?? null) ? PublicStorage::normalizePath((string) $record->image_path) : null;
    $previewUrl = $imagePath !== null && PublicStorage::exists($imagePath)
        ? PublicStorage::url($imagePath)
        : null;
@endphp

<div style="width: 45px; height: 45px; margin-left: 10px" class="relative inline-flex h-12 w-12 shrink-0 overflow-hidden rounded-lg border border-gray-200 bg-gray-100 dark:border-white/10 dark:bg-gray-800">
    @if ($previewUrl)
        <img
            src="{{ $previewUrl }}"
            alt=""
            class="block h-full w-full object-cover"
            loading="lazy"
        />
    @else
        <div style="font-size: 8px" class="flex h-full w-full items-center justify-center text-[5px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
            {{ $isVideo ? 'Video' : 'AI' }}
        </div>
    @endif

    @if ($isVideo)
        <span
            class="pointer-events-none absolute inset-0 flex items-center justify-center"
            aria-hidden="true"
        >
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 shadow-[0_2px_8px_rgba(0,0,0,0.35)] ring-2 ring-white/90">
                <svg class="ml-0.5 h-3.5 w-3.5 shrink-0 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M8 5v14l11-7z" />
                </svg>
            </span>
        </span>
    @endif
</div>
