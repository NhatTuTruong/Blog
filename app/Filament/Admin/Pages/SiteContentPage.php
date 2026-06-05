<?php

namespace App\Filament\Admin\Pages;

use App\Models\SiteContent;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SiteContentPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';

    protected static string $view = 'filament.admin.pages.site-content';

    protected static ?string $navigationLabel = 'Nội dung trang';

    protected static ?string $title = 'Chỉnh sửa nội dung trang';

    protected static ?string $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 2;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();
        return $user && $user->isAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill([
            'maintenance_enabled' => SiteContent::get('maintenance_enabled', false),
            'header_nav' => SiteContent::get('header_nav', SiteContent::defaultHeaderNav()),
            'footer_brand_description' => SiteContent::get('footer_brand_description', 'Articles, guides and stories — updated regularly.'),
            'footer_columns' => SiteContent::get('footer_columns', SiteContent::defaultFooterColumns()),
            'footer_copyright' => SiteContent::get('footer_copyright', '© ' . date('Y') . ' ' . config('app.name') . '. All rights reserved.'),
            'error_404' => SiteContent::get('error_404', SiteContent::defaultErrorContent('404')),
            'error_403' => SiteContent::get('error_403', SiteContent::defaultErrorContent('403')),
            'error_500' => SiteContent::get('error_500', SiteContent::defaultErrorContent('500')),
            'error_503' => SiteContent::get('error_503', SiteContent::defaultErrorContent('503')),
            'page_about_us' => SiteContent::get('page_about_us', SiteContent::defaultPageAboutUs()),
            'page_contact' => SiteContent::get('page_contact', SiteContent::defaultPageContact()),
            'page_privacy' => SiteContent::get('page_privacy', SiteContent::defaultPagePrivacy()),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Tabs')
                    ->tabs([
                        Tabs\Tab::make('Bảo trì')
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->schema([
                                Section::make('Chế độ bảo trì')
                                    ->description('Khi bật, toàn bộ trang web (trừ khu vực Admin) sẽ hiển thị trang thông báo bảo trì.')
                                    ->schema([
                                        Toggle::make('maintenance_enabled')
                                            ->label('Bật chế độ bảo trì')
                                            ->inline(false),
                                        TextInput::make('error_503.title')
                                            ->label('Tiêu đề trang bảo trì')
                                            ->required()
                                            ->maxLength(100),
                                        Textarea::make('error_503.message')
                                            ->label('Nội dung thông báo')
                                            ->rows(3)
                                            ->maxLength(500),
                                    ])
                                    ->columns(1),
                            ]),
                        Tabs\Tab::make('Header')
                            ->icon('heroicon-o-bars-3-bottom-left')
                            ->schema([
                                Section::make('Menu điều hướng (Nav)')
                                    ->description('Các link hiển thị trên header. URL có thể là đường dẫn tương đối (vd: /blog) hoặc tuyệt đối.')
                                    ->schema([
                                        Repeater::make('header_nav')
                                            ->label('')
                                            ->columns(2)
                                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                            ->addActionLabel('Thêm link')
                                            ->schema([
                                                TextInput::make('label')->label('Nhãn')->required()->maxLength(100),
                                                TextInput::make('url')->label('URL')->required()->maxLength(500)->placeholder('/blog hoặc https://...'),
                                            ]),
                                    ]),
                            ]),
                        Tabs\Tab::make('Footer')
                            ->icon('heroicon-o-squares-2x2')
                            ->schema([
                                Section::make('Phần thương hiệu')
                                    ->schema([
                                        Textarea::make('footer_brand_description')
                                            ->label('Mô tả dưới logo')
                                            ->rows(3)
                                            ->maxLength(500)
                                            ->columnSpanFull(),
                                        TextInput::make('footer_copyright')
                                            ->label('Dòng bản quyền (footer bottom)')
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                    ]),
                                Section::make('Các cột footer')
                                    ->description('Mỗi cột có tiêu đề và danh sách link.')
                                    ->schema([
                                        Repeater::make('footer_columns')
                                            ->label('')
                                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'Cột mới')
                                            ->addActionLabel('Thêm cột')
                                            ->schema([
                                                TextInput::make('title')->label('Tiêu đề cột')->required()->maxLength(100),
                                                Repeater::make('links')
                                                    ->label('Links')
                                                    ->columns(2)
                                                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                                    ->addActionLabel('Thêm link')
                                                    ->schema([
                                                        TextInput::make('label')->label('Nhãn')->required()->maxLength(100),
                                                        TextInput::make('url')->label('URL')->required()->maxLength(500)->placeholder('/blog hoặc https://...'),
                                                    ]),
                                            ])
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tabs\Tab::make('Trang báo lỗi')
                            ->icon('heroicon-o-exclamation-triangle')
                            ->schema([
                                Section::make('404 - Trang không tồn tại')
                                    ->schema([
                                        TextInput::make('error_404.title')->label('Tiêu đề')->required()->maxLength(100),
                                        Textarea::make('error_404.message')->label('Nội dung')->rows(2)->maxLength(500),
                                    ])->columns(1),
                                Section::make('403 - Không có quyền truy cập')
                                    ->schema([
                                        TextInput::make('error_403.title')->label('Tiêu đề')->required()->maxLength(100),
                                        Textarea::make('error_403.message')->label('Nội dung')->rows(2)->maxLength(500),
                                    ])->columns(1),
                                Section::make('500 - Lỗi máy chủ')
                                    ->schema([
                                        TextInput::make('error_500.title')->label('Tiêu đề')->required()->maxLength(100),
                                        Textarea::make('error_500.message')->label('Nội dung')->rows(2)->maxLength(500),
                                    ])->columns(1),
                                Section::make('503 - Bảo trì')
                                    ->schema([
                                        TextInput::make('error_503.title')->label('Tiêu đề')->required()->maxLength(100),
                                        Textarea::make('error_503.message')->label('Nội dung')->rows(2)->maxLength(500),
                                    ])->columns(1),
                            ]),
                        Tabs\Tab::make('About, Contact, Policy')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('About Us')
                                    ->description('Nội dung trang /about')
                                    ->schema([
                                        RichEditor::make('page_about_us')
                                            ->label('Nội dung About Us')
                                            ->toolbarButtons([
                                                'bold', 'italic', 'underline', 'strikeThrough', 'link', 'image',
                                                'orderedList', 'bulletList', 'blockquote', 'codeBlock', 'undo', 'redo',
                                            ])
                                            ->columnSpanFull()
                                            ->extraInputAttributes(['style' => 'min-height: 280px;']),
                                    ]),
                                Section::make('Contact')
                                    ->description('Nội dung trang /contact. Dùng [SITE_EMAIL] để hiển thị email site.')
                                    ->schema([
                                        RichEditor::make('page_contact')
                                            ->label('Nội dung Contact')
                                            ->toolbarButtons([
                                                'bold', 'italic', 'underline', 'strikeThrough', 'link', 'image',
                                                'orderedList', 'bulletList', 'blockquote', 'codeBlock', 'undo', 'redo',
                                            ])
                                            ->columnSpanFull()
                                            ->extraInputAttributes(['style' => 'min-height: 180px;']),
                                    ]),
                                Section::make('Privacy Policy')
                                    ->description('Nội dung trang /privacy. Dùng [PRIVACY_DATE] để hiển thị ngày cập nhật.')
                                    ->schema([
                                        RichEditor::make('page_privacy')
                                            ->label('Nội dung Privacy Policy')
                                            ->toolbarButtons([
                                                'bold', 'italic', 'underline', 'strikeThrough', 'link', 'image',
                                                'orderedList', 'bulletList', 'blockquote', 'codeBlock', 'undo', 'redo',
                                            ])
                                            ->columnSpanFull()
                                            ->extraInputAttributes(['style' => 'min-height: 320px;']),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Lưu thay đổi')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        SiteContent::set('maintenance_enabled', $data['maintenance_enabled'] ?? false);
        SiteContent::set('header_nav', $data['header_nav'] ?? []);
        SiteContent::set('footer_brand_description', $data['footer_brand_description'] ?? '');
        SiteContent::set('footer_columns', $data['footer_columns'] ?? []);
        SiteContent::set('footer_copyright', $data['footer_copyright'] ?? '');
        SiteContent::set('error_404', $data['error_404'] ?? []);
        SiteContent::set('error_403', $data['error_403'] ?? []);
        SiteContent::set('error_500', $data['error_500'] ?? []);
        SiteContent::set('error_503', $data['error_503'] ?? []);
        SiteContent::set('page_about_us', $data['page_about_us'] ?? '');
        SiteContent::set('page_contact', $data['page_contact'] ?? '');
        SiteContent::set('page_privacy', $data['page_privacy'] ?? '');

        Notification::make()
            ->title('Đã lưu nội dung Header, Footer, trang lỗi và các trang.')
            ->success()
            ->send();
    }
}
