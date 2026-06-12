<?php

namespace App\Filament\Admin\Support;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;

class IntegrationSettingsForm
{
    /** @return array<int, Section> */
    public static function sections(): array
    {
        return [
            Section::make('AI Content (Gemini)')
                ->description('Mỗi phần dùng một API key riêng. Nhập key mới để lưu; "********" giữ key hiện tại; để trống xóa key.')
                ->schema([
                    TextInput::make('gemini_api_key_auto_blog')
                        ->label('Gemini API key — Đăng bài viết tự động')
                        ->password()
                        ->revealable()
                        ->maxLength(255),
                    TextInput::make('gemini_api_key_instagram')
                        ->label('Gemini API key — Instagram')
                        ->password()
                        ->revealable()
                        ->maxLength(255),
                    TextInput::make('gemini_api_key_facebook')
                        ->label('Gemini API key — Facebook')
                        ->password()
                        ->revealable()
                        ->maxLength(255),
                    TextInput::make('gemini_api_key_pinterest')
                        ->label('Gemini API key — Pinterest')
                        ->password()
                        ->revealable()
                        ->maxLength(255),
                    TextInput::make('gemini_model')
                        ->label('Gemini model')
                        ->required()
                        ->helperText('Khuyến nghị: gemini-2.5-flash-lite. Tránh gemini-flash-latest khi bị 503.')
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
            Section::make('Apify — Google Images')
                ->schema([
                    TextInput::make('apify_api_token')
                        ->label('Apify API token')
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
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
                        ->label('Khoảng cách đăng hàng đợi thủ công (phút)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(1440)
                        ->default(30)
                        ->required(),
                    Toggle::make('instagram_auto_queue_enabled')
                        ->label('Bật hàng đợi auto Instagram')
                        ->helperText('Tự đăng lại từ bài «Hoàn thành» trong bảng Hàng đợi (cũ nhất trước, xoay vòng khác brand). Tạm dừng khi hàng đợi thủ công đang chạy.')
                        ->inline(false),
                    TextInput::make('instagram_auto_queue_interval_minutes')
                        ->label('Khoảng cách đăng hàng đợi auto (phút)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(1440)
                        ->default(60)
                        ->required()
                        ->visible(fn (callable $get): bool => (bool) $get('instagram_auto_queue_enabled')),
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
            Section::make('Facebook Page (Meta Graph API)')
                ->schema([
                    Toggle::make('facebook_enabled')
                        ->label('Bật đăng Facebook')
                        ->inline(false),
                    Repeater::make('facebook_accounts')
                        ->label('Trang Facebook')
                        ->schema([
                            Hidden::make('id'),
                            TextInput::make('name')
                                ->label('Tên gợi nhớ')
                                ->placeholder('Fanpage chính…')
                                ->maxLength(120),
                            TextInput::make('page_id')
                                ->label('Page ID')
                                ->required()
                                ->maxLength(64),
                            TextInput::make('access_token')
                                ->label('Page Access Token')
                                ->password()
                                ->revealable()
                                ->helperText('Token EAA… của Page. Nhập mới để lưu; "********" giữ token hiện tại.')
                                ->maxLength(2048)
                                ->columnSpanFull(),
                            Toggle::make('enabled')
                                ->label('Bật')
                                ->default(true)
                                ->inline(false),
                        ])
                        ->columns(2)
                        ->defaultItems(1)
                        ->addActionLabel('Thêm trang')
                        ->reorderable()
                        ->collapsible()
                        ->collapsed()
                        ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null)
                            ? (string) $state['name']
                            : (filled($state['page_id'] ?? null) ? 'Page '.$state['page_id'] : 'Trang mới'))
                        ->columnSpanFull(),
                    TextInput::make('facebook_graph_version')
                        ->label('Graph API version')
                        ->default('v21.0')
                        ->maxLength(20),
                    TextInput::make('facebook_queue_interval_minutes')
                        ->label('Khoảng cách đăng hàng đợi thủ công (phút)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(1440)
                        ->default(30)
                        ->required(),
                    Toggle::make('facebook_auto_queue_enabled')
                        ->label('Bật hàng đợi auto Facebook')
                        ->helperText('Tự đăng lại từ bài «Hoàn thành» trong bảng Hàng đợi (cũ nhất trước, xoay vòng khác brand). Tạm dừng khi hàng đợi thủ công đang chạy.')
                        ->inline(false),
                    TextInput::make('facebook_auto_queue_interval_minutes')
                        ->label('Khoảng cách đăng hàng đợi auto (phút)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(1440)
                        ->default(60)
                        ->required()
                        ->visible(fn (callable $get): bool => (bool) $get('facebook_auto_queue_enabled')),
                    TextInput::make('facebook_public_base_url')
                        ->label('URL công khai (HTTPS)')
                        ->url()
                        ->placeholder('https://your-domain.com')
                        ->maxLength(500)
                        ->columnSpanFull(),
                ])
                ->columns(3),
            Section::make('Pinterest (API v5)')
                ->schema([
                    Toggle::make('pinterest_enabled')
                        ->label('Bật đăng Pinterest')
                        ->helperText('Tự bật khi lưu tài khoản có token. Có token hợp lệ là đủ để đăng Pin.')
                        ->inline(false),
                    Repeater::make('pinterest_accounts')
                        ->label('Tài khoản Pinterest')
                        ->schema([
                            Hidden::make('id'),
                            TextInput::make('name')
                                ->label('Tên gợi nhớ')
                                ->placeholder('Tài khoản chính…')
                                ->maxLength(120),
                            TextInput::make('access_token')
                                ->label('Access Token')
                                ->password()
                                ->revealable()
                                ->helperText('Token OAuth Pinterest. Nhập mới để lưu; "********" giữ token hiện tại.')
                                ->maxLength(2048)
                                ->columnSpanFull(),
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
                            : 'Tài khoản mới')
                        ->columnSpanFull(),
                    TextInput::make('pinterest_queue_interval_minutes')
                        ->label('Khoảng cách đăng hàng đợi thủ công (phút)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(1440)
                        ->default(30)
                        ->required(),
                    Toggle::make('pinterest_auto_queue_enabled')
                        ->label('Bật hàng đợi auto Pinterest')
                        ->helperText('Tự đăng lại từ bài «Hoàn thành» trong bảng Hàng đợi (cũ nhất trước, xoay vòng khác brand). Tạm dừng khi hàng đợi thủ công đang chạy.')
                        ->inline(false),
                    TextInput::make('pinterest_auto_queue_interval_minutes')
                        ->label('Khoảng cách đăng hàng đợi auto (phút)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(1440)
                        ->default(60)
                        ->required()
                        ->visible(fn (callable $get): bool => (bool) $get('pinterest_auto_queue_enabled')),
                    TextInput::make('pinterest_auto_queue_board_ids')
                        ->label('Board ID cho hàng đợi auto')
                        ->helperText('Cách nhau bởi dấu phẩy. Để trống = dùng Board lần đăng thủ công gần nhất hoặc Board đầu tiên.')
                        ->placeholder('123456789,987654321')
                        ->maxLength(500)
                        ->visible(fn (callable $get): bool => (bool) $get('pinterest_auto_queue_enabled'))
                        ->columnSpanFull(),
                    TextInput::make('pinterest_public_base_url')
                        ->label('URL công khai (HTTPS)')
                        ->url()
                        ->placeholder('https://your-domain.com')
                        ->helperText('Để trống = dùng URL Facebook/Instagram hoặc APP_URL.')
                        ->maxLength(500)
                        ->columnSpanFull(),
                    TextInput::make('pinterest_api_base_url')
                        ->label('Pinterest API base URL (tùy chọn)')
                        ->placeholder('https://api.pinterest.com/v5')
                        ->maxLength(500)
                        ->columnSpanFull(),
                ])
                ->columns(3),
            Section::make('Email (Gửi & Nhận mail)')
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
        ];
    }
}
