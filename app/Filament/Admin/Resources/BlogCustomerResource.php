<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BlogCustomerResource\Pages;
use App\Models\BlogPostEmailUnlock;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BlogCustomerResource extends Resource
{
    protected static ?string $model = BlogPostEmailUnlock::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Khách hàng';

    protected static ?string $modelLabel = 'Khách hàng';

    protected static ?string $pluralModelLabel = 'Khách hàng';

    protected static ?string $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 7;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('blog');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Thông tin khách hàng')
                    ->schema([
                        Infolists\Components\TextEntry::make('email')
                            ->label('Email')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('country')
                            ->label('Quốc gia truy cập')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('ip_address')
                            ->label('Địa chỉ IP')
                            ->placeholder('—')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Thời gian nhập email')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Bài viết')
                    ->schema([
                        Infolists\Components\TextEntry::make('blog.title')
                            ->label('Tên bài viết')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('blog_url')
                            ->label('Liên kết bài viết')
                            ->getStateUsing(fn (BlogPostEmailUnlock $record): ?string => $record->blog?->publicUrl())
                            ->url(fn (BlogPostEmailUnlock $record): ?string => $record->blog?->publicUrl())
                            ->openUrlInNewTab()
                            ->placeholder('—'),
                    ])
                    ->columns(1),
                Infolists\Components\Section::make('Thiết bị')
                    ->schema([
                        Infolists\Components\TextEntry::make('user_agent')
                            ->label('User-Agent')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('country')
                    ->label('Quốc gia')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('blog.title')
                    ->label('Bài viết')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->url(fn (BlogPostEmailUnlock $record): ?string => $record->blog?->publicUrl())
                    ->openUrlInNewTab()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Thời gian')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('country')
                    ->label('Quốc gia')
                    ->options(fn (): array => BlogPostEmailUnlock::query()
                        ->whereNotNull('country')
                        ->where('country', '!=', '')
                        ->distinct()
                        ->orderBy('country')
                        ->pluck('country', 'country')
                        ->all()),
                Tables\Filters\SelectFilter::make('blog_id')
                    ->label('Bài viết')
                    ->relationship('blog', 'title')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label(''),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogCustomers::route('/'),
            'view' => Pages\ViewBlogCustomer::route('/{record}'),
        ];
    }
}
