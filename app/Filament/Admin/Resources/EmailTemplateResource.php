<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Pages\SendTemplatedEmail as SendTemplatedEmailPage;
use App\Filament\Admin\Resources\EmailTemplateResource\Pages;
use App\Models\EmailTemplate;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Mẫu email';

    protected static ?string $modelLabel = 'Mẫu email';

    protected static ?string $pluralModelLabel = 'Mẫu email';

    protected static ?string $navigationGroup = 'Email';

    protected static ?int $navigationSort = 1;

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
                Forms\Components\Section::make('Thông tin mẫu')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Tên mẫu')
                            ->required()
                            ->maxLength(120)
                            ->live(onBlur: true)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('subject')
                            ->label('Tiêu đề email')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->helperText('Có thể dùng biến thể, ví dụ: Chào [tên] — ưu đãi mới')
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Đang sử dụng')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Biến thể (placeholder)')
                    ->description('Trong tiêu đề/nội dung mẫu dùng [tên_biến]. «Nội dung biến thể» sẽ tự điền sang tab Gửi email (có thể sửa trước khi gửi).')
                    ->schema([
                        Forms\Components\Repeater::make('variables')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('key')
                                    ->label('Tên biến')
                                    ->required()
                                    ->maxLength(80)
                                    ->placeholder('email')
                                    ->helperText('Trong mẫu: [email]'),
                                Forms\Components\TextInput::make('default')
                                    ->label('Nội dung biến thể')
                                    ->maxLength(500)
                                    ->placeholder('nhatbui2017@gmail.com')
                                    ->helperText('Giá trị thay [tên_biến] — tự điền khi chọn mẫu ở tab Gửi email')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Thêm biến thể')
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Nội dung email')
                    ->schema([
                        Forms\Components\RichEditor::make('body')
                            ->label('Nội dung')
                            ->required()
                            ->live(debounce: 2000)
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strikeThrough',
                                'link',
                                'orderedList',
                                'bulletList',
                                'h2',
                                'h3',
                                'blockquote',
                                'redo',
                                'undo',
                            ])
                            ->helperText('Gõ [tên_biến] trực tiếp — ví dụ: đây là email [email]. Có thể định dạng chữ, danh sách, liên kết.')
                            ->extraInputAttributes(['style' => 'min-height: 280px;'])
                            ->afterStateHydrated(function (Forms\Components\RichEditor $component, ?string $state): void {
                                if (blank($state)) {
                                    return;
                                }

                                $component->state(EmailTemplate::prepareBodyForEditor($state));
                            })
                            ->dehydrateStateUsing(fn (?string $state): string => EmailTemplate::normalizeBodyFromEditor($state ?? ''))
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Tệp đính kèm')
                    ->description('Tùy chọn. Các file này sẽ tự động đính kèm mỗi khi gửi email theo mẫu này.')
                    ->schema([
                        Forms\Components\FileUpload::make('attachments')
                            ->label('')
                            ->multiple()
                            ->disk('local')
                            ->directory('email-template-attachments')
                            ->visibility('private')
                            ->maxSize(15360)
                            ->maxFiles(5)
                            ->helperText('Tối đa 5 file, mỗi file 15MB (PDF, Word, Excel, ảnh, ZIP, …).')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function formatVariablesColumn(EmailTemplate $record): string
    {
        $defs = $record->variableDefinitions();

        if ($defs === []) {
            return '—';
        }

        return collect($defs)
            ->map(fn (array $v) => '['.$v['key'].']')
            ->implode(', ');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên mẫu')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Tiêu đề')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('variables')
                    ->label('Biến thể')
                    ->formatStateUsing(fn (mixed $state, EmailTemplate $record): string => static::formatVariablesColumn($record))
                    ->wrap(),
                Tables\Columns\TextColumn::make('attachments')
                    ->label('Đính kèm')
                    ->formatStateUsing(fn (mixed $state, EmailTemplate $record): string => (string) count($record->attachmentStoragePaths()))
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Cập nhật')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Đang sử dụng'),
            ])
            ->actions([
                Tables\Actions\Action::make('send')
                    ->label('Gửi')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->url(fn (EmailTemplate $record): string => SendTemplatedEmailPage::urlWithTemplate($record->id)),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailTemplates::route('/'),
            'create' => Pages\CreateEmailTemplate::route('/create'),
            'edit' => Pages\EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
