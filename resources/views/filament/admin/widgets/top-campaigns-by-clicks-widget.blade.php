<x-filament-widgets::widget class="fi-wi-top-campaigns-by-clicks">
    <x-filament::section>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            #
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Chiến dịch
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Cửa hàng
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Lượt click
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($campaigns as $index => $campaign)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $index + 1 }}
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ \App\Filament\Admin\Resources\CampaignResource::getUrl('edit', ['record' => $campaign['id']]) }}"
                                   class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                    {{ \Illuminate\Support\Str::limit($campaign['title'], 40) }}
                                </a>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                {{ $campaign['brand'] }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                <span class="text-sm font-semibold text-green-600 dark:text-green-400">
                                    {{ number_format($campaign['clicks']) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                Chưa có dữ liệu
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
