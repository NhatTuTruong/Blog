<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Blog;
use App\Support\BlogStatsDateRange;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class BlogCategoryChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Bài viết theo danh mục';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 1;

    protected static ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $range = BlogStatsDateRange::fromFilters($this->filters);

        $rows = $range
            ->applyTo(Blog::query())
            ->select('category', DB::raw('COUNT(*) as total'))
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $labels = $rows->pluck('category')->all();
        $data = $rows->pluck('total')->map(fn ($v) => (int) $v)->all();

        if (empty($labels)) {
            $labels = ['—'];
            $data = [0];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Số bài',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgba(245, 158, 11, 0.85)',
                        'rgba(59, 130, 246, 0.85)',
                        'rgba(16, 185, 129, 0.85)',
                        'rgba(239, 68, 68, 0.85)',
                        'rgba(139, 92, 246, 0.85)',
                        'rgba(236, 72, 153, 0.85)',
                        'rgba(20, 184, 166, 0.85)',
                        'rgba(107, 114, 128, 0.85)',
                    ],
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }
}
