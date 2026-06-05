<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\BlogCategoryChartWidget;
use App\Filament\Admin\Widgets\BlogPostsChartWidget;
use App\Filament\Admin\Widgets\BlogStatsOverviewWidget;
use App\Filament\Admin\Widgets\TopBlogPostsWidget;
use App\Support\BlogStatsDateRange;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $navigationLabel = 'Bảng điều khiển';

    protected static ?string $title = 'Bảng điều khiển';

    public function mount(): void
    {
        $this->mountHasFilters();

        if (blank($this->filters['period'] ?? null)) {
            $this->filters = [
                'period' => BlogStatsDateRange::PERIOD_THIS_MONTH,
                'startDate' => null,
                'endDate' => null,
            ];

            $this->getFiltersForm()->fill($this->filters);
        }
    }

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Bộ lọc thời gian')
                    ->description('Áp dụng cho toàn bộ thống kê bên dưới.')
                    ->schema([
                        Select::make('period')
                            ->label('Khoảng thời gian')
                            ->options(BlogStatsDateRange::periodOptions())
                            ->default(BlogStatsDateRange::PERIOD_THIS_MONTH)
                            ->native(false)
                            ->live(),
                        DatePicker::make('startDate')
                            ->label('Từ ngày')
                            ->visible(fn (Get $get): bool => $get('period') === BlogStatsDateRange::PERIOD_CUSTOM)
                            ->maxDate(fn (Get $get) => $get('endDate')),
                        DatePicker::make('endDate')
                            ->label('Đến ngày')
                            ->visible(fn (Get $get): bool => $get('period') === BlogStatsDateRange::PERIOD_CUSTOM)
                            ->minDate(fn (Get $get) => $get('startDate')),
                    ])
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 3,
                    ])
                    ->compact(),
            ]);
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            BlogStatsOverviewWidget::class,
            BlogPostsChartWidget::class,
            BlogCategoryChartWidget::class,
            TopBlogPostsWidget::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 3,
        ];
    }
}
