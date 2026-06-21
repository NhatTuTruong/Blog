<?php

namespace App\Filament\Admin\Concerns;

use App\Exports\PinterestTemplateExport;
use App\Filament\Admin\Pages\SystemSettings;
use App\Filament\Admin\Support\SocialMediaQueueTable;
use App\Models\PinterestAccount;
use App\Models\PinterestQueueItem;
use App\Models\PinterestSavedList;
use App\Services\PinterestApiService;
use App\Services\PinterestQueueService;
use App\Services\PinterestSavedListService;
use App\Support\FormDraftService;
use App\Support\PinterestSettings;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait ManagesPinterestPublish
{
    public ?string $pinterestAccountLabel = null;

    protected ?int $pinterestLoadedSavedListId = null;

    protected ?string $pinterestLoadedSavedListName = null;

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function getPinterestFormSchema(): array
    {
        return [
            Section::make('Nguồn dữ liệu')
                ->description('Chọn danh sách đã lưu hoặc upload Excel. Import khi nhấn «Đăng bài» hoặc «Import file».')
                ->schema([
                    Grid::make(12)->schema([
                        Select::make('saved_list_id')
                            ->label('Danh sách đã lưu')
                            ->options(fn (): array => PinterestSavedList::optionsForSelect())
                            ->placeholder('— Chọn để tải —')
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (?string $state): void {
                                if (filled($state)) {
                                    $this->loadPinterestSavedListById((int) $state, silent: true);
                                }
                            })
                            ->columnSpan(['default' => 12, 'md' => 5]),
                        $this->importExcelFileUploadField('pinterest-imports')
                            ->columnSpan(['default' => 12, 'md' => 4]),
                        Placeholder::make('summary')
                            ->label('Tóm tắt')
                            ->content(fn (): string => implode(' · ', array_filter([
                                $this->getRecordCountForPlatform('pinterest').' bài sẵn sàng',
                                $this->pinterestLoadedSavedListName ? 'Đang mở: '.$this->pinterestLoadedSavedListName : null,
                                'Cách '.$this->pinterestQueueIntervalMinutes.' phút/bài',
                            ])))
                            ->columnSpan(['default' => 12, 'md' => 3]),
                    ]),
                ]),
            Section::make('Chi tiết Pin Pinterest')
                ->description(fn (): string => PinterestSettings::isConfigured()
                    ? 'AI viết title/description khi tới lượt đăng. Ưu tiên ảnh/video upload; không có thì Apify Google Images (domain brand); lỗi Apify → random ảnh default1–3.'
                    : 'Chưa cấu hình Pinterest — vào Cài đặt tùy chỉnh để thêm token.')
                ->schema([
                    Placeholder::make('pinterest_status')
                        ->label('Tài khoản Pinterest')
                        ->content(fn (): string => $this->pinterestAccountLabel
                            ?? (PinterestSettings::isConfigured() ? 'Đã kết nối token' : 'Chưa cấu hình')),
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
                            ...$this->socialMediaRepeaterUploadFields('pinterest-uploads', 'pinterest-temp-videos'),
                            TextInput::make('brand_domain')->label('Domain brand')->placeholder('nike.com')->maxLength(255)->columnSpan(['default' => 6, 'md' => 2]),
                            TextInput::make('aff_link')->label('Link Affiliate')->url()->maxLength(2048)->columnSpan(['default' => 6, 'md' => 2]),
                            Textarea::make('content_idea')->label('Ý tưởng nội dung cho AI')->rows(4)->maxLength(2000)->columnSpan(['default' => 6, 'md' => 4]),
                            TagsInput::make('coupon_codes')->label('Coupon')->placeholder('Enter')->columnSpan(['default' => 6, 'md' => 2]),
                        ]),
                ]),
        ];
    }

    public function pinterestTable(Table $table): Table
    {
        return $table
            ->query(PinterestQueueItem::query()->with('pinterestAccount')->latest('id'))
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')->label('Media')->disk('public')->height(48)->width(48)
                    ->defaultImageUrl(fn (PinterestQueueItem $record): string => filled($record->video_path)
                        ? 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48"><rect fill="#7f1d1d" width="48" height="48" rx="6"/><polygon fill="#fca5a5" points="20,16 34,24 20,32"/></svg>')
                        : 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48"><rect fill="#991b1b" width="48" height="48" rx="6"/><text x="24" y="28" text-anchor="middle" fill="#fecaca" font-size="10">Pin</text></svg>')),
                Tables\Columns\TextColumn::make('board_name')->label('Board')
                    ->formatStateUsing(fn ($state, PinterestQueueItem $record): string => filled($record->board_name)
                        ? (string) $record->board_name
                        : (filled($record->board_id) ? 'Board '.$record->board_id : ($record->pinterestAccount?->displayLabel() ?? '—'))),
                Tables\Columns\TextColumn::make('brand_domain')->label('Brand')->searchable(),
                SocialMediaQueueTable::queueSourceColumn(),
                SocialMediaQueueTable::statusColumn(),
                Tables\Columns\TextColumn::make('caption')->label('Nội dung')->limit(50)->placeholder('Tạo khi tới lượt')->toggleable(),
                Tables\Columns\TextColumn::make('scheduled_at')->label('Lên lịch')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('pinterest_pin_id')->label('Pin ID')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->actions([
                SocialMediaQueueTable::republishAction(),
                SocialMediaQueueTable::detailAction('pinterest'),
            ])
            ->bulkActions(SocialMediaQueueTable::bulkActions())
            ->emptyStateHeading('Chưa có Pin trong hàng đợi Pinterest');
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    protected function getPinterestHeaderActions(): array
    {
        return [
            Action::make('testPinterest')->label('Kiểm tra Pinterest')->icon('heroicon-o-signal')->color('gray')
                ->action(fn () => $this->testPinterestConnection()),
            Action::make('openPinterestSettings')->label('Cài đặt Pinterest')->icon('heroicon-o-cog-6-tooth')->color('gray')
                ->url(SystemSettings::getUrl()),
            Action::make('publishPinterest')->label('Đăng bài')->icon('heroicon-o-sparkles')->color('success')
                ->modalHeading('Đăng danh sách lên Pinterest')
                ->modalDescription(function (): string {
                    $service = app(PinterestQueueService::class);
                    $postCount = $this->getRecordCount();
                    $base = $postCount > 0
                        ? "Sẽ xếp hàng theo số Pin × số board đã chọn. Mỗi lượt cách {$this->queueIntervalMinutes} phút."
                        : 'Chưa có bài hợp lệ.';
                    if ($service->hasActiveQueue()) {
                        $base .= ' Lưu ý: đang có hàng đợi ('.$service->activeQueueSummary().').';
                    }
                    if (! PinterestSettings::isConfigured()) {
                        $base .= ' Pinterest chưa cấu hình — thêm token trong Cài đặt tùy chỉnh.';
                    }

                    return $base;
                })
                ->modalSubmitActionLabel('Bắt đầu')
                ->form([
                    Select::make('pinterest_account_id')
                        ->label('Tài khoản Pinterest')
                        ->options(fn (): array => PinterestAccount::optionsForSelect())
                        ->default(fn (): ?string => (string) (PinterestAccount::enabledConfiguredIds()[0] ?? '') ?: null)
                        ->searchable()
                        ->live()
                        ->required()
                        ->visible(fn (): bool => PinterestAccount::optionsForSelect() !== [])
                        ->helperText('Dùng token đã lưu để tải danh sách board.'),
                    CheckboxList::make('pinterest_board_ids')
                        ->label('Bảng (Board) đăng Pin')
                        ->options(fn (Get $get): array => $this->pinterestBoardOptionsForAccount($get('pinterest_account_id')))
                        ->columns(1)
                        ->required()
                        ->visible(fn (Get $get): bool => PinterestAccount::optionsForSelect() !== []
                            && $this->pinterestBoardOptionsForAccount($get('pinterest_account_id')) !== [])
                        ->helperText('Chọn một hoặc nhiều board từ tài khoản Pinterest.'),
                    Placeholder::make('no_pinterest_boards')
                        ->label('Bảng (Board)')
                        ->content(fn (Get $get): string => filled($get('pinterest_account_id'))
                            ? 'Không tải được board — kiểm tra token hoặc nhấn «Kiểm tra Pinterest».'
                            : 'Chưa có tài khoản Pinterest — vào Cài đặt tùy chỉnh.')
                        ->visible(fn (Get $get): bool => PinterestAccount::optionsForSelect() === []
                            || (filled($get('pinterest_account_id')) && $this->pinterestBoardOptionsForAccount($get('pinterest_account_id')) === [])),
                    Placeholder::make('no_pinterest')->label('Tài khoản Pinterest')->content('Chưa có token — vào Cài đặt tùy chỉnh.')
                        ->visible(fn (): bool => PinterestAccount::optionsForSelect() === []),
                    Placeholder::make('active_queue_warning')->label('Cảnh báo')
                        ->content(fn (): string => 'Đang có hàng đợi ('.app(PinterestQueueService::class)->activeQueueSummary().').')
                        ->visible(fn (): bool => app(PinterestQueueService::class)->hasActiveQueue()),
                    Checkbox::make('confirm_active_queue')->label('Tôi hiểu và vẫn muốn thêm vào hàng đợi')
                        ->accepted()->visible(fn (): bool => app(PinterestQueueService::class)->hasActiveQueue())
                        ->dehydrated(fn (): bool => app(PinterestQueueService::class)->hasActiveQueue()),
                    Radio::make('publish_mode')->label('Thời điểm')->options(['immediate' => 'Ngay', 'scheduled' => 'Đặt lịch'])->default('immediate')->live()->required(),
                    DateTimePicker::make('scheduled_start_at')->label('Bắt đầu lúc')->seconds(false)->native(false)->default(now())
                        ->visible(fn (Get $get): bool => $get('publish_mode') === 'scheduled')
                        ->required(fn (Get $get): bool => $get('publish_mode') === 'scheduled'),
                    ...$this->crossPlatformPublishFormSchema('pinterest'),
                ])
                ->action(fn (array $data) => $this->publishPinterestRecords($data)),
            ActionGroup::make([
                Action::make('importPinterestFile')->label('Import file')->icon('heroicon-o-arrow-up-tray')->action(fn () => $this->importPinterestFromFile()),
                Action::make('savePinterestList')->label('Lưu danh sách')->icon('heroicon-o-bookmark')
                    ->form([TextInput::make('name')->label('Tên')->required()->maxLength(120)->default(fn (): ?string => $this->loadedSavedListName)])
                    ->action(fn (array $data) => $this->savePinterestCurrentList($data)),
                Action::make('deletePinterestSavedList')->label('Xóa danh sách')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->visible(fn (): bool => $this->pinterestLoadedSavedListId !== null || filled($this->pinterestData['saved_list_id'] ?? null))
                    ->action(fn () => $this->deletePinterestSavedList()),
                Action::make('downloadPinterestTemplate')->label('Tải file mẫu')->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (): BinaryFileResponse => Excel::download(new PinterestTemplateExport, 'pinterest-template.xlsx')),
            ])->label('Thêm')->icon('heroicon-m-ellipsis-vertical')->color('gray')->button(),
        ];
    }

    public function testPinterestConnection(): void
    {
        $accounts = PinterestAccount::query()->where('enabled', true)->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (PinterestAccount $a): bool => $a->isConfigured());

        if ($accounts->isEmpty()) {
            Notification::make()->title('Chưa có tài khoản Pinterest')->warning()->send();

            return;
        }

        $api = app(PinterestApiService::class);
        $ok = [];
        $failed = [];
        foreach ($accounts as $account) {
            $result = $api->forAccount($account)->testConnection($account);
            if ($result === null) {
                $failed[] = $account->displayLabel().': '.($api->lastError ?? 'lỗi');
                continue;
            }
            $username = $result['username'] ?? $result['id'] ?? '';
            $boardCount = (int) ($result['board_count'] ?? 0);
            $ok[] = $account->displayLabel().' → @'.$username.' ('.$boardCount.' board)';
        }

        if ($ok !== []) {
            $this->pinterestAccountLabel = count($ok).' tài khoản OK';
        }

        $notification = Notification::make()
            ->title($failed === [] ? 'Kết nối Pinterest thành công' : ($ok !== [] ? 'Một số tài khoản lỗi' : 'Không kết nối được'))
            ->body(trim(collect($ok)->merge($failed)->implode("\n")));
        $failed === [] ? $notification->success()->send() : ($ok !== [] ? $notification->warning()->send() : $notification->danger()->send());
    }

    public function refreshPinterestAccountLabel(): void
    {
        if (! PinterestSettings::isConfigured()) {
            $this->pinterestAccountLabel = null;

            return;
        }

        $this->pinterestAccountLabel = PinterestAccount::query()->where('enabled', true)->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (PinterestAccount $a): bool => $a->isConfigured())
            ->map(fn (PinterestAccount $a): string => $a->displayLabel())
            ->implode(' · ');
    }

    public function publishPinterestRecords(array $data): void
    {
        if (! $this->preparePinterestRecordsFromForm()) {
            return;
        }

        $records = $this->validPinterestRecordsFromForm();
        if ($records === []) {
            Notification::make()->title('Chưa có bài')->body('Nhập dữ liệu hoặc import file.')->warning()->send();

            return;
        }

        $accountId = (int) ($data['pinterest_account_id'] ?? 0);
        $boardIds = collect($data['pinterest_board_ids'] ?? [])
            ->map(fn (mixed $id): string => trim((string) $id))
            ->filter()
            ->values()
            ->all();

        if ($accountId <= 0) {
            Notification::make()->title('Chưa chọn tài khoản Pinterest')->warning()->send();

            return;
        }

        if ($boardIds === []) {
            Notification::make()->title('Chưa chọn board Pinterest')->warning()->send();

            return;
        }

        $boardOptions = $this->pinterestBoardOptionsForAccount($accountId);
        $targets = collect($boardIds)
            ->map(fn (string $boardId): array => [
                'account_id' => $accountId,
                'board_id' => $boardId,
                'board_name' => $boardOptions[$boardId] ?? null,
            ])
            ->all();

        $startAt = ($data['publish_mode'] ?? 'immediate') === 'scheduled' ? Carbon::parse($data['scheduled_start_at']) : now();
        $service = app(PinterestQueueService::class);
        $batchId = $service->enqueue($records, Filament::auth()->user(), $startAt, $targets);

        if ($batchId === null) {
            Notification::make()->title('Không thể xếp hàng')->body($service->lastError ?? '')->danger()->send();

            return;
        }

        $queueCount = count($records) * count($boardIds);
        $crossResults = $this->publishRecordsToCrossPlatforms('pinterest', $records, $startAt, $data);

        if ($crossResults !== []) {
            $this->notifyCrossPlatformPublishResults('Pinterest', $queueCount.' Pin', $crossResults);
        } else {
            Notification::make()->title('Đã xếp hàng '.$queueCount.' Pin Pinterest')->success()->send();
        }

        $this->resetFormAfterPublish();
        $this->clearFormDraft();
        $this->refreshQueue(resetTable: true);
        $this->activeTab = 'queue';
    }

    public function releasePinterestStuckProcessing(): void
    {
        $service = app(PinterestQueueService::class);
        if (! $service->hasStuckProcessing()) {
            Notification::make()->title('Không có bài bị kẹt')->warning()->send();

            return;
        }
        $released = $service->releaseStuckProcessingItems(force: true);
        Notification::make()->title('Đã mở kẹt')->body("{$released} bài về Chờ đăng.")->success()->send();
        $this->refreshQueue(resetTable: true);
    }

    public function canReleasePinterestStuckProcessing(): bool
    {
        return app(PinterestQueueService::class)->hasStuckProcessing();
    }

    public function cancelPinterestPendingQueue(): void
    {
        $service = app(PinterestQueueService::class);
        if (! $service->hasPendingQueue()) {
            Notification::make()->title('Không có bài chờ')->warning()->send();

            return;
        }
        $cancelled = $service->cancelPendingQueue();
        Notification::make()->title('Đã hủy hàng đợi')->body("Đã xóa {$cancelled} bài.")->success()->send();
        $this->refreshQueue(resetTable: true);
    }

    public function canCancelPinterestPendingQueue(): bool
    {
        return app(PinterestQueueService::class)->hasPendingQueue();
    }

    public function importPinterestFromFile(): void
    {
        $count = $this->runImportFromForm(null, 'pinterest');

        if ($count === null) {
            return;
        }

        Notification::make()->title('Đã import '.$count.' bài')->success()->send();
    }

    public function savePinterestCurrentList(array $data): void
    {
        if (! $this->preparePinterestRecordsFromForm()) {
            return;
        }
        $records = $this->validPinterestRecordsFromForm();
        $service = app(PinterestSavedListService::class);
        $list = $service->save((string) ($data['name'] ?? ''), $records, Filament::auth()->user(), $this->loadedSavedListId);
        if ($list === null) {
            Notification::make()->title('Không lưu được')->body($service->lastError ?? '')->danger()->send();

            return;
        }
        $this->pinterestLoadedSavedListId = $list->id;
        $this->pinterestLoadedSavedListName = $list->name;
        if ($this->activePlatform === 'pinterest') {
            $this->loadedSavedListId = $list->id;
            $this->loadedSavedListName = $list->name;
        }
        $this->fillPinterestForm([...$this->pinterestData, 'saved_list_id' => $list->id]);
        Notification::make()->title('Đã lưu «'.$list->name.'»')->success()->send();
    }

    public function loadPinterestSavedListById(int $savedListId, bool $silent = false): void
    {
        $list = PinterestSavedList::query()->find($savedListId);
        if (! $list) {
            if (! $silent) {
                Notification::make()->title('Không tìm thấy')->danger()->send();
            }

            return;
        }
        $records = app(PinterestSavedListService::class)->recordsForForm($savedListId);
        $this->pinterestLoadedSavedListId = $list->id;
        $this->pinterestLoadedSavedListName = $list->name;
        if ($this->activePlatform === 'pinterest') {
            $this->loadedSavedListId = $list->id;
            $this->loadedSavedListName = $list->name;
        }
        $this->fillPinterestForm(['records' => $records, 'import_file' => null, 'saved_list_id' => $list->id]);
        if (! $silent) {
            Notification::make()->title('Đã tải «'.$list->name.'»')->success()->send();
        }
    }

    public function deletePinterestSavedList(): void
    {
        $id = (int) ($this->pinterestData['saved_list_id'] ?? $this->pinterestLoadedSavedListId ?? 0);
        if ($id <= 0) {
            Notification::make()->title('Chưa chọn danh sách')->warning()->send();

            return;
        }
        if (! app(PinterestSavedListService::class)->delete($id)) {
            Notification::make()->title('Không xóa được')->danger()->send();

            return;
        }
        if ($this->pinterestLoadedSavedListId === $id) {
            $this->pinterestLoadedSavedListId = null;
            $this->pinterestLoadedSavedListName = null;
        }
        if ($this->loadedSavedListId === $id) {
            $this->loadedSavedListId = null;
            $this->loadedSavedListName = null;
        }
        $this->fillPinterestForm([...$this->pinterestData, 'saved_list_id' => null]);
        Notification::make()->title('Đã xóa')->success()->send();
    }

    protected function pinterestFormDraftKey(): string
    {
        return FormDraftService::key('pinterest_publish');
    }

    /**
     * @return array<string, string> board_id => board_name
     */
    public function pinterestBoardOptionsForAccount(mixed $accountId): array
    {
        $accountId = (int) $accountId;
        if ($accountId <= 0) {
            return [];
        }

        /** @var PinterestAccount|null $account */
        $account = PinterestAccount::query()->find($accountId);
        if ($account === null || ! $account->isConfigured()) {
            return [];
        }

        return app(PinterestApiService::class)->forAccount($account)->listBoardOptions();
    }
}
