@php
    use App\Support\PublicStorage;

    $mediaUrl = filled($record->image_path) ? PublicStorage::url($record->image_path) : null;
    $coupons = is_array($record->coupon_codes ?? null)
        ? collect($record->coupon_codes)->filter()->implode(', ')
        : null;
@endphp

<div class="space-y-4 text-sm">
    @if ($mediaUrl)
        <div class="rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800">
            <img src="{{ $mediaUrl }}" alt="Featured image" class="w-full max-h-64 object-contain" />
        </div>
    @endif

    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Trạng thái</dt>
            <dd class="mt-0.5">{{ $record->statusLabel() }}</dd>
        </div>
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Domain</dt>
            <dd class="mt-0.5">{{ filled($record->brand_domain) ? $record->brand_domain : '—' }}</dd>
        </div>
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Danh mục</dt>
            <dd class="mt-0.5">{{ $record->category_name ?? $record->blogCategory?->name ?? 'General' }}</dd>
        </div>
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Batch</dt>
            <dd class="mt-0.5 break-all font-mono text-xs">{{ $record->batch_id ?? '—' }}</dd>
        </div>
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Lên lịch</dt>
            <dd class="mt-0.5">{{ $record->scheduled_at?->format('d/m/Y H:i') ?? '—' }}</dd>
        </div>
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Xử lý lúc</dt>
            <dd class="mt-0.5">{{ $record->processed_at?->format('d/m/Y H:i') ?? '—' }}</dd>
        </div>
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Blog ID</dt>
            <dd class="mt-0.5">
                @if ($record->blog_id && $record->blog)
                    <a href="{{ \App\Filament\Admin\Resources\BlogResource::getUrl('edit', ['record' => $record->blog_id]) }}"
                       target="_blank"
                       class="text-primary-600 hover:underline">
                        #{{ $record->blog_id }}: {{ \Illuminate\Support\Str::limit($record->blog->title, 40) }}
                    </a>
                @else
                    —
                @endif
            </dd>
        </div>
        @if ($record->user)
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">User</dt>
                <dd class="mt-0.5">{{ $record->user->name ?? $record->user->email ?? '—' }}</dd>
            </div>
        @endif
    </dl>

    @if (filled($record->aff_link))
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Link Affiliate</dt>
            <dd class="mt-0.5 break-all">
                <a href="{{ $record->aff_link }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline">
                    {{ $record->aff_link }}
                </a>
            </dd>
        </div>
    @endif

    @if ($coupons)
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Coupon</dt>
            <dd class="mt-0.5">{{ $coupons }}</dd>
        </div>
    @endif

    @if (filled($record->content_idea))
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Ý tưởng</dt>
            <dd class="mt-0.5 whitespace-pre-wrap rounded-lg bg-gray-50 p-3 dark:bg-white/5">{{ $record->content_idea }}</dd>
        </div>
    @endif

    @if (filled($record->error_message))
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Lỗi</dt>
            <dd class="mt-0.5 whitespace-pre-wrap rounded-lg bg-danger-50 p-3 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">{{ $record->error_message }}</dd>
        </div>
    @endif

    @if (filled($record->image_path))
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Image path</dt>
            <dd class="mt-0.5 break-all font-mono text-xs">{{ $record->image_path }}</dd>
        </div>
    @endif
</div>
