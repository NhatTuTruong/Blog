<?php

namespace App\Filament\Admin\Concerns;

use App\Exports\FacebookTemplateExport;
use App\Filament\Admin\Pages\SystemSettings;
use App\Models\FacebookAccount;
use App\Models\FacebookQueueItem;
use App\Models\FacebookSavedList;
use App\Services\FacebookGraphService;
use App\Services\FacebookQueueService;
use App\Services\FacebookSavedListService;
use App\Support\FacebookSettings;
use App\Support\FormDraftService;
use App\Support\PublicStorage;
use App\Filament\Admin\Support\SocialMediaQueueTable;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
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
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait ManagesFacebookPublish
{
    public ?string $facebookAccountLabel = null;

    protected ?int $facebookLoadedSavedListId = null;

    protected ?string $facebookLoadedSavedListName = null;

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function getFacebookFormSchema(): array
    {
        return [
            Section::make('Nguồn dữ liệu')
                ->description('Chọn danh sách đã lưu hoặc upload Excel. Import khi nhấn «Đăng bài» hoặc «Import file».')
                ->schema([
                    Grid::make(12)->schema([
                        Select::make('saved_list_id')
                            ->label('Danh sách đã lưu')
                            ->options(fn (): array => FacebookSavedList::optionsForSelect())
                            ->placeholder('— Chọn để tải —')
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (?string $state): void {
                                if (filled($state)) {
                                    $this->loadFacebookSavedListById((int) $state, silent: true);
                                }
                            })
                            ->columnSpan(['default' => 12, 'md' => 5]),
                        $this->importExcelFileUploadField('facebook-imports')
                            ->columnSpan(['default' => 12, 'md' => 4]),
                        Placeholder::make('summary')
                            ->label('Tóm tắt')
                            ->content(fn (): string => implode(' · ', array_filter([
                                $this->getRecordCountForPlatform('facebook').' bài sẵn sàng',
                                $this->facebookLoadedSavedListName ? 'Đang mở: '.$this->facebookLoadedSavedListName : null,
                                'Cách '.$this->facebookQueueIntervalMinutes.' phút/bài',
                            ])))
                            ->columnSpan(['default' => 12, 'md' => 3]),
                    ]),
                ]),
            Section::make('Chi tiết bài đăng Facebook')
                ->description(fn (): string => FacebookSettings::isConfigured()
                    ? ''
                    : 'Chưa cấu hình Facebook — vào Cài đặt tùy chỉnh để thêm Page và token.')
                ->schema([
                    Placeholder::make('facebook_status')
                        ->label('Trang Facebook')
                        ->content(fn (): string => $this->facebookAccountLabel
                            ?? (FacebookSettings::isConfigured() ? 'Đã cấu hình' : 'Chưa cấu hình')),
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
                            ...$this->socialMediaRepeaterUploadFields('facebook-uploads', 'facebook-temp-videos'),
                            $this->socialMediaRepeaterMediaTypeField(),
                            TextInput::make('brand_domain')->label('Domain brand')->placeholder('nike.com')->maxLength(255)->columnSpan(['default' => 6, 'md' => 2]),
                            TextInput::make('aff_link')->label('Link Affiliate')->url()->maxLength(2048)->columnSpan(['default' => 6, 'md' => 2]),
                            Textarea::make('content_idea')->label('Ý tưởng nội dung cho AI')->rows(4)->maxLength(2000)->columnSpan(['default' => 6, 'md' => 4]),
                            TagsInput::make('coupon_codes')->label('Coupon')->placeholder('Enter')->columnSpan(['default' => 6, 'md' => 2]),
                        ]),
                ]),
        ];
    }

    public function facebookTable(Table $table): Table
    {
        return $table
            ->query(FacebookQueueItem::query()->with('facebookAccount')->latest('id'))
            ->columns([
                SocialMediaQueueTable::mediaColumn(),
                Tables\Columns\TextColumn::make('facebookAccount.name')->label('Trang FB')
                    ->formatStateUsing(fn ($state, FacebookQueueItem $record): string => $record->facebookAccount?->displayLabel() ?? '—'),
                Tables\Columns\TextColumn::make('brand_domain')->label('Brand')->searchable(),
                SocialMediaQueueTable::queueSourceColumn(),
                SocialMediaQueueTable::statusColumn(),
                Tables\Columns\TextColumn::make('caption')->label('Nội dung')->limit(50)->placeholder('Tạo khi tới lượt')->toggleable(),
                Tables\Columns\TextColumn::make('scheduled_at')->label('Lên lịch')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('facebook_post_id')->label('Post ID')->toggleable(isToggledHiddenByDefault: true)->hidden(),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->actions([
                SocialMediaQueueTable::republishAction(),
                SocialMediaQueueTable::detailAction('facebook'),
            ])
            ->bulkActions(SocialMediaQueueTable::bulkActions())
            ->emptyStateHeading('Chưa có bài trong hàng đợi Facebook');
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    protected function getFacebookHeaderActions(): array
    {
        return [
            Action::make('testFacebook')->label('Kiểm tra Facebook')->icon('heroicon-o-signal')->color('gray')
                ->action(fn () => $this->testFacebookConnection()),
            Action::make('openFacebookSettings')->label('Cài đặt Facebook')->icon('heroicon-o-cog-6-tooth')->color('gray')
                ->url(SystemSettings::getUrl()),
            Action::make('publishFacebook')->label('Đăng bài')->icon('heroicon-o-sparkles')->color('success')
                ->modalHeading('Đăng danh sách lên Facebook')
                ->modalDescription(function (): string {
                    $service = app(FacebookQueueService::class);
                    $postCount = $this->getRecordCount();
                    $accountCount = count(FacebookAccount::enabledConfiguredIds());
                    $total = $postCount * max(1, $accountCount);
                    $base = $postCount > 0
                        ? "Sẽ xếp hàng {$total} lượt đăng ({$postCount} bài × trang đã chọn). Mỗi lượt cách {$this->queueIntervalMinutes} phút."
                        : 'Chưa có bài hợp lệ.';
                    if ($service->hasActiveQueue()) {
                        $base .= ' Lưu ý: đang có hàng đợi ('.$service->activeQueueSummary().').';
                    }
                    if (! FacebookSettings::isConfigured()) {
                        $base .= ' Facebook chưa cấu hình — thêm Page trong Cài đặt tùy chỉnh.';
                    }

                    return $base;
                })
                ->modalSubmitActionLabel('Bắt đầu')
                ->form([
                    CheckboxList::make('facebook_account_ids')->label('Trang Facebook')
                        ->options(fn (): array => FacebookAccount::optionsForSelect())
                        ->default(fn (): array => FacebookAccount::enabledConfiguredIds())
                        ->columns(1)->required()
                        ->visible(fn (): bool => FacebookAccount::optionsForSelect() !== [])
                        ->helperText('Mặc định chọn tất cả.'),
                    Placeholder::make('no_fb')->label('Trang Facebook')->content('Chưa có trang — vào Cài đặt tùy chỉnh.')
                        ->visible(fn (): bool => FacebookAccount::optionsForSelect() === []),
                    Placeholder::make('active_queue_warning')->label('Cảnh báo')
                        ->content(fn (): string => 'Đang có hàng đợi ('.app(FacebookQueueService::class)->activeQueueSummary().').')
                        ->visible(fn (): bool => app(FacebookQueueService::class)->hasActiveQueue()),
                    Checkbox::make('confirm_active_queue')->label('Tôi hiểu và vẫn muốn thêm vào hàng đợi')
                        ->accepted()->visible(fn (): bool => app(FacebookQueueService::class)->hasActiveQueue())
                        ->dehydrated(fn (): bool => app(FacebookQueueService::class)->hasActiveQueue()),
                    Radio::make('publish_mode')->label('Thời điểm')->options(['immediate' => 'Ngay', 'scheduled' => 'Đặt lịch'])->default('immediate')->live()->required(),
                    DateTimePicker::make('scheduled_start_at')->label('Bắt đầu lúc')->seconds(false)->native(false)->default(now())
                        ->visible(fn (Get $get): bool => $get('publish_mode') === 'scheduled')
                        ->required(fn (Get $get): bool => $get('publish_mode') === 'scheduled'),
                    ...$this->crossPlatformPublishFormSchema('facebook'),
                ])
                ->action(fn (array $data) => $this->publishFacebookRecords($data)),
            ActionGroup::make([
                Action::make('importFacebookFile')->label('Import file')->icon('heroicon-o-arrow-up-tray')->action(fn () => $this->importFacebookFromFile()),
                Action::make('saveFacebookList')->label('Lưu danh sách')->icon('heroicon-o-bookmark')
                    ->form([TextInput::make('name')->label('Tên')->required()->maxLength(120)->default(fn (): ?string => $this->loadedSavedListName)])
                    ->action(fn (array $data) => $this->saveFacebookCurrentList($data)),
                Action::make('deleteFacebookSavedList')->label('Xóa danh sách')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->visible(fn (): bool => $this->facebookLoadedSavedListId !== null || filled($this->facebookData['saved_list_id'] ?? null))
                    ->action(fn () => $this->deleteFacebookSavedList()),
                Action::make('downloadFacebookTemplate')->label('Tải file mẫu')->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (): BinaryFileResponse => Excel::download(new FacebookTemplateExport, 'facebook-template.xlsx')),
            ])->label('Thêm')->icon('heroicon-m-ellipsis-vertical')->color('gray')->button(),
        ];
    }

    public function testFacebookConnection(): void
    {
        $accounts = FacebookAccount::query()->where('enabled', true)->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (FacebookAccount $a): bool => $a->isConfigured());

        if ($accounts->isEmpty()) {
            Notification::make()->title('Chưa có trang Facebook')->warning()->send();

            return;
        }

        $graph = app(FacebookGraphService::class);
        $ok = [];
        $failed = [];
        foreach ($accounts as $account) {
            $result = $graph->forAccount($account)->testConnection($account);
            if ($result === null) {
                $failed[] = $account->displayLabel().': '.($graph->lastError ?? 'lỗi');
                continue;
            }
            $label = $account->displayLabel().' → '.($result['name'] ?? $result['id']);
            if (! empty($result['token_upgraded'])) {
                $label .= ' (đã đổi sang Page token)';
            }
            $ok[] = $label;
        }

        if ($ok !== []) {
            $this->facebookAccountLabel = count($ok).' trang OK';
        }

        $notification = Notification::make()
            ->title($failed === [] ? 'Kết nối Facebook thành công' : ($ok !== [] ? 'Một số trang lỗi' : 'Không kết nối được'))
            ->body(trim(collect($ok)->merge($failed)->implode("\n")));
        $failed === [] ? $notification->success()->send() : ($ok !== [] ? $notification->warning()->send() : $notification->danger()->send());
    }

    public function refreshFacebookAccountLabel(): void
    {
        if (! FacebookSettings::isConfigured()) {
            $this->facebookAccountLabel = null;

            return;
        }

        $this->facebookAccountLabel = FacebookAccount::query()->where('enabled', true)->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (FacebookAccount $a): bool => $a->isConfigured())
            ->map(fn (FacebookAccount $a): string => $a->displayLabel())
            ->implode(' · ');
    }

    public function publishFacebookRecords(array $data): void
    {
        if (! $this->prepareFacebookRecordsFromForm()) {
            return;
        }

        $records = $this->validFacebookRecordsFromForm();
        if ($records === []) {
            Notification::make()
                ->title('Chưa có bài')
                ->body('Nhập dữ liệu hoặc import file.')
                ->warning()
                ->send();

            return;
        }

        $accountIds = collect($data['facebook_account_ids'] ?? [])->map(fn ($id): int => (int) $id)->filter(fn (int $id): bool => $id > 0)->values()->all();
        if ($accountIds === []) {
            Notification::make()->title('Chưa chọn trang Facebook')->warning()->send();

            return;
        }

        $startAt = ($data['publish_mode'] ?? 'immediate') === 'scheduled' ? Carbon::parse($data['scheduled_start_at']) : now();
        $service = app(FacebookQueueService::class);
        $batchId = $service->enqueue($records, Filament::auth()->user(), $startAt, $accountIds);

        if ($batchId === null) {
            Notification::make()->title('Không thể xếp hàng')->body($service->lastError ?? '')->danger()->send();

            return;
        }

        $queueCount = count($records) * count($accountIds);
        $crossResults = $this->publishRecordsToCrossPlatforms('facebook', $records, $startAt, $data);

        if ($crossResults !== []) {
            $this->notifyCrossPlatformPublishResults('Facebook', $queueCount.' lượt', $crossResults);
        } else {
            Notification::make()->title('Đã xếp hàng '.$queueCount.' lượt Facebook')->success()->send();
        }

        $this->resetFormAfterPublish();
        $this->clearFormDraft();
        $this->refreshQueue(resetTable: true);
        $this->activeTab = 'queue';
    }

    public function releaseFacebookStuckProcessing(): void
    {
        $service = app(FacebookQueueService::class);
        if (! $service->hasStuckProcessing()) {
            Notification::make()->title('Không có bài bị kẹt')->warning()->send();

            return;
        }
        $released = $service->releaseStuckProcessingItems(force: true);
        Notification::make()->title('Đã mở kẹt')->body("{$released} bài về Chờ đăng.")->success()->send();
        $this->refreshQueue(resetTable: true);
    }

    public function canReleaseFacebookStuckProcessing(): bool
    {
        return app(FacebookQueueService::class)->hasStuckProcessing();
    }

    public function cancelFacebookPendingQueue(): void
    {
        $service = app(FacebookQueueService::class);
        if (! $service->hasPendingQueue()) {
            Notification::make()->title('Không có bài chờ')->warning()->send();

            return;
        }
        $cancelled = $service->cancelPendingQueue();
        Notification::make()->title('Đã hủy hàng đợi')->body("Đã xóa {$cancelled} bài.")->success()->send();
        $this->refreshQueue(resetTable: true);
    }

    public function canCancelFacebookPendingQueue(): bool
    {
        return app(FacebookQueueService::class)->hasPendingQueue();
    }

    public function importFacebookFromFile(): void
    {
        $count = $this->runImportFromForm(null, 'facebook');

        if ($count === null) {
            return;
        }

        Notification::make()->title('Đã import '.$count.' bài')->success()->send();
    }

    public function saveFacebookCurrentList(array $data): void
    {
        if (! $this->prepareFacebookRecordsFromForm()) {
            return;
        }
        $records = $this->validFacebookRecordsFromForm();
        $service = app(FacebookSavedListService::class);
        $list = $service->save((string) ($data['name'] ?? ''), $records, Filament::auth()->user(), $this->loadedSavedListId);
        if ($list === null) {
            Notification::make()->title('Không lưu được')->body($service->lastError ?? '')->danger()->send();

            return;
        }
        $this->facebookLoadedSavedListId = $list->id;
        $this->facebookLoadedSavedListName = $list->name;
        if ($this->activePlatform === 'facebook') {
            $this->loadedSavedListId = $list->id;
            $this->loadedSavedListName = $list->name;
        }
        $this->fillFacebookForm([...$this->facebookData, 'saved_list_id' => $list->id]);
        Notification::make()->title('Đã lưu «'.$list->name.'»')->success()->send();
    }

    public function loadFacebookSavedListById(int $savedListId, bool $silent = false): void
    {
        $list = FacebookSavedList::query()->find($savedListId);
        if (! $list) {
            if (! $silent) {
                Notification::make()->title('Không tìm thấy')->danger()->send();
            }

            return;
        }
        $records = app(FacebookSavedListService::class)->recordsForForm($savedListId);
        $this->facebookLoadedSavedListId = $list->id;
        $this->facebookLoadedSavedListName = $list->name;
        if ($this->activePlatform === 'facebook') {
            $this->loadedSavedListId = $list->id;
            $this->loadedSavedListName = $list->name;
        }
        $this->fillFacebookForm(['records' => $records, 'import_file' => null, 'saved_list_id' => $list->id]);
        if (! $silent) {
            Notification::make()->title('Đã tải «'.$list->name.'»')->success()->send();
        }
    }

    public function deleteFacebookSavedList(): void
    {
        $id = (int) ($this->facebookData['saved_list_id'] ?? $this->facebookLoadedSavedListId ?? 0);
        if ($id <= 0) {
            Notification::make()->title('Chưa chọn danh sách')->warning()->send();

            return;
        }
        if (! app(FacebookSavedListService::class)->delete($id)) {
            Notification::make()->title('Không xóa được')->danger()->send();

            return;
        }
        if ($this->facebookLoadedSavedListId === $id) {
            $this->facebookLoadedSavedListId = null;
            $this->facebookLoadedSavedListName = null;
        }
        if ($this->loadedSavedListId === $id) {
            $this->loadedSavedListId = null;
            $this->loadedSavedListName = null;
        }
        $this->fillFacebookForm([...$this->facebookData, 'saved_list_id' => null]);
        Notification::make()->title('Đã xóa')->success()->send();
    }

    protected function facebookFormDraftKey(): string
    {
        return FormDraftService::key('facebook_publish');
    }
}
