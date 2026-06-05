<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\BlogResource;
use App\Models\Blog;
use App\Support\BlogStatsDateRange;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Number;

class TopBlogPostsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Top bài viết theo lượt xem';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $range = BlogStatsDateRange::fromFilters($this->filters);

        return $table
            ->query(
                fn () => $range
                    ->applyTo(Blog::query())
                    ->orderByDesc('views_count')
                    ->orderByDesc('created_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Tiêu đề')
                    ->searchable()
                    ->limit(50)
                    ->url(fn (Blog $record): string => BlogResource::getUrl('edit', ['record' => $record])),
                Tables\Columns\TextColumn::make('category')
                    ->label('Danh mục')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('views_count')
                    ->label('Lượt xem')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => Number::format((int) $state)),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('Xuất bản')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->paginated(false)
            ->defaultSort('views_count', 'desc')
            ->emptyStateHeading('Chưa có bài viết trong khoảng thời gian này');
    }
}
