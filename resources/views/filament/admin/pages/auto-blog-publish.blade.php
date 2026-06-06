<x-filament-panels::page>
    @php
        $recordCount = $this->getRecordCount();
        $pendingCount = $queueStats['pending'] + $queueStats['processing'];
    @endphp

    <div class="mb-4 flex flex-wrap gap-2">
        <x-filament::button
            :color="$activeTab === 'compose' ? 'primary' : 'gray'"
            wire:click="$set('activeTab', 'compose')"
            icon="heroicon-o-pencil-square"
        >
            Soạn danh sách
            @if ($recordCount > 0)
                <span class="ms-1 rounded-full bg-white/20 px-1.5 py-0.5 text-xs">{{ $recordCount }}</span>
            @endif
        </x-filament::button>

        <x-filament::button
            :color="$activeTab === 'queue' ? 'primary' : 'gray'"
            wire:click="$set('activeTab', 'queue')"
            icon="heroicon-o-queue-list"
        >
            Hàng đợi
            @if ($pendingCount > 0)
                <span class="ms-1 rounded-full bg-amber-500/90 px-1.5 py-0.5 text-xs text-white">{{ $pendingCount }}</span>
            @endif
        </x-filament::button>
    </div>

    @if ($activeTab === 'compose')
        {{ $this->form }}
    @else
        <div wire:poll.30s="refreshQueue" class="space-y-4">
            <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="fi-section-header-heading text-sm font-semibold">
                            Trạng thái hàng đợi
                        </span>
                        <x-filament::badge color="gray" size="sm">
                            {{ $queueIntervalMinutes }} phút/bài
                        </x-filament::badge>
                        <x-filament::badge color="warning" size="sm">
                            Chờ {{ $queueStats['pending'] }}
                        </x-filament::badge>
                        <x-filament::badge color="info" size="sm">
                            Đang tạo {{ $queueStats['processing'] }}
                        </x-filament::badge>
                        <x-filament::badge color="success" size="sm">
                            Xong {{ $queueStats['completed'] }}
                        </x-filament::badge>
                        <x-filament::badge color="danger" size="sm">
                            Lỗi {{ $queueStats['failed'] }}
                        </x-filament::badge>
                    </div>

                    <x-filament::button
                        color="danger"
                        size="sm"
                        icon="heroicon-o-x-circle"
                        wire:click="cancelPendingQueue"
                        wire:confirm="Hủy tất cả bài đang chờ? Bạn cần soạn lại danh sách để đăng tiếp."
                        :disabled="! $this->canCancelPendingQueue()"
                    >
                        Hủy hàng đợi
                    </x-filament::button>
                </div>
            </section>

            {{ $this->table }}
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
