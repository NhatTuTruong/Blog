<?php

namespace App\Filament\Admin\Pages;

use App\Support\AdminSettings;
use App\Support\MailSettings;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SystemSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.admin.pages.system-settings';

    protected static ?string $navigationLabel = 'Cài đặt hệ thống';

    protected static ?string $title = 'Cài đặt hệ thống';

    protected static ?string $navigationGroup = 'Cài đặt';

    protected static ?int $navigationSort = 9999;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return (bool) ($user && method_exists($user, 'isAdmin') && $user->isAdmin());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill([
            'site_contact_email' => (string) AdminSettings::get('site_contact_email', config('mail.from.address', 'contact@example.com')),
            'auto_blog_enabled' => (bool) AdminSettings::get('auto_blog_enabled', true),
            'auto_blog_daily_count' => (int) AdminSettings::get('auto_blog_daily_count', 2),
            'auto_blog_window_start_hour' => (int) AdminSettings::get('auto_blog_window_start_hour', 6),
            'auto_blog_window_end_hour' => (int) AdminSettings::get('auto_blog_window_end_hour', 18),
            'auto_blog_variant_best' => (bool) AdminSettings::get('auto_blog_variant_best', true),
            'auto_blog_variant_guide' => (bool) AdminSettings::get('auto_blog_variant_guide', true),
            'auto_blog_variant_comparison' => (bool) AdminSettings::get('auto_blog_variant_comparison', true),
            'auto_blog_queue_interval_minutes' => (int) AdminSettings::get('auto_blog_queue_interval_minutes', 10),
            'seo_title_suffix' => (string) AdminSettings::get('seo_title_suffix', '- ' . config('app.name')),
            'seo_meta_description_default' => (string) AdminSettings::get('seo_meta_description_default', 'Latest articles and insights from our blog.'),
            'seo_og_image_default' => (string) AdminSettings::get('seo_og_image_default', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Auto Blog')
                    ->description('Thiết lập site-wide cho cron tạo blog. Gemini API key (Đăng bài viết tự động) cấu hình tại «Cài đặt tích hợp» → AI Content (Gemini).')
                    ->schema([
                        Toggle::make('auto_blog_enabled')
                            ->label('Bật Auto Blog')
                            ->inline(false),
                        TextInput::make('auto_blog_daily_count')
                            ->label('Số bài/ngày')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('auto_blog_window_start_hour')
                            ->label('Giờ bắt đầu (0-23)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(23)
                            ->required(),
                        TextInput::make('auto_blog_window_end_hour')
                            ->label('Giờ kết thúc (0-23)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(23)
                            ->required(),
                        Toggle::make('auto_blog_variant_best')
                            ->label('Bật variant: Bài viết lựa chọn tốt nhất')
                            ->inline(false),
                        Toggle::make('auto_blog_variant_guide')
                            ->label('Bật variant: Bài viết hướng dẫn')
                            ->inline(false),
                        Toggle::make('auto_blog_variant_comparison')
                            ->label('Bật variant: Bài viết so sánh')
                            ->inline(false),
                        TextInput::make('auto_blog_queue_interval_minutes')
                            ->label('Khoảng cách đăng hàng đợi (phút)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->default(10)
                            ->helperText('Áp dụng cho trang «Đăng bài viết tự động».')
                            ->required(),
                    ])
                    ->columns(3),
                Section::make('SEO mặc định')
                    ->description('Áp dụng cho các trang dùng layout chính.')
                    ->schema([
                        TextInput::make('seo_title_suffix')
                            ->label('Title suffix')
                            ->helperText('Ví dụ: - ' . config('app.name'))
                            ->maxLength(120),
                        TextInput::make('seo_meta_description_default')
                            ->label('Meta description fallback')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('seo_og_image_default')
                            ->label('OpenGraph image mặc định (URL)')
                            ->url()
                            ->maxLength(500),
                    ])
                    ->columns(1),
                Section::make('Thiết lập chung')
                    ->schema([
                        TextInput::make('site_contact_email')
                            ->label('Email liên hệ hiển thị ở trang Contact')
                            ->email()
                            ->required()
                            ->maxLength(255),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Lưu cài đặt')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        AdminSettings::set('site_contact_email', trim((string) ($data['site_contact_email'] ?? MailSettings::fromAddress())));
        AdminSettings::set('auto_blog_enabled', (bool) ($data['auto_blog_enabled'] ?? true));
        AdminSettings::set('auto_blog_daily_count', max(1, (int) ($data['auto_blog_daily_count'] ?? 2)));
        AdminSettings::set('auto_blog_window_start_hour', max(0, min(23, (int) ($data['auto_blog_window_start_hour'] ?? 6))));
        AdminSettings::set('auto_blog_window_end_hour', max(0, min(23, (int) ($data['auto_blog_window_end_hour'] ?? 18))));
        AdminSettings::set('auto_blog_variant_best', (bool) ($data['auto_blog_variant_best'] ?? true));
        AdminSettings::set('auto_blog_variant_guide', (bool) ($data['auto_blog_variant_guide'] ?? true));
        AdminSettings::set('auto_blog_variant_comparison', (bool) ($data['auto_blog_variant_comparison'] ?? true));
        AdminSettings::set('auto_blog_queue_interval_minutes', max(1, min(1440, (int) ($data['auto_blog_queue_interval_minutes'] ?? 10))));

        AdminSettings::set('seo_title_suffix', trim((string) ($data['seo_title_suffix'] ?? ('- ' . config('app.name')))));
        AdminSettings::set('seo_meta_description_default', trim((string) ($data['seo_meta_description_default'] ?? 'Latest articles and insights from our blog.')));
        AdminSettings::set('seo_og_image_default', trim((string) ($data['seo_og_image_default'] ?? '')));

        $this->mount();

        Notification::make()
            ->title('Đã lưu cài đặt hệ thống')
            ->success()
            ->send();
    }
}
