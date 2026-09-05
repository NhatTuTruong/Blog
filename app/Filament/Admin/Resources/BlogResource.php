<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BlogResource\Pages;
use App\Models\Blog;
use App\Models\User;
use App\Support\BlogCategorySelection;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BlogResource extends Resource
{
    protected static ?string $model = Blog::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Quản lý Bài viết';

    protected static ?string $modelLabel = 'Bài viết';

    protected static ?string $pluralModelLabel = 'Bài viết';

    protected static ?string $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();

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
                Forms\Components\Section::make('Thông tin bài viết')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Tiêu đề')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if (filled($state)) {
                                    $set('slug', \Illuminate\Support\Str::slug($state));
                                }
                            }),
                        Forms\Components\Select::make('blogCategories')
                            ->label('Danh mục')
                            ->relationship(
                                name: 'blogCategories',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->placeholder('Chọn danh mục (tùy chọn)')
                            ->helperText('Có thể chọn nhiều danh mục. Bài sẽ hiển thị trong tất cả danh mục đã chọn.')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                $ids = BlogCategorySelection::normalizeIds(is_array($state) ? $state : null);
                                $set('blog_category_id', $ids[0] ?? null);
                                $set('category', BlogCategorySelection::labelForIds($ids) ?? null);
                            })
                            ->columnSpan(2),
                        Forms\Components\Hidden::make('blog_category_id'),
                        Forms\Components\Hidden::make('category'),
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->whereNull('deleted_at'))
                            ->helperText('URL thân thiện, tự động tạo từ tiêu đề'),
                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Ngày đăng')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->seconds(false)
                            ->displayFormat('d/m/Y H:i')
                            ->helperText('Ngày hiển thị trên website và thứ tự sắp xếp bài viết.'),
                        Forms\Components\Toggle::make('is_published')
                            ->label('Xuất bản')
                            ->default(true)
                            ->helperText('Chỉ bài đã xuất bản mới hiển thị trên trang chủ'),
                        Forms\Components\TextInput::make('priority')
                            ->label('Độ ưu tiên')
                            ->helperText('Số lớn hơn hiển thị trước ở Featured Story/Trending Posts. Mặc định 1.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(255)
                            ->default(1)
                            ->step(1)
                            ->columnSpan(1),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Nội dung')
                    ->schema([
                        Forms\Components\RichEditor::make('content')
                            ->label('Nội dung')
                            ->live(debounce: 2000)
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strikeThrough',
                                'link',
                                'image',
                                'orderedList',
                                'bulletList',
                                'blockquote',
                                'codeBlock',
                                'undo',
                                'redo',
                            ])
                            ->columnSpanFull()
                            ->extraInputAttributes(['style' => 'min-height: 300px;']),
                    ]),
                Forms\Components\Section::make('Ảnh & Video')
                    ->schema([
                        Forms\Components\FileUpload::make('featured_image')
                            ->label('Ảnh đại diện')
                            ->image()
                            ->directory('blogs/featured')
                            ->maxSize(5120)
                            ->helperText('Để trống sẽ dùng ảnh trong public/categories/{slug-danh-muc}.jpg (ví dụ tech.jpg); không có thì ảnh upload danh mục hoặc default.jpg.'),
                        Forms\Components\FileUpload::make('images')
                            ->label('Ảnh bổ sung')
                            ->image()
                            ->directory('blogs/images')
                            ->multiple()
                            ->maxFiles(20)
                            ->maxSize(5120)
                            ->reorderable()
                            ->helperText('Thêm nhiều ảnh minh họa cho bài viết'),
                        Forms\Components\FileUpload::make('videos')
                            ->label('Video')
                            ->directory('blogs/videos')
                            ->multiple()
                            ->maxFiles(5)
                            ->maxSize(102400)
                            ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/ogg'])
                            ->helperText('Hỗ trợ MP4, WebM, OGG (tối đa 5 video)')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(fn (string $operation) => $operation === 'edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Chưa có bài viết')
            ->emptyStateDescription('Tạo bài thủ công hoặc dùng AI để bắt đầu.')
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image')
                    ->label('Ảnh')
                    ->disk('public')
                    ->size(40)
                    ->state(fn (Blog $record): ?string => $record->hasStoredFeaturedImage() ? $record->featured_image : null)
                    ->defaultImageUrl(fn (Blog $record): string => $record->featured_image_url)
                    ->circular(false),
                Tables\Columns\TextColumn::make('title')
                    ->label('Tiêu đề')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('category_labels')
                    ->label('Danh mục')
                    ->getStateUsing(fn (Blog $record): string => $record->category_labels)
                    ->limit(30),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->limit(25)
                    ->hidden(),
                Tables\Columns\ToggleColumn::make('is_published')
                    ->label('Xuất bản')
                    ->tooltip('Bật/tắt để xuất bản hoặc chuyển về bản nháp')
                    ->sortable(),
                Tables\Columns\TextColumn::make('views_count')
                    ->label('Lượt xem')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->default(0),
                Tables\Columns\TextInputColumn::make('priority')
                    ->label('Ưu tiên')
                    ->type('number')
                    ->inputMode('numeric')
                    ->step(1)
                    ->extraInputAttributes(['min' => 0, 'max' => 255])
                    ->width(100)
                    ->default(1)
                    ->sortable()
                    ->rules(['integer', 'min:0', 'max:255'])
                    ->tooltip('Số lớn = hiển thị trước ở trang chủ. Mặc định 1.')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày đăng')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('blogCategories')
                    ->label('Danh mục')
                    ->relationship('blogCategories', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('is_published')
                    ->label('Trạng thái')
                    ->options([
                        true => 'Đã xuất bản',
                        false => 'Bản nháp',
                    ]),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('')
                    ->icon('heroicon-o-eye')
                    ->tooltip('Xem trước')
                    ->url(fn (Blog $record) => route('blog.show', $record->slug))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\ReplicateAction::make()
                    ->label('')
                    ->icon('heroicon-o-document-duplicate')
                    ->tooltip('Sao chép')
                    ->mutateRecordDataUsing(function (array $data, Blog $record): array {
                        $baseTitle = $record->title;
                        $baseSlug = $record->slug;
                        $n = 1;
                        // Luôn thêm số vào slug để tránh trùng với slug gốc
                        do {
                            $title = $baseTitle . ' - Copy' . ($n > 1 ? ' ' . $n : '');
                            $slug = $baseSlug . '-copy' . $n;
                            $exists = Blog::withoutGlobalScopes()
                                ->where('slug', $slug)
                                ->where('id', '!=', $record->id)
                                ->exists();
                            $n++;
                        } while ($exists);
                        $data['title'] = $title;
                        $data['slug'] = $slug;
                        $data['is_published'] = false; // Mặc định là bản nháp khi sao chép
                        return $data;
                    }),
                Tables\Actions\DeleteAction::make()->label(''),
                Tables\Actions\RestoreAction::make()->label(''),
                Tables\Actions\ForceDeleteAction::make()->label(''),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label(''),
                    Tables\Actions\RestoreBulkAction::make()->label(''),
                    Tables\Actions\ForceDeleteBulkAction::make()->label(''),
                ]),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('blogCategories');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogs::route('/'),
            'create' => Pages\CreateBlog::route('/create'),
            'edit' => Pages\EditBlog::route('/{record}/edit'),
        ];
    }
}
