<x-filament-panels::page class="[&>section]:!gap-y-3 [&>section]:!pt-4 [&_.fi-page-header]:!mb-0">

    @php
        $instagramRecordCount = $this->getRecordCountForPlatform('instagram');
        $facebookRecordCount = $this->getRecordCountForPlatform('facebook');
        $pinterestRecordCount = $this->getRecordCountForPlatform('pinterest');
        $recordCount = $this->getRecordCount();
        $pendingCount = $queueStats['pending'] + $queueStats['processing'];
    @endphp

    <div
        x-data="{
            platform: $wire.$entangle('activePlatform'),
            tab: $wire.$entangle('activeTab'),
            igStats: @js($instagramQueueStats),
            fbStats: @js($facebookQueueStats),
            pinStats: @js($pinterestQueueStats),
            igInterval: @js($instagramQueueIntervalMinutes),
            fbInterval: @js($facebookQueueIntervalMinutes),
            pinInterval: @js($pinterestQueueIntervalMinutes),
            igAutoEnabled: @js($instagramAutoQueueEnabled),
            fbAutoEnabled: @js($facebookAutoQueueEnabled),
            pinAutoEnabled: @js($pinterestAutoQueueEnabled),
            igAutoPaused: @js($instagramAutoQueuePaused),
            fbAutoPaused: @js($facebookAutoQueuePaused),
            pinAutoPaused: @js($pinterestAutoQueuePaused),
            igAutoInterval: @js($instagramAutoQueueIntervalMinutes),
            fbAutoInterval: @js($facebookAutoQueueIntervalMinutes),
            pinAutoInterval: @js($pinterestAutoQueueIntervalMinutes),
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
                if (this.platform === 'facebook') return this.fbStats;
                if (this.platform === 'pinterest') return this.pinStats;
                return this.igStats;
            },
            activeInterval() {
                if (this.platform === 'facebook') return this.fbInterval;
                if (this.platform === 'pinterest') return this.pinInterval;
                return this.igInterval;
            },
            activeAutoEnabled() {
                if (this.platform === 'facebook') return this.fbAutoEnabled;
                if (this.platform === 'pinterest') return this.pinAutoEnabled;
                return this.igAutoEnabled;
            },
            activeAutoPaused() {
                if (this.platform === 'facebook') return this.fbAutoPaused;
                if (this.platform === 'pinterest') return this.pinAutoPaused;
                return this.igAutoPaused;
            },
            activeAutoInterval() {
                if (this.platform === 'facebook') return this.fbAutoInterval;
                if (this.platform === 'pinterest') return this.pinAutoInterval;
                return this.igAutoInterval;
            },
            pendingFor(stats) {
                return (stats.pending ?? 0) + (stats.processing ?? 0);
            },
            childAccentBorder() {
                if (this.platform === 'facebook') return 'border-blue-500/70';
                if (this.platform === 'pinterest') return 'border-red-500/70';
                return 'border-fuchsia-500/70';
            },
            childAccentActive() {
                if (this.platform === 'facebook') return 'smp-child-active-fb';
                if (this.platform === 'pinterest') return 'smp-child-active-pin';
                return 'smp-child-active-ig';
            },
        }"
        x-on:queue-stats-synced.window="
            igStats = $event.detail.instagram;
            fbStats = $event.detail.facebook;
            pinStats = $event.detail.pinterest;
            igInterval = $event.detail.instagramInterval;
            fbInterval = $event.detail.facebookInterval;
            pinInterval = $event.detail.pinterestInterval;
            igAutoEnabled = $event.detail.instagramAutoEnabled;
            fbAutoEnabled = $event.detail.facebookAutoEnabled;
            pinAutoEnabled = $event.detail.pinterestAutoEnabled;
            igAutoPaused = $event.detail.instagramAutoPaused;
            fbAutoPaused = $event.detail.facebookAutoPaused;
            pinAutoPaused = $event.detail.pinterestAutoPaused;
            igAutoInterval = $event.detail.instagramAutoInterval;
            fbAutoInterval = $event.detail.facebookAutoInterval;
            pinAutoInterval = $event.detail.pinterestAutoInterval;
        "
    >
        <div class="smp-tag-nav mb-3 space-y-2">
            {{-- Tag cha: nền tảng --}}
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="smp-tag smp-tag-parent smp-tag-ig"
                    :class="platform === 'instagram' ? 'is-active' : ''"
                    x-on:click="setPlatform('instagram')"
                >
                    <x-filament::icon icon="heroicon-o-camera" class="h-4 w-4 shrink-0" />
                    <span>Instagram</span>
                    @if ($instagramRecordCount > 0)
                        <span class="smp-tag-count">{{ $instagramRecordCount }}</span>
                    @endif
                </button>

                <button
                    type="button"
                    class="smp-tag smp-tag-parent smp-tag-fb"
                    :class="platform === 'facebook' ? 'is-active' : ''"
                    x-on:click="setPlatform('facebook')"
                >
                    <x-filament::icon icon="heroicon-o-globe-alt" class="h-4 w-4 shrink-0" />
                    <span>Facebook</span>
                    @if ($facebookRecordCount > 0)
                        <span class="smp-tag-count">{{ $facebookRecordCount }}</span>
                    @endif
                </button>

                <button
                    type="button"
                    class="smp-tag smp-tag-parent smp-tag-pin"
                    :class="platform === 'pinterest' ? 'is-active' : ''"
                    x-on:click="setPlatform('pinterest')"
                >
                    <x-filament::icon icon="heroicon-o-bookmark" class="h-4 w-4 shrink-0" />
                    <span>Pinterest</span>
                    @if ($pinterestRecordCount > 0)
                        <span class="smp-tag-count">{{ $pinterestRecordCount }}</span>
                    @endif
                </button>
            </div>

            {{-- Tag con: thuộc nền tảng đang chọn --}}
            <div
                class="smp-tag-children flex flex-wrap items-center gap-2 border-l-2 pl-3 mb-2"
                :class="childAccentBorder()"
            >
                <button
                    type="button"
                    class="smp-tag smp-tag-child"
                    :class="tab === 'compose' ? childAccentActive() : ''"
                    x-on:click="setTab('compose')"
                >
                    <x-filament::icon icon="heroicon-o-pencil-square" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                    <span>Soạn danh sách</span>
                    @if ($instagramRecordCount > 0)
                        <span x-show="platform === 'instagram'" class="smp-tag-count smp-tag-count-sm">{{ $instagramRecordCount }}</span>
                    @endif
                    @if ($facebookRecordCount > 0)
                        <span x-show="platform === 'facebook'" class="smp-tag-count smp-tag-count-sm">{{ $facebookRecordCount }}</span>
                    @endif
                    @if ($pinterestRecordCount > 0)
                        <span x-show="platform === 'pinterest'" class="smp-tag-count smp-tag-count-sm">{{ $pinterestRecordCount }}</span>
                    @endif
                </button>

                <button
                    type="button"
                    class="smp-tag smp-tag-child"
                    :class="tab === 'queue' ? childAccentActive() : ''"
                    x-on:click="setTab('queue')"
                >
                    <x-filament::icon icon="heroicon-o-queue-list" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                    <span>Hàng đợi</span>
                    <span
                        x-show="pendingFor(activeStats()) > 0"
                        class="smp-tag-count smp-tag-count-sm smp-tag-count-warn"
                        x-text="pendingFor(activeStats())"
                    ></span>
                </button>
            </div>
        </div>

        <style>
            .smp-tag-nav .smp-tag {
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

            .smp-tag-nav .smp-tag-parent {
                padding: 0.45rem 0.95rem;
                font-size: 0.875rem;
                color: #fff;
                background: rgb(31 41 55 / 0.55);
                border-color: rgb(75 85 99 / 0.45);
            }

            .smp-tag-nav .smp-tag-parent:hover {
                color: rgb(229 231 235);
                background: rgb(55 65 81 / 0.65);
            }

            .smp-tag-nav .smp-tag-ig.is-active {
                color: #fff;
                border-color: transparent;
                background: linear-gradient(135deg, #833ab4 0%, #c13584 45%, #e1306c 100%);
                box-shadow: 0 0 0 1px rgb(192 38 211 / 0.35), 0 4px 14px rgb(131 58 180 / 0.35);
            }

            .smp-tag-nav .smp-tag-fb.is-active {
                color: #fff;
                border-color: transparent;
                background: linear-gradient(135deg, #1877f2 0%, #0d65d9 100%);
                box-shadow: 0 0 0 1px rgb(59 130 246 / 0.35), 0 4px 14px rgb(24 119 242 / 0.35);
            }

            .smp-tag-nav .smp-tag-pin.is-active {
                color: #fff;
                border-color: transparent;
                background: linear-gradient(135deg, #e60023 0%, #bd081c 100%);
                box-shadow: 0 0 0 1px rgb(239 68 68 / 0.35), 0 4px 14px rgb(230 0 35 / 0.35);
            }

            .smp-tag-nav .smp-tag-children {
                min-height: 2rem;
                margin-bottom: 10px !important;
            }

            .smp-tag-nav .smp-tag-child {
                padding: 0.3rem 0.75rem;
                font-size: 0.8125rem;
                font-weight: 500;
                color: #fff;
                background: rgb(17 24 39 / 0.45);
                border-color: rgb(55 65 81 / 0.6);
            }

            .smp-tag-nav .smp-tag-child:hover {
                color: rgb(229 231 235);
                background: rgb(31 41 55 / 0.75);
            }

            .smp-tag-nav .smp-child-active-ig {
                color: #fff;
                background: rgb(131 58 180 / 0.22);
                border-color: rgb(192 38 211 / 0.55);
            }

            .smp-tag-nav .smp-child-active-fb {
                color: #fff;
                background: rgb(24 119 242 / 0.2);
                border-color: rgb(59 130 246 / 0.55);
            }

            .smp-tag-nav .smp-child-active-pin {
                color: #fff;
                background: rgb(230 0 35 / 0.2);
                border-color: rgb(239 68 68 / 0.55);
            }

            .smp-tag-nav .smp-tag-count {
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

            .smp-tag-nav .smp-tag-count-sm {
                font-size: 0.625rem;
                min-width: 1.1rem;
                padding: 0.05rem 0.35rem;
            }

            .smp-tag-nav .smp-tag-count-warn {
                background: rgb(245 158 11 / 0.9);
                color: #fff;
            }
        </style>

        {{-- Chỉ render form của platform đang chọn — tránh xung đột upload Livewire --}}
        <div x-show="tab === 'compose'" x-cloak>
            <div wire:poll.30s="saveFormDraft" class="hidden" aria-hidden="true"></div>

            @if ($activePlatform === 'instagram')
                <div wire:key="compose-instagram-form">
                    {{ $this->instagramForm }}
                </div>
            @elseif ($activePlatform === 'pinterest')
                <div wire:key="compose-pinterest-form">
                    {{ $this->pinterestForm }}
                </div>
            @else
                <div wire:key="compose-facebook-form">
                    {{ $this->facebookForm }}
                </div>
            @endif
        </div>

        {{-- Hàng đợi: poll Livewire mỗi 10s (badge Alpine + bảng), không reset phân trang --}}
        <div
            x-show="tab === 'queue'"
            x-cloak
            class="space-y-4"
            data-smp-queue-panel
        >
            @if ($activeTab === 'queue')
                <div wire:poll.10s="pollQueueDisplay" class="hidden" aria-hidden="true"></div>
            @endif

            <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="fi-section-header-heading text-sm font-semibold">
                            Hàng đợi <span x-text="platform === 'facebook' ? 'Facebook' : (platform === 'pinterest' ? 'Pinterest' : 'Instagram')"></span>
                        </span>
                        <x-filament::badge color="gray" size="sm">
                            Thủ công: <span x-text="activeInterval()"></span> phút/bài
                        </x-filament::badge>
                        <template x-if="activeAutoEnabled()">
                            <x-filament::badge color="primary" size="sm">
                                Auto: <span x-text="activeAutoInterval()"></span> phút/bài
                            </x-filament::badge>
                        </template>
                        <template x-if="activeAutoEnabled() && activeAutoPaused()">
                            <x-filament::badge color="warning" size="sm">
                                Auto tạm dừng (hàng đợi thủ công đang chạy)
                            </x-filament::badge>
                        </template>
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

            @if ($activeTab === 'queue')
                <div wire:loading.remove wire:target="switchPlatform,switchTab">
                    {{ $this->table }}
                </div>
            @endif
        </div>
    </div>

    {{-- Modals ngoài vùng x-show; đặt cuối để không chiếm khoảng trắng phía trên --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
