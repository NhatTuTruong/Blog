@php
    /** @var \App\Models\ReceivedEmail $record */
    $record = $getRecord();
    $attachments = $record->attachmentItems();
@endphp

@include('filament.partials.email-attachment-cards', [
    'attachments' => $attachments,
    'emptyMessage' => 'Email có '.$record->attachments_count.' tệp đính kèm trên server nhưng chưa được tải về hệ thống.',
    'emptyHint' => 'Bấm «Tải đính kèm» ở góc trên để tải file về, hoặc đồng bộ lại hộp thư.',
])
