<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BlogDealResource\Pages;
use App\Models\BlogDeal;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BlogDealResource extends Resource
{
    protected static ?string $model = BlogDeal::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Quản lý Deal';

    protected static ?string $modelLabel = 'Deal';

    protected static ?string $pluralModelLabel = 'Deals';

    protected static ?string $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Thông tin deal')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Tiêu đề')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->label('Mô tả')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('coupon_code')
                            ->label('Mã coupon')
                            ->maxLength(100)
                            ->helperText('Để trống = Discount Deal. Có mã = Coupon Code.'),
                        Forms\Components\TextInput::make('shop_url')
                            ->label('Link cửa hàng')
                            ->url()
                            ->required()
                            ->maxLength(2048),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Thứ tự hiển thị')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Thứ tự trên trang Deals (số nhỏ hiển thị trước).'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Gắn bài viết')
                    ->description('Một deal có thể gắn cho nhiều bài viết review.')
                    ->schema([
                        Forms\Components\Select::make('blogs')
                            ->label('Bài viết')
                            ->relationship(
                                name: 'blogs',
                                titleAttribute: 'title',
                                modifyQueryUsing: fn ($query) => $query->orderByDesc('created_at'),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Chưa có deal')
            ->emptyStateDescription('Tạo deal mới và gắn vào các bài viết review.')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Tiêu đề')
                    ->searchable()
                    ->sortable()
                    ->limit(45),
                Tables\Columns\TextColumn::make('type_label')
                    ->label('Loại')
                    ->badge()
                    ->state(fn (BlogDeal $record): string => $record->typeLabel())
                    ->color(fn (BlogDeal $record): string => $record->isCouponDeal() ? 'success' : 'info'),
                Tables\Columns\TextColumn::make('coupon_code')
                    ->label('Mã')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('blogs_count')
                    ->label('Số bài viết')
                    ->counts('blogs')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('shop_url')
                    ->label('Link shop')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Thứ tự')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Cập nhật')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Loại deal')
                    ->options([
                        'coupon' => 'Coupon Codes',
                        'discount' => 'Discount Deals',
                    ])
                    ->query(function ($query, array $data) {
                        return match ($data['value'] ?? null) {
                            'coupon' => $query->whereNotNull('coupon_code')->where('coupon_code', '!=', ''),
                            'discount' => $query->where(function ($inner) {
                                $inner->whereNull('coupon_code')->orWhere('coupon_code', '');
                            }),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(''),
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
            'index' => Pages\ListBlogDeals::route('/'),
            'create' => Pages\CreateBlogDeal::route('/create'),
            'edit' => Pages\EditBlogDeal::route('/{record}/edit'),
        ];
    }
}
