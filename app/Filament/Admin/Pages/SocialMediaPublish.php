<?php

namespace App\Filament\Admin\Pages;

use App\Exports\InstagramTemplateExport;
use App\Filament\Admin\Pages\SystemSettings;
use App\Models\InstagramQueueItem;
use App\Models\InstagramSavedList;
use App\Models\User;
use App\Services\InstagramGraphService;
use App\Services\InstagramImportService;
use App\Services\InstagramQueueService;
use App\Services\InstagramSavedListService;
use App\Support\InstagramSettings;
use App\Support\PublicStorage;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public ?int $loadedSavedListId = null;

    public ?string $loadedSavedListName = null;

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
            'import_file' => null,
            'saved_list_id' => null,
        ]);

        $this->refreshQueue();
        $this->refreshInstagramAccountLabel();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Nguồn dữ liệu')
                    ->description('Chọn danh sách đã lưu (tự tải) hoặc upload Excel. File sẽ được import khi bạn nhấn «Đăng bài» hoặc «Import file».')
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                Select::make('saved_list_id')
                                    ->label('Danh sách đã lưu')
                                    ->options(fn (): array => InstagramSavedList::optionsForSelect())
                                    ->placeholder('— Chọn để tải —')
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function (?string $state): void {
                                        if (filled($state)) {
                                            $this->loadSavedListById((int) $state, silent: true);
                                        }
                                    })
                                    ->columnSpan(['default' => 12, 'md' => 5]),
                                FileUpload::make('import_file')
                                    ->label('Import Excel / CSV')
                                    ->disk('local')
                                    ->directory('instagram-imports')
                                    ->visibility('private')
                                    ->acceptedFileTypes([
                                        'text/csv',
                                        'text/plain',
                                        'application/vnd.ms-excel',
                                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                    ])
                                    ->maxSize(5120)
                                    ->columnSpan(['default' => 12, 'md' => 4]),
                                Placeholder::make('summary')
                                    ->label('Tóm tắt')
                                    ->content(function (): string {
                                        $parts = [$this->getRecordCount().' bài sẵn sàng'];

                                        if ($this->loadedSavedListName) {
                                            $parts[] = 'Đang mở: '.$this->loadedSavedListName;
                                        }

                                        $parts[] = 'Cách '.$this->queueIntervalMinutes.' phút/bài';

                                        return implode(' · ', $parts);
                                    })
                                    ->columnSpan(['default' => 12, 'md' => 3]),
                            ]),
                    ]),
                Section::make('Chi tiết bài đăng Instagram')
                    ->description(fn (): string => InstagramSettings::isConfigured()
                        ? 'AI viết caption theo ý tưởng (bỏ trống = giới thiệu cửa hàng). Mỗi bài chỉ 1 ảnh hoặc 1 video. Không gắn media = random ảnh default1–3. Video tự xóa sau khi đăng.'
                        : 'Chưa cấu hình Instagram — vào Cài đặt hệ thống để nhập Access Token và User ID.')
                    ->schema([
                        Placeholder::make('instagram_status')
                            ->label('Kết nối Instagram')
                            ->content(fn (): string => $this->instagramAccountLabel
                                ?? (InstagramSettings::isConfigured() ? 'Đã cấu hình' : 'Chưa cấu hình'))
                            ->helperText(fn (): ?string => InstagramSettings::isConfigured()
                                ? null
                                : 'Mở Cài đặt hệ thống → Instagram để nhập token và ID tài khoản.'),
                        Repeater::make('records')
                            ->label('')
                            ->columns(6)
                            ->defaultItems(1)
                            ->collapsible()
                            ->collapsed()
                            ->cloneable()
                            ->addActionLabel('Thêm dòng')
                            ->itemLabel(fn (array $state): ?string => filled($state['brand_domain'] ?? null)
                                ? (string) $state['brand_domain']
                                : (filled($state['content_idea'] ?? null)
                                    ? Str::limit((string) $state['content_idea'], 40)
                                    : $this->mediaRepeaterItemLabel($state)))
                            ->schema([
                                FileUpload::make('media')
                                    ->label('Ảnh hoặc video (tùy chọn)')
                                    ->acceptedFileTypes([
                                        'image/jpeg',
                                        'image/png',
                                        'image/webp',
                                        'image/gif',
                                        'video/mp4',
                                        'video/quicktime',
                                    ])
                                    ->disk('public')
                                    ->directory('instagram-uploads')
                                    ->visibility('public')
                                    ->maxFiles(1)
                                    ->maxSize(102400)
                                    ->saveUploadedFileUsing(function ($file, BaseFileUpload $component): ?string {
                                        $directory = str_starts_with((string) $file->getMimeType(), 'video/')
                                            ? 'instagram-temp-videos'
                                            : 'instagram-uploads';
                                        $filename = $component->getUploadedFileNameForStorage($file);
                                        $stored = PublicStorage::storeUploadedFile($file, $directory, $filename);

                                        return PublicStorage::syncUploadedPath($stored);
                                    })
                                    ->helperText('Chỉ 1 file: ảnh (JPG/PNG, ≤8MB) hoặc video (MP4/MOV, ≤100MB). Bỏ trống = random ảnh default1–3 trong public/images/instagram. Video tự xóa sau khi đăng.')
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
                                    ->rows(4)
                                    ->maxLength(2000)
                                    ->helperText('Tùy chọn. Bỏ trống = AI viết đoạn Instagram ngắn giới thiệu cửa hàng. Có ý tưởng chi tiết (nhiều dòng/bullet) = caption bám sát hơn.')
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
                    ->label('Media')
                    ->disk('public')
                    ->height(48)
                    ->width(48)
                    ->defaultImageUrl(fn (InstagramQueueItem $record): string => filled($record->video_path)
                        ? 'data:image/svg+xml;base64,'.base64_encode(
                            '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48"><rect fill="#1e3a5f" width="48" height="48" rx="6"/><polygon fill="#9CA3AF" points="20,16 34,24 20,32"/></svg>'
                        )
                        : 'data:image/svg+xml;base64,'.base64_encode(
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
                    ->description(fn (InstagramQueueItem $record): ?string => match (true) {
                        $record->status === InstagramQueueItem::STATUS_FAILED => $record->error_message,
                        $record->used_default_caption && $record->status === InstagramQueueItem::STATUS_COMPLETED => 'Đã đăng với nội dung mặc định',
                        default => null,
                    }),
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
            ->emptyStateDescription('Đăng danh sách bài Instagram để bắt đầu xếp hàng tự động.');
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
                        ? "Sẽ xếp hàng {$count} bài. Mỗi bài cách {$this->queueIntervalMinutes} phút. AI viết caption theo ý tưởng."
                        : 'Chưa có bài hợp lệ — nhập dữ liệu, import file hoặc chọn danh sách đã lưu.';

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
            ActionGroup::make([
                Action::make('importFromFile')
                    ->label('Import file vào danh sách')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->action(fn () => $this->importFromFile()),
                Action::make('saveList')
                    ->label('Lưu danh sách')
                    ->icon('heroicon-o-bookmark')
                    ->form([
                        TextInput::make('name')
                            ->label('Tên danh sách')
                            ->required()
                            ->maxLength(120)
                            ->default(fn (): ?string => $this->loadedSavedListName),
                    ])
                    ->action(fn (array $data) => $this->saveCurrentList($data)),
                Action::make('deleteSavedList')
                    ->label('Xóa danh sách đã lưu')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => $this->loadedSavedListId !== null
                        || filled($this->data['saved_list_id'] ?? null))
                    ->action(fn () => $this->deleteSavedList()),
                Action::make('downloadTemplate')
                    ->label('Tải file mẫu Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (): BinaryFileResponse => Excel::download(
                        new InstagramTemplateExport,
                        'instagram-template.xlsx',
                    )),
            ])
                ->label('Thêm')
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->button(),
        ];
    }

    public function importFromFile(): void
    {
        $count = $this->runImportFromForm();

        if ($count === null) {
            return;
        }

        Notification::make()
            ->title('Đã import '.$count.' bài')
            ->success()
            ->send();
    }

    public function saveCurrentList(array $data): void
    {
        if (! $this->prepareRecordsFromForm()) {
            return;
        }

        $records = $this->validRecordsFromForm();
        $service = app(InstagramSavedListService::class);
        $list = $service->save(
            (string) ($data['name'] ?? ''),
            $records,
            Filament::auth()->user(),
            $this->loadedSavedListId,
        );

        if ($list === null) {
            Notification::make()
                ->title('Không thể lưu')
                ->body($service->lastError ?? 'Vui lòng kiểm tra lại.')
                ->danger()
                ->send();

            return;
        }

        $this->loadedSavedListId = $list->id;
        $this->loadedSavedListName = $list->name;

        $this->form->fill([
            ...$this->form->getState(),
            'saved_list_id' => $list->id,
        ]);

        Notification::make()
            ->title('Đã lưu «'.$list->name.'»')
            ->body($list->record_count.' bài Instagram.')
            ->success()
            ->send();
    }

    public function loadSavedListById(int $savedListId, bool $silent = false): void
    {
        if ($savedListId <= 0) {
            return;
        }

        $list = InstagramSavedList::query()->find($savedListId);

        if (! $list) {
            if (! $silent) {
                Notification::make()->title('Không tìm thấy danh sách')->danger()->send();
            }

            return;
        }

        $records = app(InstagramSavedListService::class)->recordsForForm($savedListId);

        if ($records === []) {
            if (! $silent) {
                Notification::make()->title('Danh sách trống')->warning()->send();
            }

            return;
        }

        $this->loadedSavedListId = $list->id;
        $this->loadedSavedListName = $list->name;

        $this->form->fill([
            'records' => $records,
            'import_file' => null,
            'saved_list_id' => $list->id,
        ]);

        if (! $silent) {
            Notification::make()
                ->title('Đã tải «'.$list->name.'»')
                ->body($list->record_count.' bài Instagram.')
                ->success()
                ->send();
        }
    }

    public function deleteSavedList(): void
    {
        $savedListId = (int) ($this->form->getState()['saved_list_id'] ?? $this->loadedSavedListId ?? 0);

        if ($savedListId <= 0) {
            Notification::make()->title('Chưa chọn danh sách')->warning()->send();

            return;
        }

        $service = app(InstagramSavedListService::class);

        if (! $service->delete($savedListId)) {
            Notification::make()
                ->title('Không thể xóa')
                ->body($service->lastError ?? 'Vui lòng thử lại.')
                ->danger()
                ->send();

            return;
        }

        if ($this->loadedSavedListId === $savedListId) {
            $this->loadedSavedListId = null;
            $this->loadedSavedListName = null;
        }

        $this->form->fill([
            ...$this->form->getState(),
            'saved_list_id' => null,
        ]);

        Notification::make()->title('Đã xóa danh sách')->success()->send();
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
                ->body('Nhập dữ liệu, import file hoặc chọn danh sách đã lưu.')
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

        $this->resetFormAfterPublish();
        $this->refreshQueue();
        $this->activeTab = 'queue';
    }

    public function refreshQueue(): void
    {
        $service = app(InstagramQueueService::class);
        $service->recoverStaleProcessingItems();
        $this->queueStats = $service->queueStats();
        $this->queueIntervalMinutes = $service->intervalMinutes();

        if ($this->activeTab === 'queue') {
            $this->resetTable();
        }
    }

    public function cancelPendingQueue(): void
    {
        $service = app(InstagramQueueService::class);

        if (! $service->hasPendingQueue()) {
            Notification::make()
                ->title('Không có bài đang chờ')
                ->warning()
                ->send();

            return;
        }

        $cancelled = $service->cancelPendingQueue();

        Notification::make()
            ->title('Đã hủy hàng đợi')
            ->body("Đã xóa {$cancelled} bài đang chờ.")
            ->success()
            ->send();

        $this->refreshQueue();
    }

    public function canCancelPendingQueue(): bool
    {
        return app(InstagramQueueService::class)->hasPendingQueue();
    }

    protected function prepareRecordsFromForm(): bool
    {
        $state = $this->form->getState();
        $this->data = $state;

        if (! filled($state['import_file'] ?? null)) {
            return true;
        }

        return $this->runImportFromForm($state) !== null;
    }

    protected function resetFormAfterPublish(): void
    {
        $this->loadedSavedListId = null;
        $this->loadedSavedListName = null;

        $this->form->fill([
            'records' => [$this->emptyRecordRow()],
            'import_file' => null,
            'saved_list_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $state
     */
    protected function runImportFromForm(?array $state = null): ?int
    {
        $state ??= $this->form->getState();
        $path = $this->resolveImportPathFromState($state);

        if ($path === null) {
            Notification::make()
                ->title('Chưa chọn file')
                ->body('Upload file Excel/CSV trước.')
                ->warning()
                ->send();

            return null;
        }

        $import = app(InstagramImportService::class);
        $items = $import->parseFile($path);

        if ($items === []) {
            Notification::make()
                ->title('Import thất bại')
                ->body($import->lastError ?? 'Không đọc được file.')
                ->danger()
                ->send();

            return null;
        }

        $existing = collect($state['records'] ?? [])
            ->filter(fn (array $record): bool => $this->recordRowHasContent($record))
            ->values()
            ->all();

        $this->loadedSavedListId = null;
        $this->loadedSavedListName = null;

        $this->form->fill([
            'records' => array_values(array_merge($existing, $items)),
            'import_file' => null,
            'saved_list_id' => null,
        ]);

        $this->deleteImportFile($path);

        return count($items);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function resolveImportPathFromState(array $state): ?string
    {
        $file = $state['import_file'] ?? null;

        if (blank($file)) {
            return null;
        }

        if (is_array($file)) {
            $file = $file[array_key_first($file)] ?? null;
        }

        if (blank($file)) {
            return null;
        }

        $disk = Storage::disk('local');

        if (! $disk->exists((string) $file)) {
            return null;
        }

        return $disk->path((string) $file);
    }

    protected function deleteImportFile(string $absolutePath): void
    {
        $relative = str_replace(Storage::disk('local')->path(''), '', $absolutePath);
        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        if ($relative !== '' && Storage::disk('local')->exists($relative)) {
            Storage::disk('local')->delete($relative);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function validRecordsFromForm(): array
    {
        return collect($this->data['records'] ?? [])
            ->filter(fn (array $record): bool => $this->recordRowHasContent($record))
            ->map(function (array $record): array {
                $media = $this->normalizeMediaPath($record['media'] ?? null);
                $split = $this->splitMediaUpload($media);

                return [
                    ...$record,
                    'media' => $media,
                    'image' => $split['image'],
                    'video' => $split['video'],
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
        if (filled($this->normalizeMediaPath($record['media'] ?? null))) {
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
            'media' => null,
            'brand_domain' => null,
            'content_idea' => null,
            'aff_link' => null,
            'coupon_codes' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function mediaRepeaterItemLabel(array $state): string
    {
        $media = $this->normalizeMediaPath($state['media'] ?? null);
        if ($media === null) {
            return 'Bài mới';
        }

        return $this->isVideoMediaPath($media) ? 'Có video' : 'Có ảnh';
    }

    protected function normalizeMediaPath(mixed $media): ?string
    {
        if (is_array($media)) {
            $media = $media[0] ?? null;
        }

        if (! is_string($media) || ! filled($media)) {
            return null;
        }

        return trim($media);
    }

    /**
     * @return array{image: ?string, video: ?string}
     */
    protected function splitMediaUpload(?string $path): array
    {
        if ($path === null) {
            return ['image' => null, 'video' => null];
        }

        if ($this->isVideoMediaPath($path)) {
            return ['image' => null, 'video' => $path];
        }

        return ['image' => $path, 'video' => null];
    }

    protected function isVideoMediaPath(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['mp4', 'mov', 'qt', 'm4v'], true);
    }
}
