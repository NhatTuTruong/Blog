<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Pages\SystemSettings;
use App\Models\InstagramQueueItem;
use App\Models\User;
use App\Services\InstagramGraphService;
use App\Services\InstagramQueueService;
use App\Support\InstagramSettings;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SocialMediaPublish extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-share';

    protected static string $view = 'filament.admin.pages.social-media-publish';

    protected static ?string $navigationLabel = 'Đăng bài mạng xã hội';

    protected static ?string $title = 'Đăng bài mạng xã hội';

    protected static ?string $navigationGroup = 'Mạng xã hội';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'social-media-publish';

    public ?array $data = [];

    public string $activePlatform = 'instagram';

    public string $activeTab = 'compose';

    /** @var array{pending: int, processing: int, completed: int, failed: int} */
    public array $queueStats = [
        'pending' => 0,
        'processing' => 0,
        'completed' => 0,
        'failed' => 0,
    ];

    public int $queueIntervalMinutes = 30;

    public ?string $instagramAccountLabel = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getSubheading(): ?string
    {
        return '';
    }

    public function getRecordCount(): int
    {
        return collect($this->data['records'] ?? [])
            ->filter(fn (array $record): bool => $this->recordRowHasContent($record))
            ->count();
    }

    public function mount(): void
    {
        $this->queueIntervalMinutes = app(InstagramQueueService::class)->intervalMinutes();

        $this->form->fill([
            'records' => [$this->emptyRecordRow()],
        ]);

        $this->refreshQueue();
        $this->refreshInstagramAccountLabel();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Bài đăng Instagram')
                    ->description(fn (): string => InstagramSettings::isConfigured()
                        ? 'AI (Gemini) viết caption. Ảnh/video không bắt buộc — nếu không tải ảnh, hệ thống tự tạo ảnh từ nội dung. Khoảng cách: '.$this->queueIntervalMinutes.' phút/bài.'
                        : 'Chưa cấu hình Instagram — vào Cài đặt hệ thống để nhập Access Token và User ID.')
                    ->schema([
                        Placeholder::make('instagram_status')
                            ->label('Kết nối Instagram')
                            ->content(fn (): string => $this->instagramAccountLabel
                                ?? (InstagramSettings::isConfigured() ? 'Đã cấu hình' : 'Chưa cấu hình'))
                            ->helperText(fn (): ?string => InstagramSettings::isConfigured()
                                ? null
                                : 'Mở Cài đặt hệ thống → Instagram để nhập token và ID tài khoản.'),
                        Grid::make(12)
                            ->schema([
                                Placeholder::make('summary')
                                    ->label('Tóm tắt')
                                    ->content(fn (): string => implode(' · ', array_filter([
                                        $this->getRecordCount().' bài sẵn sàng',
                                        'Cách '.$this->queueIntervalMinutes.' phút/bài',
                                    ])))
                                    ->columnSpan(['default' => 12, 'md' => 4]),
                            ]),
                        Repeater::make('records')
                            ->label('')
                            ->columns(6)
                            ->defaultItems(1)
                            ->collapsible()
                            ->collapsed()
                            ->cloneable()
                            ->addActionLabel('Thêm bài')
                            ->itemLabel(fn (array $state): ?string => filled($state['brand_domain'] ?? null)
                                ? (string) $state['brand_domain']
                                : (filled($state['content_idea'] ?? null)
                                    ? Str::limit((string) $state['content_idea'], 40)
                                    : (filled($state['image'] ?? null) ? 'Có ảnh' : 'Bài mới')))
                            ->schema([
                                FileUpload::make('image')
                                    ->label('Ảnh đăng (tùy chọn)')
                                    ->image()
                                    ->disk('public')
                                    ->directory('instagram-uploads')
                                    ->visibility('public')
                                    ->maxSize(8192)
                                    ->helperText('Không bắt buộc. Nếu bỏ trống, hệ thống tự tạo ảnh từ domain/ý tưởng. Nên dùng JPG.')
                                    ->columnSpan(['default' => 6, 'md' => 2]),
                                TextInput::make('brand_domain')
                                    ->label('Domain brand')
                                    ->placeholder('nike.com')
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 6, 'md' => 2]),
                                TextInput::make('aff_link')
                                    ->label('Link AFF')
                                    ->url()
                                    ->maxLength(2048)
                                    ->placeholder('https://…')
                                    ->columnSpan(['default' => 6, 'md' => 2]),
                                Textarea::make('content_idea')
                                    ->label('Ý tưởng caption cho AI')
                                    ->rows(2)
                                    ->maxLength(2000)
                                    ->columnSpan(['default' => 6, 'md' => 4]),
                                TagsInput::make('coupon_codes')
                                    ->label('Coupon')
                                    ->placeholder('Enter')
                                    ->columnSpan(['default' => 6, 'md' => 2]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InstagramQueueItem::query()->latest('id')
            )
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Ảnh')
                    ->disk('public')
                    ->height(48)
                    ->width(48)
                    ->defaultImageUrl(fn (): string => 'data:image/svg+xml;base64,'.base64_encode(
                        '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48"><rect fill="#374151" width="48" height="48" rx="6"/><text x="24" y="28" text-anchor="middle" fill="#9CA3AF" font-size="10" font-family="sans-serif">AI</text></svg>'
                    )),
                Tables\Columns\TextColumn::make('brand_domain')
                    ->label('Brand')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state, InstagramQueueItem $record): string => $record->statusLabel())
                    ->color(fn (string $state): string => match ($state) {
                        InstagramQueueItem::STATUS_PENDING => 'warning',
                        InstagramQueueItem::STATUS_PROCESSING => 'info',
                        InstagramQueueItem::STATUS_COMPLETED => 'success',
                        InstagramQueueItem::STATUS_FAILED => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (InstagramQueueItem $record): ?string => $record->status === InstagramQueueItem::STATUS_FAILED
                        ? $record->error_message
                        : null),
                Tables\Columns\TextColumn::make('caption')
                    ->label('Caption')
                    ->limit(50)
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Lên lịch')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('instagram_media_id')
                    ->label('Media ID')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->poll('30s')
            ->emptyStateHeading('Chưa có bài trong hàng đợi')
            ->emptyStateDescription('Soạn bài Instagram và nhấn «Đăng bài» để bắt đầu.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testInstagram')
                ->label('Kiểm tra Instagram')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action(fn () => $this->testInstagramConnection()),
            Action::make('openSettings')
                ->label('Cài đặt Instagram')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(SystemSettings::getUrl()),
            Action::make('publish')
                ->label('Đăng bài')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->visible(fn (): bool => $this->activePlatform === 'instagram')
                ->modalHeading('Đăng danh sách lên Instagram')
                ->modalDescription(function (): string {
                    $service = app(InstagramQueueService::class);
                    $count = $this->getRecordCount();
                    $base = $count > 0
                        ? "Sẽ xếp hàng {$count} bài. Mỗi bài cách {$this->queueIntervalMinutes} phút. AI viết caption; ảnh tự tạo nếu không tải."
                        : 'Chưa có bài hợp lệ — nhập domain, ý tưởng caption hoặc tải ảnh.';

                    if ($service->hasActiveQueue()) {
                        $base .= ' Lưu ý: đang có hàng đợi ('.$service->activeQueueSummary().').';
                    }

                    if (! InstagramSettings::isConfigured()) {
                        $base .= ' Instagram chưa được cấu hình — lưu token và User ID trước.';
                    }

                    return $base;
                })
                ->modalSubmitActionLabel('Bắt đầu')
                ->form([
                    Placeholder::make('active_queue_warning')
                        ->label('Cảnh báo')
                        ->content(function (): string {
                            $service = app(InstagramQueueService::class);

                            return 'Đang có hàng đợi ('.$service->activeQueueSummary().'). Bài mới sẽ xếp chung hàng đợi.';
                        })
                        ->visible(fn (): bool => app(InstagramQueueService::class)->hasActiveQueue()),
                    Checkbox::make('confirm_active_queue')
                        ->label('Tôi hiểu và vẫn muốn thêm vào hàng đợi đang chạy')
                        ->accepted()
                        ->visible(fn (): bool => app(InstagramQueueService::class)->hasActiveQueue())
                        ->dehydrated(fn (): bool => app(InstagramQueueService::class)->hasActiveQueue()),
                    Radio::make('publish_mode')
                        ->label('Thời điểm bắt đầu')
                        ->options([
                            'immediate' => 'Ngay bây giờ',
                            'scheduled' => 'Đặt lịch',
                        ])
                        ->default('immediate')
                        ->live()
                        ->required(),
                    DateTimePicker::make('scheduled_start_at')
                        ->label('Bắt đầu lúc')
                        ->seconds(false)
                        ->native(false)
                        ->default(now())
                        ->minDate(now()->startOfDay())
                        ->visible(fn (Get $get): bool => $get('publish_mode') === 'scheduled')
                        ->required(fn (Get $get): bool => $get('publish_mode') === 'scheduled'),
                ])
                ->action(fn (array $data) => $this->publishRecords($data)),
        ];
    }

    public function testInstagramConnection(): void
    {
        $graph = app(InstagramGraphService::class);
        $result = $graph->testConnection();

        if ($result === null) {
            Notification::make()
                ->title('Không kết nối được Instagram')
                ->body($graph->lastError ?? 'Kiểm tra token và User ID.')
                ->danger()
                ->send();

            return;
        }

        $label = '@'.($result['username'] ?? $result['id']);
        if (filled($result['name'] ?? null)) {
            $label .= ' ('.$result['name'].')';
        }

        $this->instagramAccountLabel = $label;

        Notification::make()
            ->title('Kết nối Instagram thành công')
            ->body($label)
            ->success()
            ->send();
    }

    public function refreshInstagramAccountLabel(): void
    {
        if (! InstagramSettings::isConfigured()) {
            $this->instagramAccountLabel = null;

            return;
        }

        $graph = app(InstagramGraphService::class);
        $result = $graph->testConnection();

        if ($result === null) {
            $this->instagramAccountLabel = 'Lỗi: '.($graph->lastError ?? 'không kết nối được');

            return;
        }

        $this->instagramAccountLabel = '@'.($result['username'] ?? $result['id']);
    }

    public function publishRecords(array $data): void
    {
        if (! $this->prepareRecordsFromForm()) {
            return;
        }

        $records = $this->validRecordsFromForm();

        if ($records === []) {
            Notification::make()
                ->title('Chưa có bài để đăng')
                ->body('Nhập domain, ý tưởng caption, coupon hoặc tải ảnh (tùy chọn).')
                ->warning()
                ->send();

            return;
        }

        $startAt = ($data['publish_mode'] ?? 'immediate') === 'scheduled'
            ? Carbon::parse($data['scheduled_start_at'])
            : now();

        $service = app(InstagramQueueService::class);
        $batchId = $service->enqueue($records, Filament::auth()->user(), $startAt);

        if ($batchId === null) {
            Notification::make()
                ->title('Không thể xếp hàng')
                ->body($service->lastError ?? 'Vui lòng kiểm tra lại.')
                ->danger()
                ->send();

            return;
        }

        $count = count($records);
        $minutes = ($count - 1) * $this->queueIntervalMinutes;
        $startLabel = $startAt->isFuture()
            ? $startAt->format('d/m/Y H:i')
            : 'ngay bây giờ';

        Notification::make()
            ->title('Đã xếp hàng '.$count.' bài Instagram')
            ->body($minutes > 0
                ? "Bắt đầu {$startLabel} · cách {$this->queueIntervalMinutes} phút/bài."
                : "Bắt đầu từ {$startLabel}.")
            ->success()
            ->send();

        $this->form->fill([
            'records' => [$this->emptyRecordRow()],
        ]);

        $this->refreshQueue();
        $this->activeTab = 'queue';
    }

    public function refreshQueue(): void
    {
        $this->queueStats = app(InstagramQueueService::class)->queueStats();
        $this->queueIntervalMinutes = app(InstagramQueueService::class)->intervalMinutes();
    }

    public function cancelPendingQueue(): void
    {
        $cancelled = app(InstagramQueueService::class)->cancelPendingQueue();

        Notification::make()
            ->title($cancelled > 0 ? 'Đã hủy '.$cancelled.' bài chờ' : 'Không có bài đang chờ')
            ->success()
            ->send();

        $this->refreshQueue();
    }

    public function canCancelPendingQueue(): bool
    {
        return ($this->queueStats['pending'] ?? 0) > 0;
    }

    protected function prepareRecordsFromForm(): bool
    {
        $state = $this->form->getState();
        $this->data = $state;

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function validRecordsFromForm(): array
    {
        return collect($this->data['records'] ?? [])
            ->filter(fn (array $record): bool => $this->recordRowHasContent($record))
            ->map(function (array $record): array {
                $image = $record['image'] ?? null;
                if (is_array($image)) {
                    $image = $image[0] ?? null;
                }

                return [
                    ...$record,
                    'image' => is_string($image) && filled($image) ? $image : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function recordRowHasContent(array $record): bool
    {
        if (filled($record['image'] ?? null)) {
            return true;
        }

        if (filled($record['brand_domain'] ?? null)) {
            return true;
        }

        if (filled($record['content_idea'] ?? null)) {
            return true;
        }

        if (filled($record['aff_link'] ?? null)) {
            return true;
        }

        $coupons = $record['coupon_codes'] ?? [];

        return is_array($coupons) && collect($coupons)->filter(fn (mixed $c): bool => filled($c))->isNotEmpty();
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyRecordRow(): array
    {
        return [
            'image' => null,
            'brand_domain' => null,
            'content_idea' => null,
            'aff_link' => null,
            'coupon_codes' => [],
        ];
    }
}
