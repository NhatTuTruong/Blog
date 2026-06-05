<div x-data="{ open: $wire.entangle('showModal') }" x-cloak>
    <button type="button"
            wire:click="openLibrary"
            class="inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition fi-btn relative grid-flow-col whitespace-nowrap focus:outline-none fi-btn-color-primary fi-btn-size-sm fi-btn-outlined border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 shadow-sm bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-2 focus:ring-primary-500/50">
        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
        <span>Chọn từ thư viện</span>
    </button>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
         style="display: none;">
        <div wire:click="closeModal" class="absolute inset-0"></div>
        <div class="relative w-full max-w-4xl max-h-[90vh] overflow-hidden rounded-xl bg-white dark:bg-gray-800 shadow-xl flex flex-col"
             @click.stop>
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Chọn ảnh đã upload</h3>
                <div class="flex items-center gap-2">
                    <button type="button"
                            wire:click="refreshImages"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-white bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 disabled:opacity-50">
                        <span wire:loading.remove wire:target="refreshImages">↻</span>
                        <span wire:loading wire:target="refreshImages" class="animate-spin">⟳</span>
                        Làm mới
                    </button>
                    <button type="button"
                            wire:click="closeModal"
                            class="rounded-lg p-1.5 text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:ring-2 focus:ring-primary-500">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </div>
            <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700 flex gap-2">
                <button type="button"
                        wire:click="$set('filter', 'all')"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium transition {{ $filter === 'all' ? 'bg-primary-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-white' }}">
                    Tất cả
                </button>
                <button type="button"
                        wire:click="$set('filter', '7days')"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium transition {{ $filter === '7days' ? 'bg-primary-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-white' }}">
                    7 ngày
                </button>
                <button type="button"
                        wire:click="$set('filter', 'today')"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium transition {{ $filter === 'today' ? 'bg-primary-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-white' }}">
                    Hôm nay
                </button>
            </div>
            <div class="overflow-y-auto p-4 flex-1 min-h-0" style="max-height: 50vh;">
                @if(empty($this->filteredImages))
                    <p class="text-sm text-gray-500 dark:text-gray-400 py-8 text-center">Không có ảnh phù hợp với bộ lọc.</p>
                @else
                    <div style="display:grid; grid-template-columns: repeat(8, minmax(0, 1fr)); gap:0.75rem;">
                        @foreach($this->filteredImages as $item)
                            <button type="button"
                                    data-path="{{ e($item['path']) }}"
                                    @click="$wire.selectImage($el.dataset.path)"
                                    class="rounded-lg border-2 p-1 bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-700 hover:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition">
                                <img src="{{ $item['url'] }}"
                                     alt=""
                                     class="w-full aspect-square object-contain rounded pointer-events-none"
                                     loading="lazy" />
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-2">
                <button type="button"
                        wire:click="closeModal"
                        class="fi-btn relative grid-flow-col whitespace-nowrap fi-btn-color-gray fi-btn-outlined fi-btn-size-sm">
                    Đóng
                </button>
            </div>
        </div>
    </div>
</div>
