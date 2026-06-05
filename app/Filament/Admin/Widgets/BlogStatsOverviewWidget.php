<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Support\BlogStatsDateRange;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class BlogStatsOverviewWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $range = BlogStatsDateRange::fromFilters($this->filters);
        $base = Blog::query();
        $scoped = (clone $base);
        $range->applyTo($scoped);

        $total = (clone $scoped)->count();
        $published = (clone $scoped)->where('is_published', true)->count();
        $draft = max(0, $total - $published);
        $views = (int) (clone $scoped)->sum('views_count');
        $avgViews = $total > 0 ? round($views / $total, 1) : 0;

        $categoriesActive = BlogCategory::query()->where('is_active', true)->count();

        $prev = $range->previous();
        $prevTotal = $prev
            ? $prev->applyTo(Blog::query())->count()
            : null;

        $totalDesc = $range->label();
        if ($prevTotal !== null && $prevTotal > 0) {
            $change = round((($total - $prevTotal) / $prevTotal) * 100, 1);
            $sign = $change >= 0 ? '+' : '';
            $totalDesc .= " · {$sign}{$change}% so với kỳ trước";
        }

        return [
            Stat::make('Bài viết', Number::format($total))
                ->description($totalDesc)
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),
            Stat::make('Đã xuất bản', Number::format($published))
                ->description('Trong khoảng đã chọn')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('Chưa xuất bản', Number::format($draft))
                ->description('Nháp / ẩn')
                ->descriptionIcon('heroicon-m-eye-slash')
                ->color('warning'),
            Stat::make('Lượt xem', Number::format($views))
                ->description('Tổng lượt xem các bài tạo trong kỳ')
                ->descriptionIcon('heroicon-m-eye')
                ->color('info'),
            Stat::make('TB lượt xem / bài', (string) $avgViews)
                ->description('Trung bình trong kỳ')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('gray'),
            Stat::make('Danh mục đang hiển thị', Number::format($categoriesActive))
                ->description('Trên website (không lọc theo ngày)')
                ->descriptionIcon('heroicon-m-tag')
                ->color('success'),
        ];
    }
}
