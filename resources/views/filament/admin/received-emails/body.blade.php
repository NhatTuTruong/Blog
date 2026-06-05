@php
    /** @var \App\Models\ReceivedEmail $record */
    $record = $getRecord();
@endphp

<div class="received-email-preview w-full">
    <div class="received-email-preview__frame">
        {!! $record->displayBodyHtml() !!}
    </div>
</div>

<style>
    .received-email-preview {
        position: relative;
        z-index: 0;
        isolation: isolate;
        width: 100%;
        max-width: 100%;
    }

    .received-email-preview__frame {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: visible;
        background: #ffffff;
        color: #111827;
        border-radius: 0.5rem;
        border: 1px solid rgb(229 231 235);
        padding: 1.25rem;
        line-height: 1.6;
        font-size: 14px;
        box-sizing: border-box;
    }

    .dark .received-email-preview__frame {
        border-color: rgb(55 65 81);
    }

    .received-email-preview__frame * {
        max-width: 100% !important;
        box-sizing: border-box;
    }

    .received-email-preview__frame table {
        width: 100% !important;
        max-width: 100% !important;
        table-layout: auto !important;
    }

    .received-email-preview__frame img {
        height: auto !important;
    }

    .received-email-preview__frame center,
    .received-email-preview__frame [align="center"] {
        text-align: left !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
    }

    .received-email-preview__frame body,
    .received-email-preview__frame html {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
    }

    .received-email-plain {
        white-space: pre-wrap;
        word-break: break-word;
    }

    .received-email-empty {
        margin: 0;
        color: rgb(107 114 128);
    }
</style>
