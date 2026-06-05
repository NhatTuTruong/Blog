<x-filament-widgets::widget class="fi-wi-campaigns-map">
    <x-filament::section>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Biểu đồ phân bố theo danh mục -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Phân bố theo danh mục
                </h3>
                <div class="space-y-3">
                    @forelse($campaignsByCategory as $category => $count)
                        @php
                            $percentage = $totalCampaigns > 0 
                                ? round(($count / $totalCampaigns) * 100, 1) 
                                : 0;
                        @endphp
                        <div class="space-y-1">
                            <div class="flex justify-between text-sm">
                                <span class="font-medium text-gray-700 dark:text-gray-300">
                                    {{ $category ?: 'Chưa phân loại' }}
                                </span>
                                <span class="text-gray-600 dark:text-gray-400">
                                    {{ $count }} ({{ $percentage }}%)
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                                <div 
                                    class="bg-primary-600 h-2.5 rounded-full transition-all duration-300"
                                    style="width: {{ $percentage }}%"
                                ></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Chưa có dữ liệu</p>
                    @endforelse
                </div>
            </div>

            <!-- Top cửa hàng -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Top cửa hàng có nhiều chiến dịch
                </h3>
                <div class="space-y-3">
                    @forelse($campaignsByBrand as $index => $brand)
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                                    <span class="text-sm font-semibold text-primary-600 dark:text-primary-400">
                                        {{ $index + 1 }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $brand['name'] }}
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-bold text-primary-600 dark:text-primary-400">
                                    {{ $brand['count'] }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    chiến dịch
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Chưa có dữ liệu</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Bản đồ nhiệt giả lập -->
        <div class="mt-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                Bản đồ nhiệt phân bố chiến dịch
            </h3>
            <div class="relative bg-gradient-to-br from-blue-50 to-purple-50 dark:from-gray-800 dark:to-gray-900 rounded-lg p-6 overflow-hidden">
                <div class="grid grid-cols-4 md:grid-cols-8 gap-4">
                    @php
                        $heatmapData = $campaignsByCategory;
                        $maxValue = !empty($heatmapData) ? max($heatmapData) : 1;
                        $colors = [
                            'bg-blue-100 dark:bg-blue-900',
                            'bg-blue-200 dark:bg-blue-800',
                            'bg-blue-300 dark:bg-blue-700',
                            'bg-blue-400 dark:bg-blue-600',
                            'bg-blue-500 dark:bg-blue-500',
                            'bg-purple-400 dark:bg-purple-600',
                            'bg-purple-500 dark:bg-purple-500',
                            'bg-purple-600 dark:bg-purple-400',
                        ];
                    @endphp
                    @foreach(range(1, 16) as $i)
                        @php
                            $categoryKeys = array_keys($heatmapData);
                            $categoryCount = count($categoryKeys);
                            if ($categoryCount > 0) {
                                $keyIndex = ($i - 1) % $categoryCount;
                                $value = $heatmapData[$categoryKeys[$keyIndex]] ?? 0;
                            } else {
                                $value = 0;
                            }
                            $intensity = $maxValue > 0 ? min(7, floor(($value / $maxValue) * 7)) : 0;
                            $colorClass = $colors[$intensity] ?? $colors[0];
                        @endphp
                        <div class="aspect-square rounded-lg {{ $colorClass }} flex items-center justify-center text-xs font-semibold text-gray-700 dark:text-gray-300 transition-transform hover:scale-110 cursor-pointer" title="{{ $value }} chiến dịch">
                            {{ $value }}
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                    <span>Ít</span>
                    <div class="flex space-x-1">
                        @foreach($colors as $color)
                            <div class="w-4 h-4 rounded {{ $color }}"></div>
                        @endforeach
                    </div>
                    <span>Nhiều</span>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

