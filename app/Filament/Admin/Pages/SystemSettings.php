<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Support\IntegrationSettingsForm;
use App\Filament\Concerns\AuthorizesPanelAccess;
use App\Models\User;
use App\Services\IntegrationSettingsPersistence;
use App\Support\AdminSettings;
use App\Support\MailSettings;
use Filament\Actions\Action;
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
    use AuthorizesPanelAccess;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.admin.pages.system-settings';

    protected static ?string $navigationLabel = 'Cài đặt hệ thống';

    protected static ?string $title = 'Cài đặt hệ thống';

    protected static ?string $navigationGroup = 'Cài đặt';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return static::canAccessMemberFeatures();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill($this->loadFormData());
    }

    public function form(Form $form): Form
    {
        $schema = IntegrationSettingsForm::sections();

        if (static::canAccessAdminFeatures()) {
            $schema = array_merge($this->adminSections(), $schema);
        }

        return $form
            ->schema($schema)
            ->statePath('data');
    }

    /** @return array<int, Section> */
    protected function adminSections(): array
    {
        return [
            Section::make('Auto Blog')
                ->description('Thiết lập site-wide cho cron tạo blog. Gemini API key (Đăng bài viết tự động) cấu hình tại mục AI Content (Gemini) bên dưới.')
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
                        ->helperText('Ví dụ: - '.config('app.name'))
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
            Section::make('Đồng bộ Với Web Coupon')
                ->schema([
                    Toggle::make('coupon_sync_dedupe_domain')
                        ->label('Bỏ qua domain trùng trong hàng đợi')
                        ->helperText('Tắt = luôn đẩy mọi domain vào hàng đợi, kể cả trùng.')
                        ->default(true)
                        ->inline(false),
                ]),
        ];
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

        $this->persistence()->saveFormData($data);

        if (static::canAccessAdminFeatures()) {
            $this->saveAdminSettings($data);
        }

        $this->form->fill($this->loadFormData());

        Notification::make()
            ->title('Đã lưu cài đặt hệ thống')
            ->success()
            ->send();
    }

    /** @return array<string, mixed> */
    protected function loadFormData(): array
    {
        $data = $this->persistence()->loadFormData();

        if (! static::canAccessAdminFeatures()) {
            return $data;
        }

        return array_merge($data, [
            'site_contact_email' => (string) AdminSettings::get('site_contact_email', config('mail.from.address', 'contact@example.com')),
            'auto_blog_enabled' => (bool) AdminSettings::get('auto_blog_enabled', true),
            'auto_blog_daily_count' => (int) AdminSettings::get('auto_blog_daily_count', 2),
            'auto_blog_window_start_hour' => (int) AdminSettings::get('auto_blog_window_start_hour', 6),
            'auto_blog_window_end_hour' => (int) AdminSettings::get('auto_blog_window_end_hour', 18),
            'auto_blog_variant_best' => (bool) AdminSettings::get('auto_blog_variant_best', true),
            'auto_blog_variant_guide' => (bool) AdminSettings::get('auto_blog_variant_guide', true),
            'auto_blog_variant_comparison' => (bool) AdminSettings::get('auto_blog_variant_comparison', true),
            'auto_blog_queue_interval_minutes' => (int) AdminSettings::get('auto_blog_queue_interval_minutes', 10),
            'seo_title_suffix' => (string) AdminSettings::get('seo_title_suffix', '- '.config('app.name')),
            'seo_meta_description_default' => (string) AdminSettings::get('seo_meta_description_default', 'Latest articles and insights from our blog.'),
            'seo_og_image_default' => (string) AdminSettings::get('seo_og_image_default', ''),
            'coupon_sync_dedupe_domain' => \App\Support\CouponSyncSettings::dedupeDomainEnabled(),
        ]);
    }

    /** @param  array<string, mixed>  $data */
    protected function saveAdminSettings(array $data): void
    {
        AdminSettings::set('site_contact_email', trim((string) ($data['site_contact_email'] ?? MailSettings::fromAddress())));
        AdminSettings::set('auto_blog_enabled', (bool) ($data['auto_blog_enabled'] ?? true));
        AdminSettings::set('auto_blog_daily_count', max(1, (int) ($data['auto_blog_daily_count'] ?? 2)));
        AdminSettings::set('auto_blog_window_start_hour', max(0, min(23, (int) ($data['auto_blog_window_start_hour'] ?? 6))));
        AdminSettings::set('auto_blog_window_end_hour', max(0, min(23, (int) ($data['auto_blog_window_end_hour'] ?? 18))));
        AdminSettings::set('auto_blog_variant_best', (bool) ($data['auto_blog_variant_best'] ?? true));
        AdminSettings::set('auto_blog_variant_guide', (bool) ($data['auto_blog_variant_guide'] ?? true));
        AdminSettings::set('auto_blog_variant_comparison', (bool) ($data['auto_blog_variant_comparison'] ?? true));
        AdminSettings::set('auto_blog_queue_interval_minutes', max(1, min(1440, (int) ($data['auto_blog_queue_interval_minutes'] ?? 10))));

        AdminSettings::set('seo_title_suffix', trim((string) ($data['seo_title_suffix'] ?? ('- '.config('app.name')))));
        AdminSettings::set('seo_meta_description_default', trim((string) ($data['seo_meta_description_default'] ?? 'Latest articles and insights from our blog.')));
        AdminSettings::set('seo_og_image_default', trim((string) ($data['seo_og_image_default'] ?? '')));
        AdminSettings::set('coupon_sync_dedupe_domain', (bool) ($data['coupon_sync_dedupe_domain'] ?? true));
    }

    protected function persistence(): IntegrationSettingsPersistence
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new \RuntimeException('Người dùng chưa đăng nhập.');
        }

        return new IntegrationSettingsPersistence($user->id);
    }
}
