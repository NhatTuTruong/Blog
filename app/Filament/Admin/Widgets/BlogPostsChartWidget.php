<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Blog;
use App\Support\BlogStatsDateRange;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class BlogPostsChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Bài viết mới theo ngày';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'xl' => 2,
    ];

    protected static ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $range = BlogStatsDateRange::fromFilters($this->filters);
        $keys = $range->dayKeys();
        $labels = $range->dayLabels();

        $query = Blog::query();
        $range->applyTo($query);

        $driver = DB::connection()->getDriverName();
        $dateExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', created_at)"
            : 'DATE(created_at)';

        $counts = (clone $query)
            ->selectRaw("{$dateExpr} as day_key, COUNT(*) as total")
            ->groupBy('day_key')
            ->pluck('total', 'day_key');

        $viewSums = (clone $query)
            ->selectRaw("{$dateExpr} as day_key, SUM(views_count) as total_views")
            ->groupBy('day_key')
            ->pluck('total_views', 'day_key');

        $postData = [];
        $viewData = [];

        foreach ($keys as $key) {
            $postData[] = (int) ($counts[$key] ?? 0);
            $viewData[] = (int) ($viewSums[$key] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Bài viết mới',
                    'data' => $postData,
                    'borderColor' => 'rgb(245, 158, 11)',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Lượt xem (bài tạo trong ngày)',
                    'data' => $viewData,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.08)',
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
                'y1' => [
                    'position' => 'right',
                    'beginAtZero' => true,
                    'grid' => ['drawOnChartArea' => false],
                    'ticks' => ['precision' => 0],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => true],
            ],
        ];
    }
}
