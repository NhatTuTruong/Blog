@php
    /** @var \App\Models\EmailSendLog $record */
    $record = $getRecord();
    $attachments = $record->attachmentDisplayItems();
@endphp

@include('filament.partials.email-attachment-cards', [
    'attachments' => $attachments,
    'emptyMessage' => 'Lần gửi này không có tệp đính kèm.',
])
