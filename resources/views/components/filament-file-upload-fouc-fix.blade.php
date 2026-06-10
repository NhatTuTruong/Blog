@php
    $fileUploadSrc = \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('file-upload', 'filament/forms');
@endphp

<link rel="modulepreload" href="{{ asset($fileUploadSrc) }}">

<style>
    /* Ẩn input file gốc của trình duyệt cho đến khi FilePond (kéo thả) sẵn sàng */
    [data-fi-theme] .fi-fo-file-upload:not(:has(.filepond--root)) > div:first-child {
        position: relative;
        overflow: hidden;
        border-radius: 0.5rem;
        border: 2px dashed rgba(var(--gray-400), 0.55);
        background: rgba(var(--gray-950), 0.04);
    }

    [data-fi-theme] .fi-fo-file-upload:not(:has(.filepond--root)) > div.w-full {
        min-height: 4.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    [data-fi-theme] .fi-fo-file-upload:not(:has(.filepond--root)) > div.w-full::before {
        content: 'Kéo thả file hoặc bấm để chọn';
        font-size: 0.875rem;
        line-height: 1.25rem;
        color: rgba(var(--gray-500), 1);
        text-align: center;
        pointer-events: none;
        padding: 0.75rem 1rem;
    }

    [data-fi-theme] .fi-fo-file-upload:not(:has(.filepond--root)) input[type='file'] {
        position: absolute;
        inset: 0;
        margin: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        color: transparent;
        cursor: pointer;
        z-index: 2;
        font-size: 0;
        appearance: none;
        -webkit-appearance: none;
        border: 0;
        background: transparent;
    }

    [data-fi-theme] .fi-fo-file-upload:not(:has(.filepond--root)) input[type='file']::file-selector-button {
        display: none;
        appearance: none;
        -webkit-appearance: none;
    }
</style>
