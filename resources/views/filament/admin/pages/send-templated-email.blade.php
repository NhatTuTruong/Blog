<x-filament-panels::page>
    <div wire:poll.10s="saveFormDraft" class="hidden" aria-hidden="true"></div>

    {{ $this->form }}

    @if ($this->showPreview)
        <x-filament::section class="mt-6">
            <x-slot name="heading">
                Xem trước
            </x-slot>

            <div class="space-y-4">
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Tiêu đề
                    </p>
                    <p class="mt-1 text-sm text-gray-950 dark:text-white">
                        {{ $this->previewSubject ?: '—' }}
                    </p>
                </div>

                @if (count($this->previewAttachmentNames) > 0)
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Tệp đính kèm
                        </p>
                        <p class="mt-1 text-sm text-gray-950 dark:text-white">
                            {{ implode(', ', $this->previewAttachmentNames) }}
                        </p>
                    </div>
                @endif

                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Nội dung (giao diện email)
                    </p>
                    <div class="mt-2 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        @if ($this->previewBodyHtml)
                            @include('emails.partials.card', ['htmlBody' => $this->previewBodyHtml])
                        @else
                            <p class="bg-white p-4 text-sm text-gray-500 dark:bg-gray-900 dark:text-gray-400">—</p>
                        @endif
                    </div>
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
