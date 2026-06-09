<x-filament-panels::page>

    @php
        $instagramRecordCount = $this->getRecordCountForPlatform('instagram');
        $facebookRecordCount = $this->getRecordCountForPlatform('facebook');
        $recordCount = $this->getRecordCount();
        $pendingCount = $queueStats['pending'] + $queueStats['processing'];
    @endphp

    <div
        x-data="{
            platform: @js($activePlatform),
            tab: @js($activeTab),
            igStats: @js($instagramQueueStats),
            fbStats: @js($facebookQueueStats),
            igInterval: @js($instagramQueueIntervalMinutes),
            fbInterval: @js($facebookQueueIntervalMinutes),
            setPlatform(next) {
                if (this.platform === next) return;
                this.platform = next;
                this.syncUrl();
                $wire.switchPlatform(next);
            },
            setTab(next) {
                if (this.tab === next) return;
                this.tab = next;
                this.syncUrl();
                $wire.switchTab(next);
            },
            syncUrl() {
                const url = new URL(window.location.href);
                url.searchParams.set('platform', this.platform);
                url.searchParams.set('tab', this.tab);
                window.history.replaceState({}, '', url);
            },
            activeStats() {
                return this.platform === 'facebook' ? this.fbStats : this.igStats;
            },
            activeInterval() {
                return this.platform === 'facebook' ? this.fbInterval : this.igInterval;
            },
            pendingFor(stats) {
                return (stats.pending ?? 0) + (stats.processing ?? 0);
            },
        }"
        x-on:queue-stats-synced.window="
            igStats = $event.detail.instagram;
            fbStats = $event.detail.facebook;
            igInterval = $event.detail.instagramInterval;
            fbInterval = $event.detail.facebookInterval;
        "
    >
        <div class="mb-4 flex flex-wrap gap-2">
            <x-filament::button
                tag="button"
                type="button"
                color="primary"
                icon="heroicon-o-camera"
                x-show="platform === 'instagram'"
                style="{{ $activePlatform !== 'instagram' ? 'display: none;' : '' }}"
                x-on:click="setPlatform('instagram')"
            >
                Instagram
                @if ($instagramRecordCount > 0)
                    <span class="ms-1 rounded-full bg-white/20 px-1.5 py-0.5 text-xs">{{ $instagramRecordCount }}</span>
                @endif
            </x-filament::button>
            <x-filament::button
                tag="button"
                type="button"
                color="gray"
                icon="heroicon-o-camera"
                x-show="platform !== 'instagram'"
                style="{{ $activePlatform === 'instagram' ? 'display: none;' : '' }}"
                x-on:click="setPlatform('instagram')"
            >
                Instagram
                @if ($instagramRecordCount > 0)
                    <span class="ms-1 rounded-full bg-white/20 px-1.5 py-0.5 text-xs">{{ $instagramRecordCount }}</span>
                @endif
            </x-filament::button>

            <x-filament::button
                tag="button"
                type="button"
                color="primary"
                icon="heroicon-o-globe-alt"
                x-show="platform === 'facebook'"
                style="{{ $activePlatform !== 'facebook' ? 'display: none;' : '' }}"
                x-on:click="setPlatform('facebook')"
            >
                Facebook
                @if ($facebookRecordCount > 0)
                    <span class="ms-1 rounded-full bg-white/20 px-1.5 py-0.5 text-xs">{{ $facebookRecordCount }}</span>
                @endif
            </x-filament::button>
            <x-filament::button
                tag="button"
                type="button"
                color="gray"
                icon="heroicon-o-globe-alt"
                x-show="platform !== 'facebook'"
                style="{{ $activePlatform === 'facebook' ? 'display: none;' : '' }}"
                x-on:click="setPlatform('facebook')"
            >
                Facebook
                @if ($facebookRecordCount > 0)
                    <span class="ms-1 rounded-full bg-white/20 px-1.5 py-0.5 text-xs">{{ $facebookRecordCount }}</span>
                @endif
            </x-filament::button>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <x-filament::button
                tag="button"
                type="button"
                color="primary"
                icon="heroicon-o-pencil-square"
                x-show="tab === 'compose'"
                style="{{ $activeTab !== 'compose' ? 'display: none;' : '' }}"
                x-on:click="setTab('compose')"
            >
                Soạn danh sách
                @if ($instagramRecordCount > 0)
                    <span x-show="platform === 'instagram'" class="ms-1 rounded-full bg-white/20 px-1.5 py-0.5 text-xs">{{ $instagramRecordCount }}</span>
                @endif
                @if ($facebookRecordCount > 0)
                    <span x-show="platform === 'facebook'" class="ms-1 rounded-full bg-white/20 px-1.5 py-0.5 text-xs">{{ $facebookRecordCount }}</span>
                @endif
            </x-filament::button>
            <x-filament::button
                tag="button"
                type="button"
                color="gray"
                icon="heroicon-o-pencil-square"
                x-show="tab !== 'compose'"
                style="{{ $activeTab === 'compose' ? 'display: none;' : '' }}"
                x-on:click="setTab('compose')"
            >
                Soạn danh sách
                @if ($instagramRecordCount > 0)
                    <span x-show="platform === 'instagram'" class="ms-1 rounded-full bg-white/20 px-1.5 py-0.5 text-xs">{{ $instagramRecordCount }}</span>
                @endif
                @if ($facebookRecordCount > 0)
                    <span x-show="platform === 'facebook'" class="ms-1 rounded-full bg-white/20 px-1.5 py-0.5 text-xs">{{ $facebookRecordCount }}</span>
                @endif
            </x-filament::button>

            <x-filament::button
                tag="button"
                type="button"
                color="primary"
                icon="heroicon-o-queue-list"
                x-show="tab === 'queue'"
                style="{{ $activeTab !== 'queue' ? 'display: none;' : '' }}"
                x-on:click="setTab('queue')"
            >
                Hàng đợi
                <span
                    x-show="pendingFor(activeStats()) > 0"
                    class="ms-1 rounded-full bg-amber-500/90 px-1.5 py-0.5 text-xs text-white"
                    x-text="pendingFor(activeStats())"
                ></span>
            </x-filament::button>
            <x-filament::button
                tag="button"
                type="button"
                color="gray"
                icon="heroicon-o-queue-list"
                x-show="tab !== 'queue'"
                style="{{ $activeTab === 'queue' ? 'display: none;' : '' }}"
                x-on:click="setTab('queue')"
            >
                Hàng đợi
                <span
                    x-show="pendingFor(activeStats()) > 0"
                    class="ms-1 rounded-full bg-amber-500/90 px-1.5 py-0.5 text-xs text-white"
                    x-text="pendingFor(activeStats())"
                ></span>
            </x-filament::button>
        </div>

        {{-- Soạn bài: cả hai form luôn có trong DOM, Alpine ẩn/hiện tức thì --}}
        <div x-show="tab === 'compose'" x-cloak>
            <div wire:poll.10s="saveFormDraft" class="hidden" aria-hidden="true"></div>

            <div x-show="platform === 'instagram'">
                {{ $this->instagramForm }}
            </div>

            <div x-show="platform === 'facebook'">
                {{ $this->facebookForm }}
            </div>
        </div>

        {{-- Hàng đợi: stats đổi ngay qua Alpine; bảng sync nền qua Livewire --}}
        <div x-show="tab === 'queue'" x-cloak class="space-y-4">
            <div wire:poll.30s="refreshQueue" class="hidden" aria-hidden="true"></div>

            <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="fi-section-header-heading text-sm font-semibold">
                            Hàng đợi <span x-text="platform === 'facebook' ? 'Facebook' : 'Instagram'"></span>
                        </span>
                        <x-filament::badge color="gray" size="sm">
                            <span x-text="activeInterval()"></span> phút/bài
                        </x-filament::badge>
                        <x-filament::badge color="warning" size="sm">
                            Chờ <span x-text="activeStats().pending ?? 0"></span>
                        </x-filament::badge>
                        <x-filament::badge color="info" size="sm">
                            Đang đăng <span x-text="activeStats().processing ?? 0"></span>
                        </x-filament::badge>
                        <x-filament::badge color="success" size="sm">
                            Xong <span x-text="activeStats().completed ?? 0"></span>
                        </x-filament::badge>
                        <x-filament::badge color="danger" size="sm">
                            Lỗi <span x-text="activeStats().failed ?? 0"></span>
                        </x-filament::badge>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-filament::button
                            color="warning"
                            size="sm"
                            icon="heroicon-o-arrow-path"
                            wire:click="releaseStuckProcessing"
                            wire:confirm="Đưa bài «Đang đăng» về «Chờ đăng» để thử lại?"
                            :disabled="! $this->canReleaseStuckProcessing()"
                        >
                            Mở kẹt
                        </x-filament::button>
                        <x-filament::button
                            color="danger"
                            size="sm"
                            icon="heroicon-o-x-circle"
                            wire:click="cancelPendingQueue"
                            wire:confirm="Hủy tất cả bài đang chờ?"
                            :disabled="! $this->canCancelPendingQueue()"
                        >
                            Hủy hàng đợi
                        </x-filament::button>
                    </div>
                </div>
            </section>

            <div wire:loading.delay.shortest wire:target="switchPlatform,switchTab,refreshQueue" class="text-sm text-gray-500 dark:text-gray-400">
                Đang cập nhật bảng hàng đợi…
            </div>

            <div wire:loading.remove wire:target="switchPlatform,switchTab">
                {{ $this->table }}
            </div>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
