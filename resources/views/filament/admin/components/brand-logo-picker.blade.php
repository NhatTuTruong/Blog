@php
    $images = $images ?? [];
    $images = is_array($images) ? $images : [];
@endphp

<div x-data="{
    open: false,
    selectedPath: null,
    filter: 'all',
    get filteredImages() {
        const imgs = @js($images);
        if (this.filter === 'all') return imgs;
        const now = Math.floor(Date.now() / 1000);
        const todayStart = Math.floor(new Date().setHours(0,0,0,0) / 1000);
        const weekAgo = todayStart - (7 * 24 * 60 * 60);
        return imgs.filter(item => {
            const ts = item.lastModified || 0;
            if (this.filter === 'today') return ts >= todayStart;
            if (this.filter === '7days') return ts >= weekAgo;
            return true;
        });
    },
    selectImage(path) {
        if (!path) return;
        $wire.selectLogoAndClose(path);
        $dispatch('close-modal');
        this.open = false;
        this.selectedPath = null;
    }
}" x-cloak>
    <button type="button"
            @click="$wire.refreshLibrary().then(() => { open = true })"
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
         style="display: none;"
         @close-modal.window="open = false">
        <div @click.self="open = false"
             class="relative w-full max-w-4xl max-h-[90vh] overflow-hidden rounded-xl bg-white dark:bg-gray-800 shadow-xl flex flex-col">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Chọn ảnh đã upload</h3>
                <button type="button"
                        @click="open = false"
                        class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 focus:ring-2 focus:ring-primary-500">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700 flex gap-2">
                <button type="button"
                        @click="filter = 'all'"
                        :class="filter === 'all' ? 'bg-primary-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium transition">
                    Tất cả
                </button>
                <button type="button"
                        @click="filter = '7days'"
                        :class="filter === '7days' ? 'bg-primary-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium transition">
                    7 ngày
                </button>
                <button type="button"
                        @click="filter = 'today'"
                        :class="filter === 'today' ? 'bg-primary-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium transition">
                    Hôm nay
                </button>
            </div>
            <div class="overflow-y-auto p-4 flex-1 min-h-0" style="max-height: 50vh;">
                <template x-if="filteredImages.length === 0">
                    <p class="text-sm text-gray-500 dark:text-gray-400 py-8 text-center">Không có ảnh phù hợp với bộ lọc.</p>
                </template>
                <template x-if="filteredImages.length > 0">
                    <div class="grid grid-cols-6 gap-3" style="grid-template-columns: repeat(6, minmax(0, 1fr));">
                        <template x-for="item in filteredImages" :key="item.path">
                            <button type="button"
                                    :data-path="item.path"
                                    class="brand-logo-picker-item rounded-lg border-2 p-1 bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-700 hover:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition"
                                    :class="{ 'border-primary-500 ring-2 ring-primary-500': selectedPath === item.path }"
                                    @click="selectedPath = selectedPath === item.path ? null : item.path"
                                    @dblclick="selectImage(item.path)">
                                <img :src="item.url"
                                     alt=""
                                     class="w-full aspect-square object-contain rounded"
                                     loading="lazy" />
                            </button>
                        </template>
                    </div>
                </template>
            </div>
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-2">
                <button type="button"
                        @click="open = false"
                        class="fi-btn relative grid-flow-col whitespace-nowrap fi-btn-color-gray fi-btn-outlined fi-btn-size-sm">
                    Đóng
                </button>
                <template x-if="selectedPath">
                    <button type="button"
                            @click="selectImage(selectedPath)"
                            class="fi-btn relative grid-flow-col whitespace-nowrap fi-btn-color-primary fi-btn-size-sm">
                        Chọn ảnh này
                    </button>
                </template>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
    .brand-logo-picker-item img { pointer-events: none; }
</style>
