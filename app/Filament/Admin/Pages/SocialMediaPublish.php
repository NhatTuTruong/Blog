<?php

namespace App\Filament\Admin\Pages;

use App\Exports\InstagramTemplateExport;
use App\Filament\Admin\Pages\SystemSettings;
use App\Filament\Admin\Concerns\ManagesFacebookPublish;
use App\Filament\Concerns\HasFormDraft;
use App\Models\InstagramAccount;
use App\Models\InstagramQueueItem;
use App\Models\InstagramSavedList;
use App\Models\User;
use App\Services\FacebookImportService;
use App\Services\FacebookQueueService;
use App\Services\InstagramGraphService;
use App\Services\InstagramImportService;
use App\Services\InstagramQueueService;
use App\Services\InstagramSavedListService;
use App\Support\InstagramSettings;
use App\Support\FormDraftService;
use App\Support\PublicStorage;
use App\Support\UploadLimits;
use App\Filament\Admin\Support\SocialMediaQueueTable;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Illuminate\Validation\Rules\File;
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
use Livewire\Attributes\Url;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SocialMediaPublish extends Page implements HasForms, HasTable
{
    use HasFormDraft;
    use InteractsWithForms;
    use InteractsWithTable;
    use ManagesFacebookPublish;

    protected static ?string $navigationIcon = 'heroicon-o-share';

    protected static string $view = 'filament.admin.pages.social-media-publish';

    protected static ?string $navigationLabel = 'Đăng bài mạng xã hội';

    protected static ?string $title = 'Đăng bài mạng xã hội';

    protected static ?string $navigationGroup = 'Mạng xã hội';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'social-media-publish';

    /** @var array<string, mixed> */
    public array $instagramData = [];

    /** @var array<string, mixed> */
    public array $facebookData = [];

    #[Url(as: 'platform', history: true)]
    public string $activePlatform = 'instagram';

    #[Url(as: 'tab', history: true)]
    public string $activeTab = 'compose';

    /** @var array{pending: int, processing: int, completed: int, failed: int} */
    public array $queueStats = [
        'pending' => 0,
        'processing' => 0,
        'completed' => 0,
        'failed' => 0,
    ];

    /** @var array{pending: int, processing: int, completed: int, failed: int} */
    public array $instagramQueueStats = [
        'pending' => 0,
        'processing' => 0,
        'completed' => 0,
        'failed' => 0,
    ];

    /** @var array{pending: int, processing: int, completed: int, failed: int} */
    public array $facebookQueueStats = [
        'pending' => 0,
        'processing' => 0,
        'completed' => 0,
        'failed' => 0,
    ];

    public int $queueIntervalMinutes = 30;

    public int $instagramQueueIntervalMinutes = 30;

    public int $facebookQueueIntervalMinutes = 30;

    public ?string $instagramAccountLabel = null;

    protected ?int $instagramLoadedSavedListId = null;

    protected ?string $instagramLoadedSavedListName = null;

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
        return $this->getRecordCountForPlatform($this->activePlatform);
    }

    public function getRecordCountForPlatform(string $platform): int
    {
        $data = $this->platformFormData($platform);

        return collect($data['records'] ?? [])
            ->filter(fn (array $record): bool => $this->recordRowHasContent($record))
            ->count();
    }

    public function getPendingCountForPlatform(string $platform): int
    {
        $stats = $platform === 'facebook' ? $this->facebookQueueStats : $this->instagramQueueStats;

        return ($stats['pending'] ?? 0) + ($stats['processing'] ?? 0);
    }

    public function mount(): void
    {
        $this->activePlatform = $this->sanitizePlatform($this->activePlatform);
        $this->activeTab = $this->sanitizeTab($this->activeTab);

        $this->instagramData = $this->defaultComposeFormState();
        $this->facebookData = $this->defaultComposeFormState();

        $this->loadAllQueueStats();
        $this->applyActivePlatformQueueStats();
        $this->syncLoadedSavedListMetaFromPlatform();

        $this->ensureMediaUploadDirectories();
        $this->instagramForm->fill($this->instagramData);
        $this->facebookForm->fill($this->facebookData);

        $this->restoreFormDraft();

        $this->refreshInstagramAccountLabel();
        $this->refreshFacebookAccountLabel();

        if ($this->activeTab === 'queue') {
            $this->refreshQueue();
        }
    }

    public function switchPlatform(string $platform): void
    {
        $platform = $this->sanitizePlatform($platform);
        $previousPlatform = $this->sanitizePlatform($this->activePlatform);

        if ($this->activeTab === 'compose' && $platform !== $previousPlatform) {
            $this->persistFormDraftForPlatform($previousPlatform);
        }

        $this->activePlatform = $platform;
        $this->syncLoadedSavedListMetaFromPlatform();
        $this->applyActivePlatformQueueStats();
        $this->dispatchQueueStatsToBrowser();

        if ($this->activeTab === 'queue' && $platform !== $previousPlatform) {
            $this->resetTable();
        }
    }

    public function switchTab(string $tab): void
    {
        $tab = $this->sanitizeTab($tab);
        $previousTab = $this->sanitizeTab($this->activeTab);

        if ($previousTab === 'compose' && $tab !== $previousTab) {
            $this->persistFormDraft();
        }

        $this->activeTab = $tab;

        if ($tab === 'queue' && $tab !== $previousTab) {
            $this->refreshQueue();
        }
    }

    protected function persistFormDraftForPlatform(string $platform): void
    {
        $userId = $this->formDraftUserId();

        if ($userId === null) {
            return;
        }

        $key = $platform === 'facebook'
            ? $this->facebookFormDraftKey()
            : FormDraftService::key('instagram_publish');

        $data = $platform === 'facebook' ? $this->facebookData : $this->instagramData;

        if (! $this->formDraftHasContent($data)) {
            FormDraftService::delete($userId, $key);

            return;
        }

        FormDraftService::save($userId, $key, $data);
    }

    public function updatedActivePlatform(): void
    {
        $this->activePlatform = $this->sanitizePlatform($this->activePlatform);
        $this->syncLoadedSavedListMetaFromPlatform();
        $this->applyActivePlatformQueueStats();

        if ($this->activeTab === 'queue') {
            $this->resetTable();
        }
    }

    public function updatedActiveTab(): void
    {
        $this->activeTab = $this->sanitizeTab($this->activeTab);

        if ($this->activeTab === 'queue') {
            $this->refreshQueue();
        }
    }

    public function updated(mixed $property): void
    {
        if (! is_string($property)) {
            return;
        }

        $isInstagram = $property === 'instagramData' || str_starts_with($property, 'instagramData.');
        $isFacebook = $property === 'facebookData' || str_starts_with($property, 'facebookData.');

        if (! $isInstagram && ! $isFacebook) {
            return;
        }

        $editedPlatform = $isFacebook ? 'facebook' : 'instagram';

        if ($editedPlatform !== $this->activePlatform) {
            return;
        }

        if (str_contains($property, '.media')) {
            return;
        }

        $this->persistFormDraft();
    }

    protected function ensureMediaUploadDirectories(): void
    {
        foreach ([
            'instagram-uploads',
            'instagram-temp-videos',
            'facebook-uploads',
            'facebook-temp-videos',
        ] as $directory) {
            PublicStorage::ensureDirectory($directory);
        }

        foreach (['instagram-imports', 'facebook-imports'] as $directory) {
            if (! Storage::disk('local')->exists($directory)) {
                Storage::disk('local')->makeDirectory($directory);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    protected function getForms(): array
    {
        return [
            'instagramForm',
            'facebookForm',
        ];
    }

    public function instagramForm(Form $form): Form
    {
        return $form
            ->schema($this->getInstagramFormSchema())
            ->statePath('instagramData');
    }

    public function facebookForm(Form $form): Form
    {
        return $form
            ->schema($this->getFacebookFormSchema())
            ->statePath('facebookData');
    }

    /**
     * @return array<string, mixed>
     */
    protected function activeFormData(): array
    {
        return $this->activePlatform === 'facebook' ? $this->facebookData : $this->instagramData;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function fillActivePlatformForm(array $state): void
    {
        if ($this->activePlatform === 'facebook') {
            $this->facebookData = $state;
            $this->facebookForm->fill($state);

            return;
        }

        $this->instagramData = $state;
        $this->instagramForm->fill($state);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function fillInstagramForm(array $state): void
    {
        $this->instagramData = $state;
        $this->instagramForm->fill($state);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function fillFacebookForm(array $state): void
    {
        $this->facebookData = $state;
        $this->facebookForm->fill($state);
    }

    protected function sanitizePlatform(string $platform): string
    {
        return in_array($platform, ['instagram', 'facebook'], true) ? $platform : 'instagram';
    }

    protected function sanitizeTab(string $tab): string
    {
        return in_array($tab, ['compose', 'queue'], true) ? $tab : 'compose';
    }

    /**
     * @return array<string, mixed>
     */
    protected function platformFormData(string $platform): array
    {
        $stored = $platform === 'facebook' ? $this->facebookData : $this->instagramData;

        return $stored !== [] ? $stored : $this->defaultComposeFormState();
    }

    protected function syncLoadedSavedListMetaFromPlatform(): void
    {
        if ($this->activePlatform === 'facebook') {
            $this->loadedSavedListId = $this->facebookLoadedSavedListId;
            $this->loadedSavedListName = $this->facebookLoadedSavedListName;

            return;
        }

        $this->loadedSavedListId = $this->instagramLoadedSavedListId;
        $this->loadedSavedListName = $this->instagramLoadedSavedListName;
    }

    protected function loadAllQueueStats(): void
    {
        $instagramService = app(InstagramQueueService::class);
        $this->instagramQueueStats = $instagramService->queueStats();
        $this->instagramQueueIntervalMinutes = $instagramService->intervalMinutes();

        $facebookService = app(FacebookQueueService::class);
        $this->facebookQueueStats = $facebookService->queueStats();
        $this->facebookQueueIntervalMinutes = $facebookService->intervalMinutes();
    }

    protected function applyActivePlatformQueueStats(): void
    {
        if ($this->activePlatform === 'facebook') {
            $this->queueStats = $this->facebookQueueStats;
            $this->queueIntervalMinutes = $this->facebookQueueIntervalMinutes;

            return;
        }

        $this->queueStats = $this->instagramQueueStats;
        $this->queueIntervalMinutes = $this->instagramQueueIntervalMinutes;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultComposeFormState(): array
    {
        return [
            'records' => [$this->emptyRecordRow()],
            'import_file' => null,
            'saved_list_id' => null,
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function getInstagramFormSchema(): array
    {
        return [
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
                                $this->importExcelFileUploadField('instagram-imports')
                                    ->columnSpan(['default' => 12, 'md' => 4]),
                                Placeholder::make('summary')
                                    ->label('Tóm tắt')
                                    ->content(function (): string {
                                        $parts = [$this->getRecordCountForPlatform('instagram').' bài sẵn sàng'];

                                        if ($this->instagramLoadedSavedListName) {
                                            $parts[] = 'Đang mở: '.$this->instagramLoadedSavedListName;
                                        }

                                        $parts[] = 'Cách '.$this->instagramQueueIntervalMinutes.' phút/bài';

                                        return implode(' · ', $parts);
                                    })
                                    ->columnSpan(['default' => 12, 'md' => 3]),
                            ]),
                    ]),
                Section::make('Chi tiết bài đăng Instagram')
                    ->description(fn (): string => InstagramSettings::isConfigured()
                        ? ''
                        : 'Chưa cấu hình Instagram — vào Cài đặt hệ thống để thêm tài khoản.')
                    ->schema([
                        Placeholder::make('instagram_status')
                            ->label('Tài khoản Instagram')
                            ->content(fn (): string => $this->instagramAccountLabel
                                ?? (InstagramSettings::isConfigured() ? 'Đã cấu hình' : 'Chưa cấu hình'))
                            ->helperText(fn (): ?string => InstagramSettings::isConfigured()
                                ? null
                                : 'Mở Cài đặt hệ thống → Instagram để thêm một hoặc nhiều tài khoản.'),
                        Repeater::make('records')
                            ->label('')
                            ->columns(6)
                            ->defaultItems(1)
                            ->collapsible()
                            ->cloneable()
                            ->addActionLabel('Thêm dòng')
                            ->itemLabel(fn (array $state): ?string => filled($state['brand_domain'] ?? null)
                                ? (string) $state['brand_domain']
                                : (filled($state['content_idea'] ?? null)
                                    ? Str::limit((string) $state['content_idea'], 40)
                                    : $this->mediaRepeaterItemLabel($state)))
                            ->schema([
                                ...$this->socialMediaRepeaterUploadFields('instagram-uploads', 'instagram-temp-videos'),
                                TextInput::make('brand_domain')
                                    ->label('Domain brand')
                                    ->placeholder('nike.com')
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 6, 'md' => 2]),
                                TextInput::make('aff_link')
                                    ->label('Link Affiliate')
                                    ->url()
                                    ->maxLength(2048)
                                    ->placeholder('https://…')
                                    ->columnSpan(['default' => 6, 'md' => 2]),
                                Textarea::make('content_idea')
                                    ->label('Ý tưởng caption cho AI')
                                    ->rows(4)
                                    ->maxLength(2000)
                                    ->helperText('Để trống AI viết đoạn Instagram ngắn giới thiệu cửa hàng. Có ý tưởng sẽ bám sát hơn.')
                                    ->columnSpan(['default' => 6, 'md' => 4]),
                                TagsInput::make('coupon_codes')
                                    ->label('Coupon')
                                    ->placeholder('Enter')
                                    ->columnSpan(['default' => 6, 'md' => 2]),
                            ]),
                    ]),
        ];
    }

    public function table(Table $table): Table
    {
        if ($this->activePlatform === 'facebook') {
            return $this->facebookTable($table);
        }

        return $table
            ->query(
                InstagramQueueItem::query()->with('instagramAccount')->latest('id')
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
                Tables\Columns\TextColumn::make('instagramAccount.name')
                    ->label('Tài khoản IG')
                    ->formatStateUsing(fn ($state, InstagramQueueItem $record): string => $record->instagramAccount?->displayLabel() ?? '—')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('brand_domain')
                    ->label('Brand')
                    ->placeholder('—')
                    ->searchable(),
                SocialMediaQueueTable::statusColumn(),
                Tables\Columns\TextColumn::make('caption')
                    ->label('Caption')
                    ->limit(50)
                    ->placeholder('Tạo khi tới lượt')
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
            ->actions([
                SocialMediaQueueTable::detailAction('instagram'),
            ])
            ->bulkActions(SocialMediaQueueTable::bulkActions())
            ->emptyStateHeading('Chưa có bài trong hàng đợi')
            ->emptyStateDescription('Đăng danh sách bài Instagram để bắt đầu xếp hàng tự động.');
    }

    protected function getHeaderActions(): array
    {
        if ($this->activePlatform === 'facebook') {
            return array_merge([
                $this->getFormDraftDiscardAction()
                    ->visible(fn (): bool => $this->activeTab === 'compose' && $this->formDraftExists()),
            ], $this->getFacebookHeaderActions());
        }

        return [
            $this->getFormDraftDiscardAction()
                ->visible(fn (): bool => $this->activeTab === 'compose' && $this->formDraftExists()),
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
                ->modalHeading('Đăng danh sách lên Instagram')
                ->modalDescription(function (): string {
                    $service = app(InstagramQueueService::class);
                    $postCount = $this->getRecordCount();
                    $accountCount = count(InstagramAccount::enabledConfiguredIds());
                    $total = $postCount * max(1, $accountCount);
                    $base = $postCount > 0
                        ? "Sẽ xếp hàng {$total} lượt đăng ({$postCount} bài × tài khoản đã chọn). Mỗi lượt cách {$this->queueIntervalMinutes} phút. AI tạo caption khi tới lượt."
                        : 'Chưa có bài hợp lệ — nhập dữ liệu, import file hoặc chọn danh sách đã lưu.';

                    if ($service->hasActiveQueue()) {
                        $base .= ' Lưu ý: đang có hàng đợi ('.$service->activeQueueSummary().').';
                    }

                    if (! InstagramSettings::isConfigured()) {
                        $base .= ' Instagram chưa được cấu hình — thêm tài khoản trong Cài đặt hệ thống.';
                    }

                    return $base;
                })
                ->modalSubmitActionLabel('Bắt đầu')
                ->form([
                    CheckboxList::make('instagram_account_ids')
                        ->label('Tài khoản Instagram')
                        ->options(fn (): array => InstagramAccount::optionsForSelect())
                        ->default(fn (): array => InstagramAccount::enabledConfiguredIds())
                        ->columns(1)
                        ->required()
                        ->visible(fn (): bool => InstagramAccount::optionsForSelect() !== [])
                        ->helperText('Mặc định chọn tất cả. Bỏ chọn tài khoản không muốn đăng.'),
                    Placeholder::make('no_instagram_accounts')
                        ->label('Tài khoản Instagram')
                        ->content('Chưa có tài khoản — vào Cài đặt hệ thống → Instagram để thêm.')
                        ->visible(fn (): bool => InstagramAccount::optionsForSelect() === []),
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
                    ->visible(fn (): bool => $this->instagramLoadedSavedListId !== null
                        || filled($this->instagramData['saved_list_id'] ?? null))
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

        $this->instagramLoadedSavedListId = $list->id;
        $this->instagramLoadedSavedListName = $list->name;

        if ($this->activePlatform === 'instagram') {
            $this->loadedSavedListId = $list->id;
            $this->loadedSavedListName = $list->name;
        }

        $this->fillInstagramForm([
            ...$this->instagramData,
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

        $this->instagramLoadedSavedListId = $list->id;
        $this->instagramLoadedSavedListName = $list->name;

        if ($this->activePlatform === 'instagram') {
            $this->loadedSavedListId = $list->id;
            $this->loadedSavedListName = $list->name;
        }

        $this->fillInstagramForm([
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
        $savedListId = (int) ($this->instagramData['saved_list_id'] ?? $this->instagramLoadedSavedListId ?? 0);

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

        if ($this->instagramLoadedSavedListId === $savedListId) {
            $this->instagramLoadedSavedListId = null;
            $this->instagramLoadedSavedListName = null;
        }

        if ($this->loadedSavedListId === $savedListId) {
            $this->loadedSavedListId = null;
            $this->loadedSavedListName = null;
        }

        $this->fillInstagramForm([
            ...$this->instagramData,
            'saved_list_id' => null,
        ]);

        Notification::make()->title('Đã xóa danh sách')->success()->send();
    }

    public function testInstagramConnection(): void
    {
        $accounts = InstagramAccount::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (InstagramAccount $account): bool => $account->isConfigured());

        if ($accounts->isEmpty()) {
            Notification::make()
                ->title('Chưa có tài khoản Instagram')
                ->body('Thêm tài khoản trong Cài đặt hệ thống → Instagram.')
                ->warning()
                ->send();

            return;
        }

        $graph = app(InstagramGraphService::class);
        $ok = [];
        $failed = [];

        foreach ($accounts as $account) {
            $result = $graph->forAccount($account)->testConnection($account);

            if ($result === null) {
                $failed[] = $account->displayLabel().': '.($graph->lastError ?? 'lỗi');
                continue;
            }

            $label = '@'.($result['username'] ?? $result['id']);
            if (filled($result['name'] ?? null)) {
                $label .= ' ('.$result['name'].')';
            }

            $ok[] = $account->displayLabel().' → '.$label;
        }

        if ($ok !== []) {
            $this->instagramAccountLabel = count($ok).' tài khoản OK';
        }

        if ($failed === []) {
            Notification::make()
                ->title('Kết nối Instagram thành công')
                ->body(implode("\n", $ok))
                ->success()
                ->send();

            return;
        }

        $notification = Notification::make()
            ->title($ok !== [] ? 'Một số tài khoản lỗi' : 'Không kết nối được Instagram')
            ->body(trim(collect($ok)->merge($failed)->implode("\n")));

        if ($ok !== []) {
            $notification->warning()->send();
        } else {
            $notification->danger()->send();
        }
    }

    public function refreshInstagramAccountLabel(): void
    {
        if (! InstagramSettings::isConfigured()) {
            $this->instagramAccountLabel = null;

            return;
        }

        $labels = InstagramAccount::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (InstagramAccount $account): bool => $account->isConfigured())
            ->map(fn (InstagramAccount $account): string => $account->displayLabel())
            ->values()
            ->all();

        $this->instagramAccountLabel = $labels !== []
            ? implode(' · ', $labels)
            : 'Chưa có tài khoản hợp lệ';
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

        $accountIds = collect($data['instagram_account_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($accountIds === []) {
            Notification::make()
                ->title('Chưa chọn tài khoản Instagram')
                ->body('Chọn ít nhất một tài khoản trong popup đăng bài.')
                ->warning()
                ->send();

            return;
        }

        $startAt = ($data['publish_mode'] ?? 'immediate') === 'scheduled'
            ? Carbon::parse($data['scheduled_start_at'])
            : now();

        $service = app(InstagramQueueService::class);
        $batchId = $service->enqueue($records, Filament::auth()->user(), $startAt, $accountIds);

        if ($batchId === null) {
            Notification::make()
                ->title('Không thể xếp hàng')
                ->body($service->lastError ?? 'Vui lòng kiểm tra lại.')
                ->danger()
                ->send();

            return;
        }

        $postCount = count($records);
        $queueCount = $postCount * count($accountIds);
        $minutes = ($queueCount - 1) * $this->queueIntervalMinutes;
        $startLabel = $startAt->isFuture()
            ? $startAt->format('d/m/Y H:i')
            : 'ngay bây giờ';

        Notification::make()
            ->title('Đã xếp hàng '.$queueCount.' lượt đăng')
            ->body($minutes > 0
                ? "({$postCount} bài × ".count($accountIds)." TK) Bắt đầu {$startLabel} · cách {$this->queueIntervalMinutes} phút/lượt."
                : "({$postCount} bài × ".count($accountIds)." TK) Bắt đầu từ {$startLabel}.")
            ->success()
            ->send();

        $this->resetFormAfterPublish();
        $this->clearFormDraft();
        $this->refreshQueue();
        $this->activeTab = 'queue';
    }

    public function refreshQueue(): void
    {
        $instagramService = app(InstagramQueueService::class);
        $instagramService->recoverStaleProcessingItems();
        $this->instagramQueueStats = $instagramService->queueStats();
        $this->instagramQueueIntervalMinutes = $instagramService->intervalMinutes();

        $facebookService = app(FacebookQueueService::class);
        $facebookService->recoverStaleProcessingItems();
        $this->facebookQueueStats = $facebookService->queueStats();
        $this->facebookQueueIntervalMinutes = $facebookService->intervalMinutes();

        $this->applyActivePlatformQueueStats();
        $this->dispatchQueueStatsToBrowser();

        if ($this->activeTab === 'queue') {
            $this->resetTable();
        }
    }

    protected function dispatchQueueStatsToBrowser(): void
    {
        $this->dispatch(
            'queue-stats-synced',
            instagram: $this->instagramQueueStats,
            facebook: $this->facebookQueueStats,
            instagramInterval: $this->instagramQueueIntervalMinutes,
            facebookInterval: $this->facebookQueueIntervalMinutes,
        );
    }

    public function releaseStuckProcessing(): void
    {
        if ($this->activePlatform === 'facebook') {
            $this->releaseFacebookStuckProcessing();

            return;
        }

        $service = app(InstagramQueueService::class);

        if (! $service->hasStuckProcessing()) {
            Notification::make()
                ->title('Không có bài bị kẹt')
                ->body('Không có bài nào đang ở trạng thái «Đang đăng».')
                ->warning()
                ->send();

            return;
        }

        $released = $service->releaseStuckProcessingItems(force: true);

        Notification::make()
            ->title('Đã mở kẹt hàng đợi')
            ->body("Đã đưa {$released} bài về «Chờ đăng» để thử lại.")
            ->success()
            ->send();

        $this->refreshQueue();
    }

    public function canReleaseStuckProcessing(): bool
    {
        if ($this->activePlatform === 'facebook') {
            return $this->canReleaseFacebookStuckProcessing();
        }

        return app(InstagramQueueService::class)->hasStuckProcessing();
    }

    public function cancelPendingQueue(): void
    {
        if ($this->activePlatform === 'facebook') {
            $this->cancelFacebookPendingQueue();

            return;
        }

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
        if ($this->activePlatform === 'facebook') {
            return $this->canCancelFacebookPendingQueue();
        }

        return app(InstagramQueueService::class)->hasPendingQueue();
    }

    protected function prepareRecordsFromForm(): bool
    {
        return $this->preparePlatformRecordsFromForm($this->activePlatform);
    }

    protected function prepareFacebookRecordsFromForm(): bool
    {
        return $this->preparePlatformRecordsFromForm('facebook');
    }

    protected function preparePlatformRecordsFromForm(string $platform): bool
    {
        $this->syncComposeFormState($platform);

        $state = $this->platformFormData($platform);

        if (! filled($state['import_file'] ?? null)) {
            return true;
        }

        return $this->runImportFromForm($state, $platform) !== null;
    }

    protected function syncComposeFormState(string $platform, bool $shouldValidate = true): void
    {
        $this->normalizeComposeFormFileState($platform);

        if ($platform === 'facebook') {
            $this->facebookData = $this->facebookForm->getState($shouldValidate);

            return;
        }

        $this->instagramData = $this->instagramForm->getState($shouldValidate);
    }

    protected function syncImportFileState(string $platform): void
    {
        $property = $platform === 'facebook' ? 'facebookData' : 'instagramData';
        $form = $platform === 'facebook' ? $this->facebookForm : $this->instagramForm;
        $data = is_array($this->{$property}) ? $this->{$property} : [];

        if (is_array($data['import_file'] ?? null)) {
            $data['import_file'] = $this->normalizeImportFileState($data['import_file']);
            $this->{$property} = $data;
            $form->fill($data);
        }

        $importField = collect($form->getFlatFields(withHidden: true))
            ->first(fn (mixed $field): bool => $field instanceof FileUpload && $field->getName() === 'import_file');

        if (! $importField instanceof FileUpload) {
            return;
        }

        $importField->saveUploadedFiles();

        $data['import_file'] = $importField->getState();
        $this->{$property} = $data;
    }

    protected function normalizeComposeFormFileState(string $platform): void
    {
        $property = $platform === 'facebook' ? 'facebookData' : 'instagramData';
        $data = $this->{$property};

        if (! is_array($data)) {
            return;
        }

        if (is_array($data['import_file'] ?? null)) {
            $data['import_file'] = $this->normalizeImportFileState($data['import_file']);
        }

        if (is_array($data['records'] ?? null)) {
            $data['records'] = collect($data['records'])
                ->map(function (mixed $record): mixed {
                    if (! is_array($record)) {
                        return $record;
                    }

                    if (is_array($record['media'] ?? null)) {
                        $record['media'] = $record['media'][array_key_first($record['media'])] ?? null;
                    }

                    return $record;
                })
                ->all();
        }

        $this->{$property} = $data;

        if ($platform === 'facebook') {
            $this->facebookForm->fill($data);
        } else {
            $this->instagramForm->fill($data);
        }
    }

    protected function validFacebookRecordsFromForm(): array
    {
        return $this->validRecordsFromPlatform('facebook');
    }

    protected function validRecordsFromPlatform(string $platform): array
    {
        $this->syncComposeFormState($platform);

        return collect($this->platformFormData($platform)['records'] ?? [])
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

    protected function resetFormAfterPublish(): void
    {
        $state = $this->defaultComposeFormState();

        if ($this->activePlatform === 'facebook') {
            $this->facebookLoadedSavedListId = null;
            $this->facebookLoadedSavedListName = null;
            $this->loadedSavedListId = null;
            $this->loadedSavedListName = null;
            $this->fillFacebookForm($state);

            return;
        }

        $this->instagramLoadedSavedListId = null;
        $this->instagramLoadedSavedListName = null;
        $this->loadedSavedListId = null;
        $this->loadedSavedListName = null;
        $this->fillInstagramForm($state);
    }

    protected function formDraftKey(): string
    {
        return $this->activePlatform === 'facebook'
            ? $this->facebookFormDraftKey()
            : FormDraftService::key('instagram_publish');
    }

    protected function formDraftIgnoredFields(): array
    {
        return ['import_file'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function formDraftHasContent(array $data): bool
    {
        if (filled($data['saved_list_id'] ?? null)) {
            return true;
        }

        $records = $data['records'] ?? [];

        if (! is_array($records)) {
            return false;
        }

        return collect($records)
            ->filter(fn (mixed $record): bool => is_array($record) && $this->recordRowHasContent($record))
            ->isNotEmpty();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDraftBeforeRestore(array $data): array
    {
        $data['import_file'] = null;

        if (! is_array($data['records'] ?? null) || $data['records'] === []) {
            $data['records'] = [$this->emptyRecordRow()];
        }

        $savedListId = (int) ($data['saved_list_id'] ?? 0);
        if ($savedListId > 0) {
            $list = $this->activePlatform === 'facebook'
                ? \App\Models\FacebookSavedList::query()->find($savedListId)
                : InstagramSavedList::query()->find($savedListId);
            if ($list) {
                if ($this->activePlatform === 'facebook') {
                    $this->facebookLoadedSavedListId = $list->id;
                    $this->facebookLoadedSavedListName = $list->name;
                } else {
                    $this->instagramLoadedSavedListId = $list->id;
                    $this->instagramLoadedSavedListName = $list->name;
                }

                $this->loadedSavedListId = $list->id;
                $this->loadedSavedListName = $list->name;
            } else {
                $data['saved_list_id'] = null;
            }
        }

        return $data;
    }

    protected function resetPageFormAfterDraftDiscard(): void
    {
        $state = $this->defaultComposeFormState();

        if ($this->activePlatform === 'facebook') {
            $this->facebookLoadedSavedListId = null;
            $this->facebookLoadedSavedListName = null;
            $this->loadedSavedListId = null;
            $this->loadedSavedListName = null;
            $this->fillFacebookForm($state);
        } else {
            $this->instagramLoadedSavedListId = null;
            $this->instagramLoadedSavedListName = null;
            $this->loadedSavedListId = null;
            $this->loadedSavedListName = null;
            $this->fillInstagramForm($state);
        }

        $this->formDraftRestored = false;
    }

    protected function restoreFormDraft(): void
    {
        $userId = $this->formDraftUserId();

        if ($userId === null) {
            return;
        }

        $data = FormDraftService::get($userId, $this->formDraftKey());

        if ($data === null || ! $this->formDraftHasContent($data)) {
            return;
        }

        $data = $this->mutateFormDraftBeforeRestore($data);

        $this->fillActivePlatformForm($data);
        $this->formDraftRestored = true;

        Notification::make()
            ->title('Đã khôi phục bản nháp')
            ->body('Nội dung chỉnh sửa trước đó đã được tải lại. Bạn có thể tiếp tục chỉnh sửa.')
            ->info()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formDraftDataForStorage(): array
    {
        $data = $this->activeFormData();

        if (! is_array($data['records'] ?? null)) {
            return $data;
        }

        $data['records'] = collect($data['records'])
            ->map(function (mixed $record): mixed {
                if (! is_array($record)) {
                    return $record;
                }

                $record['media'] = null;

                return $record;
            })
            ->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>|null  $state
     */
    protected function runImportFromForm(?array $state = null, ?string $platform = null): ?int
    {
        $platform = $this->sanitizePlatform($platform ?? $this->activePlatform);

        if ($state === null) {
            $this->syncImportFileState($platform);
            $state = $this->platformFormData($platform);
        }

        $path = $this->resolveImportPathFromState($state);

        if ($path === null) {
            $hasUpload = filled($this->normalizeImportFileState($state['import_file'] ?? null));

            Notification::make()
                ->title($hasUpload ? 'File chưa sẵn sàng' : 'Chưa chọn file')
                ->body($hasUpload
                    ? 'Đợi file hiển thị «Tải lên thành công» rồi bấm Import lại.'
                    : 'Upload file Excel/CSV trước.')
                ->warning()
                ->send();

            return null;
        }

        $import = $platform === 'facebook'
            ? app(FacebookImportService::class)
            : app(InstagramImportService::class);
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

        $imported = [
            'records' => array_values(array_merge($existing, $items)),
            'import_file' => null,
            'saved_list_id' => null,
        ];

        if ($platform === 'facebook') {
            $this->facebookLoadedSavedListId = null;
            $this->facebookLoadedSavedListName = null;
            $this->fillFacebookForm($imported);
        } else {
            $this->instagramLoadedSavedListId = null;
            $this->instagramLoadedSavedListName = null;
            $this->fillInstagramForm($imported);
        }

        if ($this->activePlatform === $platform) {
            $this->loadedSavedListId = null;
            $this->loadedSavedListName = null;
        }

        $this->deleteImportFile($path);

        return count($items);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function resolveImportPathFromState(array $state): ?string
    {
        $file = $this->normalizeImportFileState($state['import_file'] ?? null);

        if ($file === null) {
            return null;
        }

        $disk = Storage::disk('local');

        if ($disk->exists($file)) {
            return $disk->path($file);
        }

        foreach (['instagram-imports', 'facebook-imports'] as $directory) {
            $candidate = $directory.'/'.$file;
            if ($disk->exists($candidate)) {
                return $disk->path($candidate);
            }
        }

        return is_file($file) ? $file : null;
    }

    protected function normalizeImportFileState(mixed $file): ?string
    {
        if (is_array($file)) {
            $file = $file[array_key_first($file)] ?? null;
        }

        if (! is_string($file) || blank($file)) {
            return null;
        }

        return ltrim(str_replace('\\', '/', trim($file)), '/');
    }

    protected function importExcelFileUploadField(string $directory): FileUpload
    {
        return FileUpload::make('import_file')
            ->label('Import Excel / CSV')
            ->disk('local')
            ->directory($directory)
            ->visibility('private')
            ->extraInputAttributes([
                'accept' => '.csv,.txt,.xls,.xlsx,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->rule(File::types(['csv', 'txt', 'xls', 'xlsx'])->max(5120))
            ->maxSize(5120)
            ->fetchFileInformation(false)
            ->live()
            ->afterStateUpdated(function (FileUpload $component): void {
                $component->saveUploadedFiles();
            });
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
        return collect($this->activeFormData()['records'] ?? [])
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

        $path = trim($media);

        return PublicStorage::syncUploadedPath($path) ?? $path;
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

        return in_array($extension, ['mp4', 'mov', 'qt', 'm4v', 'webm'], true);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function socialMediaRepeaterUploadFields(string $uploadDir, string $videoDir): array
    {
        return [
            FileUpload::make('media')
                ->label('Ảnh hoặc video (tùy chọn)')
                ->mimeTypeMap(UploadLimits::mediaMimeTypeMap())
                ->extraInputAttributes([
                    'accept' => 'image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm,.jpg,.jpeg,.png,.webp,.gif,.mp4,.mov,.m4v,.webm',
                ])
                ->disk('public')
                ->directory($uploadDir)
                ->visibility('public')
                ->maxSize(UploadLimits::mediaMaxSizeKilobytes())
                ->fetchFileInformation(false)
                ->live()
                ->afterStateUpdated(function (FileUpload $component): void {
                    $component->saveUploadedFiles();
                })
                ->rule(File::types(UploadLimits::mediaFileExtensions())->max(UploadLimits::mediaMaxSizeKilobytes()))
                ->saveUploadedFileUsing(function ($file, BaseFileUpload $component) use ($uploadDir, $videoDir): ?string {
                    if (! $file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                        return null;
                    }

                    try {
                        $extension = strtolower((string) $file->getClientOriginalExtension());
                        $mimeType = strtolower((string) $file->getMimeType());
                        $isVideo = in_array($extension, ['mp4', 'mov', 'qt', 'm4v', 'webm'], true)
                            || str_starts_with($mimeType, 'video/');

                        $directory = $isVideo ? $videoDir : $uploadDir;
                        PublicStorage::ensureDirectory($directory);

                        $filename = $component->getUploadedFileNameForStorage($file);
                        $stored = PublicStorage::storeUploadedFile($file, $directory, $filename);

                        return PublicStorage::syncUploadedPath($stored);
                    } catch (\Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Không lưu được file')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        return null;
                    }
                })
                ->helperText(fn (): string => UploadLimits::mediaUploadHelperText())
                ->columnSpan(['default' => 6, 'md' => 2]),
        ];
    }
}
