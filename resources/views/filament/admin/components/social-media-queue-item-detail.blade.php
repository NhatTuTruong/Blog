@php
    use App\Models\FacebookQueueItem;
    use App\Models\InstagramQueueItem;
    use App\Filament\Admin\Support\SocialMediaQueueTable;
    use App\Support\PublicStorage;

    $isInstagram = $platform === 'instagram';
    $accountLabel = $isInstagram
        ? ($record->instagramAccount?->displayLabel() ?? '—')
        : ($record->facebookAccount?->displayLabel() ?? '—');
    $externalId = $isInstagram ? $record->instagram_media_id : $record->facebook_post_id;
    $externalIdLabel = $isInstagram ? 'Instagram Media ID' : 'Facebook Post ID';
    $mediaPath = filled($record->video_path) ? $record->video_path : $record->image_path;
    $mediaType = filled($record->video_path) ? 'Video' : (filled($record->image_path) ? 'Ảnh' : 'Ảnh mặc định (AI)');
    $coupons = is_array($record->coupon_codes ?? null)
        ? collect($record->coupon_codes)->filter()->implode(', ')
        : '—';
    $statusNote = SocialMediaQueueTable::statusNote($record);
@endphp

<div class="space-y-4 text-sm">
    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Trạng thái</dt>
            <dd class="mt-0.5">{{ $record->statusLabel() }}</dd>
        </div>
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Tài khoản</dt>
            <dd class="mt-0.5">{{ $accountLabel }}</dd>
        </div>
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Brand</dt>
            <dd class="mt-0.5">{{ filled($record->brand_domain) ? $record->brand_domain : '—' }}</dd>
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
            <dt class="font-medium text-gray-500 dark:text-gray-400">Media</dt>
            <dd class="mt-0.5">{{ $mediaType }}</dd>
        </div>
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">{{ $externalIdLabel }}</dt>
            <dd class="mt-0.5 break-all">{{ filled($externalId) ? $externalId : '—' }}</dd>
        </div>
    </dl>

    @if (filled($record->aff_link))
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Link Affiliate</dt>
            <dd class="mt-0.5 break-all"><a href="{{ $record->aff_link }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline">{{ $record->aff_link }}</a></dd>
        </div>
    @endif

    @if ($coupons !== '—')
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Coupon</dt>
            <dd class="mt-0.5">{{ $coupons }}</dd>
        </div>
    @endif

    @if (filled($record->content_idea))
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Ý tưởng caption</dt>
            <dd class="mt-0.5 whitespace-pre-wrap rounded-lg bg-gray-50 p-3 dark:bg-white/5">{{ $record->content_idea }}</dd>
        </div>
    @endif

    @if (filled($record->caption))
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Caption</dt>
            <dd class="mt-0.5 whitespace-pre-wrap rounded-lg bg-gray-50 p-3 dark:bg-white/5">{{ $record->caption }}</dd>
        </div>
    @endif

    @if (filled($statusNote))
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">Ghi chú / Lỗi</dt>
            <dd class="mt-0.5 whitespace-pre-wrap rounded-lg bg-danger-50 p-3 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">{{ $statusNote }}</dd>
        </div>
    @endif

    @if (filled($mediaPath) && PublicStorage::exists((string) $mediaPath))
        <div>
            <dt class="font-medium text-gray-500 dark:text-gray-400">File media</dt>
            <dd class="mt-0.5 break-all font-mono text-xs">{{ $mediaPath }}</dd>
        </div>
    @endif
</div>
