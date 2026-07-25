<x-filament-panels::page class="[&>section]:!gap-y-3 [&>section]:!pt-4 [&_.fi-page-header]:!mb-0">

    @php
        $recordCount = $this->getRecordCount();
        $pendingCount = $queueStats['pending'] + $queueStats['processing'];
    @endphp

    <div
        x-data="{
            tab: $wire.$entangle('activeTab'),
            stats: @js($queueStats),
            interval: @js($queueIntervalMinutes),
            pendingFor(stats) {
                return (stats.pending ?? 0) + (stats.processing ?? 0);
            },
        }"
        x-on:abp-queue-stats-synced.window="
            stats = $event.detail.stats;
            interval = $event.detail.interval;
        "
    >
        <div class="abp-tag-nav mb-3 space-y-2">
            {{-- Tag con: thuộc nền tảng đang chọn --}}
            <div
                class="abp-tag-children flex flex-wrap items-center gap-2 border-l-2 pl-3"
                :class="tab === 'compose' ? 'border-primary-500/70' : 'border-primary-500/70'"
            >
                <button
                    type="button"
                    class="abp-tag abp-tag-child"
                    :class="tab === 'compose' ? 'abp-child-active' : ''"
                    x-on:click="$wire.$set('activeTab', 'compose')"
                >
                    <x-filament::icon icon="heroicon-o-pencil-square" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                    <span>Soạn danh sách</span>
                    @if ($recordCount > 0)
                        <span class="abp-tag-count">{{ $recordCount }}</span>
                    @endif
                </button>

                <button
                    type="button"
                    class="abp-tag abp-tag-child"
                    :class="tab === 'queue' ? 'abp-child-active' : ''"
                    x-on:click="$wire.$set('activeTab', 'queue')"
                >
                    <x-filament::icon icon="heroicon-o-queue-list" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                    <span>Hàng đợi</span>
                    <span
                        x-show="pendingFor(stats) > 0"
                        class="abp-tag-count abp-tag-count-warn"
                        x-text="pendingFor(stats)"
                    ></span>
                </button>
            </div>
        </div>

        <style>
            .abp-tag-nav .abp-tag {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                border-radius: 9999px;
                font-weight: 600;
                line-height: 1.2;
                transition: all 0.15s ease;
                cursor: pointer;
                border: 1px solid transparent;
            }

            .abp-tag-nav .abp-tag-child {
                padding: 0.3rem 0.75rem;
                font-size: 0.8125rem;
                font-weight: 500;
                color: rgb(139 148 158);
                background: rgb(22 27 34 / 0.75);
                border-color: rgb(48 54 61 / 0.65);
            }

            .abp-tag-nav .abp-tag-child:hover {
                color: rgb(230 237 243);
                background: rgb(28 33 40 / 0.9);
            }

            .abp-tag-nav .abp-child-active {
                color: #fff;
                background: rgb(59 130 246 / 0.22);
                border-color: rgb(59 130 246 / 0.55);
            }

            .abp-tag-nav .abp-tag-children {
                min-height: 2rem;
                margin-bottom: 10px !important;
                border-color: rgb(48 54 61 / 0.65) !important;
            }

            .abp-tag-nav .abp-tag-count {
                display: inline-flex;
                min-width: 1.25rem;
                align-items: center;
                justify-content: center;
                border-radius: 9999px;
                padding: 0.1rem 0.4rem;
                font-size: 0.6875rem;
                font-weight: 700;
                background: rgb(255 255 255 / 0.18);
                color: inherit;
            }

            .abp-tag-nav .abp-tag-count-warn {
                background: rgb(245 158 11 / 0.9);
                color: #fff;
            }
        </style>

        {{-- Soạn danh sách --}}
        <div x-show="tab === 'compose'" x-cloak>
            {{ $this->form }}
        </div>

        {{-- Hàng đợi: poll 10s --}}
        <div
            x-show="tab === 'queue'"
            x-cloak
            class="space-y-4"
            data-abp-queue-panel
        >
            @if ($activeTab === 'queue')
                <div wire:poll.10s="pollQueueDisplay" class="hidden" aria-hidden="true"></div>
            @endif

            <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="fi-section-header-heading text-sm font-semibold">
                            Hàng đợi Blog
                        </span>
                        <x-filament::badge color="gray" size="sm">
                            <span x-text="interval"></span> phút/bài
                        </x-filament::badge>
                        <x-filament::badge color="warning" size="sm">
                            Chờ <span x-text="stats.pending ?? 0"></span>
                        </x-filament::badge>
                        <x-filament::badge color="info" size="sm">
                            Đang tạo <span x-text="stats.processing ?? 0"></span>
                        </x-filament::badge>
                        <x-filament::badge color="success" size="sm">
                            Xong <span x-text="stats.completed ?? 0"></span>
                        </x-filament::badge>
                        <x-filament::badge color="danger" size="sm">
                            Lỗi <span x-text="stats.failed ?? 0"></span>
                        </x-filament::badge>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-filament::button
                            color="warning"
                            size="sm"
                            icon="heroicon-o-arrow-path"
                            wire:click="releaseStuckProcessing"
                            wire:confirm="Gỡ kẹt tất cả bài «Đang tạo»? Các bài đó sẽ chuyển sang Lỗi để hàng đợi chạy tiếp."
                            :disabled="! $this->canReleaseStuckProcessing()"
                        >
                            Mở kẹt
                        </x-filament::button>
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
                </div>
            </section>

            @if ($activeTab === 'queue')
                <div wire:loading.remove wire:target="switchTab">
                    {{ $this->table }}
                </div>
            @endif
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
