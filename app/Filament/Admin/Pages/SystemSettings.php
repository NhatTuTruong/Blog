<?php

namespace App\Filament\Admin\Pages;

use App\Models\InstagramAccount;
use App\Support\AdminSettings;
use App\Support\MailSettings;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
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
            'gemini_api_key' => AdminSettings::getEncrypted('gemini_api_key') ? '********' : '',
            'gemini_api_key_2' => AdminSettings::getEncrypted('gemini_api_key_2') ? '********' : '',
            'gemini_api_key_3' => AdminSettings::getEncrypted('gemini_api_key_3') ? '********' : '',
            'gemini_model' => (string) AdminSettings::get('gemini_model', config('gemini.model', 'gemini-1.5-flash-latest')),
            'gemini_timeout' => max(60, (int) AdminSettings::get('gemini_timeout', config('gemini.timeout', 120))),
            'mail_host' => (string) AdminSettings::get('mail_host', env('MAIL_HOST', 'smtp.gmail.com')),
            'mail_port' => (int) AdminSettings::get('mail_port', env('MAIL_PORT', 587)),
            'mail_encryption' => (string) AdminSettings::get('mail_encryption', env('MAIL_ENCRYPTION', 'tls')),
            'mail_username' => (string) AdminSettings::get('mail_username', env('MAIL_USERNAME', '')),
            'mail_password' => AdminSettings::getEncrypted('mail_password') ? '********' : '',
            'mail_from_name' => (string) AdminSettings::get('mail_from_name', env('MAIL_FROM_NAME', config('app.name'))),
            'imap_host' => (string) AdminSettings::get('imap_host', env('IMAP_HOST', 'imap.gmail.com')),
            'imap_port' => (int) AdminSettings::get('imap_port', env('IMAP_PORT', 993)),
            'imap_encryption' => (string) AdminSettings::get('imap_encryption', env('IMAP_ENCRYPTION', 'ssl')),
            'imap_sync_limit' => (int) AdminSettings::get('imap_sync_limit', env('IMAP_SYNC_LIMIT', 50)),
            'imap_auto_sync_seconds' => (int) AdminSettings::get('imap_auto_sync_seconds', env('IMAP_AUTO_SYNC_SECONDS', 120)),
            'imap_ui_poll_seconds' => (int) AdminSettings::get('imap_ui_poll_seconds', env('IMAP_UI_POLL_SECONDS', 15)),
            'imap_notifications_poll_seconds' => (int) AdminSettings::get('imap_notifications_poll_seconds', env('IMAP_NOTIFICATIONS_POLL_SECONDS', 10)),
            'site_contact_email' => (string) AdminSettings::get('site_contact_email', config('mail.from.address', 'contact@example.com')),
            'auto_blog_enabled' => (bool) AdminSettings::get('auto_blog_enabled', true),
            'auto_blog_daily_count' => (int) AdminSettings::get('auto_blog_daily_count', 2),
            'auto_blog_window_start_hour' => (int) AdminSettings::get('auto_blog_window_start_hour', 6),
            'auto_blog_window_end_hour' => (int) AdminSettings::get('auto_blog_window_end_hour', 18),
            'auto_blog_variant_best' => (bool) AdminSettings::get('auto_blog_variant_best', true),
            'auto_blog_variant_guide' => (bool) AdminSettings::get('auto_blog_variant_guide', true),
            'auto_blog_variant_comparison' => (bool) AdminSettings::get('auto_blog_variant_comparison', true),
            'auto_blog_queue_interval_minutes' => (int) AdminSettings::get('auto_blog_queue_interval_minutes', 10),
            'instagram_enabled' => (bool) AdminSettings::get('instagram_enabled', false),
            'instagram_accounts' => InstagramAccount::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (InstagramAccount $account): array => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'access_token' => filled($account->access_token) ? '********' : '',
                    'user_id' => $account->user_id,
                    'enabled' => $account->enabled,
                ])
                ->values()
                ->all(),
            'instagram_graph_version' => (string) AdminSettings::get('instagram_graph_version', 'v21.0'),
            'instagram_queue_interval_minutes' => (int) AdminSettings::get('instagram_queue_interval_minutes', 30),
            'instagram_public_base_url' => (string) AdminSettings::get('instagram_public_base_url', ''),
            'instagram_default_image_url' => (string) AdminSettings::get('instagram_default_image_url', ''),
            'seo_title_suffix' => (string) AdminSettings::get('seo_title_suffix', '- ' . config('app.name')),
            'seo_meta_description_default' => (string) AdminSettings::get('seo_meta_description_default', 'Latest articles and insights from our blog.'),
            'seo_og_image_default' => (string) AdminSettings::get('seo_og_image_default', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('AI Content (Gemini)')
                    ->description('Dùng cho tạo blog AI trong admin và cron. Có thể cấu hình tối đa 3 key — nếu key trước lỗi hoặc hết quota sẽ tự chuyển sang key tiếp theo.')
                    ->schema([
                        TextInput::make('gemini_api_key')
                            ->label('Gemini API key (chính)')
                            ->password()
                            ->revealable()
                            ->helperText('Key ưu tiên 1. Nhập key mới để lưu; để "********" giữ key hiện tại; để trống xóa key.')
                            ->maxLength(255),
                        TextInput::make('gemini_api_key_2')
                            ->label('Gemini API key dự phòng #2')
                            ->password()
                            ->revealable()
                            ->helperText('Key ưu tiên 2. Không bắt buộc.')
                            ->maxLength(255),
                        TextInput::make('gemini_api_key_3')
                            ->label('Gemini API key dự phòng #3')
                            ->password()
                            ->revealable()
                            ->helperText('Key ưu tiên 3. Không bắt buộc.')
                            ->maxLength(255),
                        TextInput::make('gemini_model')
                            ->label('Gemini model')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('gemini_timeout')
                            ->label('Timeout (giây)')
                            ->numeric()
                            ->minValue(60)
                            ->maxValue(600)
                            ->default(120)
                            ->helperText('Tạo bài dài cần 90–180 giây. Tối thiểu 60.')
                            ->required(),
                    ])
                    ->columns(3),
                Section::make('Auto Blog')
                    ->description('Thiết lập tạo blog tự động theo ngày, khung giờ, variant và hàng đợi đăng bài AI.')
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
                            ->helperText('Áp dụng cho trang «Đăng bài viết tự động» — mỗi bài cách nhau bao nhiêu phút.')
                            ->required(),
                    ])
                    ->columns(3),
                Section::make('Instagram (Meta Graph API)')
                    ->schema([
                        Toggle::make('instagram_enabled')
                            ->label('Bật đăng Instagram')
                            ->inline(false),
                        Repeater::make('instagram_accounts')
                            ->label('Tài khoản Instagram')
                            ->schema([
                                Hidden::make('id'),
                                TextInput::make('name')
                                    ->label('Tên gợi nhớ')
                                    ->placeholder('Shop chính, Brand A…')
                                    ->maxLength(120),
                                TextInput::make('access_token')
                                    ->label('Access Token')
                                    ->password()
                                    ->revealable()
                                    ->helperText('Nhập token mới để lưu; "********" giữ token hiện tại; để trống bỏ qua dòng mới.')
                                    ->maxLength(2048)
                                    ->columnSpanFull(),
                                TextInput::make('user_id')
                                    ->label('Instagram User ID')
                                    ->helperText('Có thể để trống.')
                                    ->maxLength(64),
                                Toggle::make('enabled')
                                    ->label('Bật')
                                    ->default(true)
                                    ->inline(false),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->addActionLabel('Thêm tài khoản')
                            ->reorderable()
                            ->collapsible()
                            ->collapsed()
                            ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null)
                                ? (string) $state['name']
                                : (filled($state['user_id'] ?? null) ? 'ID '.$state['user_id'] : 'Tài khoản mới'))
                            ->columnSpanFull(),
                        TextInput::make('instagram_graph_version')
                            ->label('Graph API version')
                            ->default('v21.0')
                            ->maxLength(20),
                        TextInput::make('instagram_queue_interval_minutes')
                            ->label('Khoảng cách đăng hàng đợi (phút)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->default(30)
                           
                            ->required(),
                        TextInput::make('instagram_public_base_url')
                            ->label('URL công khai (HTTPS)')
                            ->url()
                            ->placeholder('https://your-domain.com')
                            ->maxLength(500)
                            ->columnSpanFull(),
                        TextInput::make('instagram_default_image_url')
                            ->label('Ảnh mặc định (URL, tùy chọn)')
                            ->url()
                            ->helperText('Dùng khi không tải ảnh và server không tạo được ảnh tự động.')
                            ->maxLength(500)
                            ->columnSpanFull(),
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
                Section::make('Email (Gửi & Nhận mail)')
                    ->description('Cấu hình SMTP Gmail và IMAP. Ưu tiên giá trị lưu tại đây; nếu chưa lưu sẽ dùng file .env. Địa chỉ gửi (From) luôn trùng email đăng nhập.')
                    ->schema([
                        TextInput::make('mail_host')
                            ->label('SMTP Host')
                            ->required()
                            ->maxLength(120)
                            ->default('smtp.gmail.com'),
                        TextInput::make('mail_port')
                            ->label('SMTP Port')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->required(),
                        Select::make('mail_encryption')
                            ->label('Mã hóa SMTP')
                            ->options([
                                'tls' => 'TLS',
                                'ssl' => 'SSL',
                            ])
                            ->required(),
                        TextInput::make('mail_username')
                            ->label('Email đăng nhập (SMTP / IMAP)')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->helperText('Gmail: dùng địa chỉ Gmail của bạn.'),
                        Placeholder::make('mail_from_address_preview')
                            ->label('Địa chỉ gửi (MAIL_FROM_ADDRESS)')
                            ->content(fn (Get $get): string => filled($get('mail_username'))
                                ? (string) $get('mail_username')
                                : '—')
                            ->helperText('Tự động trùng với email đăng nhập ở trên.'),
                        TextInput::make('mail_password')
                            ->label('Mật khẩu ứng dụng (App Password)')
                            ->password()
                            ->revealable()
                            ->helperText('Gmail: tạo App Password. Nhập mới để đổi; "********" giữ mật khẩu hiện tại; trống để xóa.')
                            ->maxLength(255),
                        TextInput::make('mail_from_name')
                            ->label('Tên hiển thị khi gửi (From name)')
                            ->maxLength(120),
                        TextInput::make('imap_host')
                            ->label('IMAP Host')
                            ->required()
                            ->maxLength(120)
                            ->default('imap.gmail.com'),
                        TextInput::make('imap_port')
                            ->label('IMAP Port')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->required(),
                        Select::make('imap_encryption')
                            ->label('Mã hóa IMAP')
                            ->options([
                                'ssl' => 'SSL',
                                'tls' => 'TLS',
                            ])
                            ->required(),
                        TextInput::make('imap_sync_limit')
                            ->label('Số email tối đa mỗi lần đồng bộ')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(500)
                            ->required(),
                        TextInput::make('imap_auto_sync_seconds')
                            ->label('Tự đồng bộ nền (giây, 0 = tắt)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(3600)
                            ->required(),
                        TextInput::make('imap_ui_poll_seconds')
                            ->label('Làm mới bảng Nhận mail (giây)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(300)
                            ->required(),
                        TextInput::make('imap_notifications_poll_seconds')
                            ->label('Chuông thông báo (giây)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(300)
                            ->required(),
                    ])
                    ->columns(3),
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

    protected function saveGeminiApiKey(string $field, array $data): void
    {
        $value = trim((string) ($data[$field] ?? ''));

        if ($value === '********') {
            return;
        }

        AdminSettings::setEncrypted($field, $value !== '' ? $value : null);
    }

    protected function saveMailPassword(array $data): void
    {
        $value = trim((string) ($data['mail_password'] ?? ''));

        if ($value === '********') {
            return;
        }

        AdminSettings::setEncrypted('mail_password', $value !== '' ? $value : null);
    }

    protected function saveInstagramAccounts(array $data): void
    {
        $rows = is_array($data['instagram_accounts'] ?? null) ? $data['instagram_accounts'] : [];
        $keptIds = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $tokenInput = trim((string) ($row['access_token'] ?? ''));
            $accountId = filled($row['id'] ?? null) ? (int) $row['id'] : null;

            /** @var InstagramAccount|null $account */
            $account = $accountId ? InstagramAccount::query()->find($accountId) : null;

            if ($account === null && ($tokenInput === '' || $tokenInput === '********')) {
                continue;
            }

            if ($account === null) {
                $account = new InstagramAccount;
            }

            $account->name = filled($row['name'] ?? null) ? trim((string) $row['name']) : null;
            $account->user_id = filled($row['user_id'] ?? null) ? trim((string) $row['user_id']) : null;
            $account->enabled = (bool) ($row['enabled'] ?? true);
            $account->sort_order = (int) $index;

            if ($tokenInput !== '' && $tokenInput !== '********') {
                $account->access_token = $tokenInput;
            } elseif (! $account->exists) {
                continue;
            }

            $account->save();
            $keptIds[] = $account->id;
        }

        if ($keptIds !== []) {
            InstagramAccount::query()->whereNotIn('id', $keptIds)->delete();
        } else {
            InstagramAccount::query()->delete();
        }
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->saveGeminiApiKey('gemini_api_key', $data);
        $this->saveGeminiApiKey('gemini_api_key_2', $data);
        $this->saveGeminiApiKey('gemini_api_key_3', $data);

        AdminSettings::set('gemini_model', trim((string) ($data['gemini_model'] ?? config('gemini.model', 'gemini-1.5-flash-latest'))));
        AdminSettings::set('gemini_timeout', max(60, min(600, (int) ($data['gemini_timeout'] ?? config('gemini.timeout', 120)))));

        $mailUsername = trim((string) ($data['mail_username'] ?? ''));
        AdminSettings::set('mail_host', trim((string) ($data['mail_host'] ?? 'smtp.gmail.com')));
        AdminSettings::set('mail_port', max(1, min(65535, (int) ($data['mail_port'] ?? 587))));
        AdminSettings::set('mail_encryption', trim((string) ($data['mail_encryption'] ?? 'tls')));
        AdminSettings::set('mail_username', $mailUsername);
        $this->saveMailPassword($data);
        AdminSettings::set('mail_from_name', trim((string) ($data['mail_from_name'] ?? config('app.name'))));
        AdminSettings::set('imap_host', trim((string) ($data['imap_host'] ?? 'imap.gmail.com')));
        AdminSettings::set('imap_port', max(1, min(65535, (int) ($data['imap_port'] ?? 993))));
        AdminSettings::set('imap_encryption', trim((string) ($data['imap_encryption'] ?? 'ssl')));
        AdminSettings::set('imap_sync_limit', max(1, min(500, (int) ($data['imap_sync_limit'] ?? 50))));
        AdminSettings::set('imap_auto_sync_seconds', max(0, min(3600, (int) ($data['imap_auto_sync_seconds'] ?? 120))));
        AdminSettings::set('imap_ui_poll_seconds', max(0, min(300, (int) ($data['imap_ui_poll_seconds'] ?? 15))));
        AdminSettings::set('imap_notifications_poll_seconds', max(0, min(300, (int) ($data['imap_notifications_poll_seconds'] ?? 10))));

        MailSettings::applyToConfig();

        AdminSettings::set('site_contact_email', trim((string) ($data['site_contact_email'] ?? MailSettings::fromAddress())));
        AdminSettings::set('auto_blog_enabled', (bool) ($data['auto_blog_enabled'] ?? true));
        AdminSettings::set('auto_blog_daily_count', max(1, (int) ($data['auto_blog_daily_count'] ?? 2)));
        AdminSettings::set('auto_blog_window_start_hour', max(0, min(23, (int) ($data['auto_blog_window_start_hour'] ?? 6))));
        AdminSettings::set('auto_blog_window_end_hour', max(0, min(23, (int) ($data['auto_blog_window_end_hour'] ?? 18))));
        AdminSettings::set('auto_blog_variant_best', (bool) ($data['auto_blog_variant_best'] ?? true));
        AdminSettings::set('auto_blog_variant_guide', (bool) ($data['auto_blog_variant_guide'] ?? true));
        AdminSettings::set('auto_blog_variant_comparison', (bool) ($data['auto_blog_variant_comparison'] ?? true));
        AdminSettings::set('auto_blog_queue_interval_minutes', max(1, min(1440, (int) ($data['auto_blog_queue_interval_minutes'] ?? 10))));

        AdminSettings::set('instagram_enabled', (bool) ($data['instagram_enabled'] ?? false));
        $this->saveInstagramAccounts($data);
        AdminSettings::set('instagram_graph_version', trim((string) ($data['instagram_graph_version'] ?? 'v21.0')));
        AdminSettings::set('instagram_queue_interval_minutes', max(1, min(1440, (int) ($data['instagram_queue_interval_minutes'] ?? 30))));
        AdminSettings::set('instagram_public_base_url', trim((string) ($data['instagram_public_base_url'] ?? '')));
        AdminSettings::set('instagram_default_image_url', trim((string) ($data['instagram_default_image_url'] ?? '')));

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
