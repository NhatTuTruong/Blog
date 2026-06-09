<?php

namespace App\Filament\Admin\Pages;

use App\Exports\AutoBlogTemplateExport;
use App\Filament\Admin\Resources\BlogResource;
use App\Models\AutoBlogQueueItem;
use App\Models\AutoBlogSavedList;
use App\Models\BlogCategory;
use App\Services\AutoBlogImportService;
use App\Services\AutoBlogQueueService;
use App\Services\AutoBlogSavedListService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
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
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AutoBlogPublish extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static string $view = 'filament.admin.pages.auto-blog-publish';

    protected static ?string $navigationLabel = 'Đăng bài viết tự động';

    protected static ?string $title = 'Đăng bài viết tự động';

    protected static ?string $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 3;

    public ?array $data = [];

    public string $activeTab = 'compose';

    /** @var array{pending: int, processing: int, completed: int, failed: int} */
    public array $queueStats = [
        'pending' => 0,
        'processing' => 0,
        'completed' => 0,
        'failed' => 0,
    ];

    public int $queueIntervalMinutes = 10;

    public ?int $loadedSavedListId = null;

    public ?string $loadedSavedListName = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return (bool) ($user && method_exists($user, 'isAdmin') && $user->isAdmin());
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
            ->filter(fn (array $record): bool => filled($record['brand_domain'] ?? null))
            ->count();
    }

    public function mount(): void
    {
        $this->queueIntervalMinutes = app(AutoBlogQueueService::class)->intervalMinutes();

        $this->form->fill([
            'records' => [$this->emptyRecordRow()],
            'import_file' => null,
            'saved_list_id' => null,
        ]);

        $this->refreshQueue();
    }

    public function form(Form $form): Form
    {
        $categoryOptions = BlogCategory::optionsForSelect();

        return $form
            ->schema([
                Section::make('Nguồn dữ liệu')
                    ->description('Chọn danh sách đã lưu (tự tải) hoặc upload Excel. File sẽ được import khi bạn nhấn «Đăng bài» hoặc «Import file».')
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                Select::make('saved_list_id')
                                    ->label('Danh sách đã lưu')
                                    ->options(fn (): array => AutoBlogSavedList::optionsForSelect())
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
                                    ->directory('auto-blog-imports')
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
                Section::make('Chi tiết bài viết')
                    ->schema([
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
                                : 'Dòng mới')
                            ->schema([
                                TextInput::make('brand_domain')
                                    ->label('Domain')
                                    ->placeholder('nike.com')
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 6, 'md' => 2]),
                                Select::make('blog_category_id')
                                    ->label('Danh mục')
                                    ->options($categoryOptions)
                                    ->searchable()
                                    ->placeholder('General')
                                    ->columnSpan(['default' => 6, 'md' => 2]),
                                TextInput::make('aff_link')
                                    ->label('Link Affiliate')
                                    ->url()
                                    ->maxLength(2048)
                                    ->placeholder('https://…')
                                    ->columnSpan(['default' => 6, 'md' => 2]),
                                Textarea::make('content_idea')
                                    ->label('Ý tưởng cho AI')
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
                AutoBlogQueueItem::query()
                    ->with(['blog', 'blogCategory'])
                    ->latest('id')
            )
            ->columns([
                Tables\Columns\TextColumn::make('brand_domain')
                    ->label('Domain')
                    ->searchable()
                    ->description(fn (AutoBlogQueueItem $record): string => $record->category_name
                        ?? $record->blogCategory?->name
                        ?? 'General'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state, AutoBlogQueueItem $record): string => $record->statusLabel())
                    ->color(fn (string $state): string => match ($state) {
                        AutoBlogQueueItem::STATUS_PENDING => 'warning',
                        AutoBlogQueueItem::STATUS_PROCESSING => 'info',
                        AutoBlogQueueItem::STATUS_COMPLETED => 'success',
                        AutoBlogQueueItem::STATUS_FAILED => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (AutoBlogQueueItem $record): ?string => $record->status === AutoBlogQueueItem::STATUS_FAILED
                        ? $record->error_message
                        : null),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Lên lịch')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Xử lý')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('blog.title')
                    ->label('Bài viết')
                    ->limit(40)
                    ->placeholder('—')
                    ->url(fn (AutoBlogQueueItem $record): ?string => $record->blog_id
                        ? BlogResource::getUrl('edit', ['record' => $record->blog_id])
                        : null),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->poll('30s')
            ->emptyStateHeading('Chưa có bài trong hàng đợi')
            ->emptyStateDescription('Đăng danh sách bài viết để bắt đầu xếp hàng tự động.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label('Đăng bài')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->modalHeading('Đăng danh sách bài viết')
                ->modalDescription(function (): string {
                    $service = app(AutoBlogQueueService::class);
                    $count = $this->getRecordCount();
                    $base = $count > 0
                        ? "Sẽ xếp hàng {$count} bài. Mỗi bài cách {$this->queueIntervalMinutes} phút."
                        : 'Chưa có bài hợp lệ — nhập Domain hoặc tải danh sách trước.';

                    if ($service->hasActiveQueue()) {
                        $base .= ' Lưu ý: đang có hàng đợi ('.$service->activeQueueSummary().') — bài mới sẽ chen vào cùng hàng đợi.';
                    }

                    return $base;
                })
                ->modalSubmitActionLabel('Bắt đầu')
                ->form([
                    Placeholder::make('active_queue_warning')
                        ->label('Cảnh báo')
                        ->content(function (): string {
                            $service = app(AutoBlogQueueService::class);

                            return 'Đang có hàng đợi đang chạy ('.$service->activeQueueSummary().'). '
                                .'Nếu đăng thêm, các bài mới sẽ xếp chung hàng đợi và có thể lệch khoảng cách phút/bài. '
                                .'Nên đợi hàng đợi hiện tại xong hoặc «Hủy hàng đợi» trước khi đăng danh sách mới.';
                        })
                        ->visible(fn (): bool => app(AutoBlogQueueService::class)->hasActiveQueue()),
                    Checkbox::make('confirm_active_queue')
                        ->label('Tôi hiểu và vẫn muốn thêm vào hàng đợi đang chạy')
                        ->accepted()
                        ->visible(fn (): bool => app(AutoBlogQueueService::class)->hasActiveQueue())
                        ->dehydrated(fn (): bool => app(AutoBlogQueueService::class)->hasActiveQueue()),
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
                        new AutoBlogTemplateExport,
                        'auto-blog-template.xlsx',
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

    public function publishRecords(array $data): void
    {
        if (! $this->prepareRecordsFromForm()) {
            return;
        }

        $records = $this->validRecordsFromForm();

        if ($records === []) {
            Notification::make()
                ->title('Chưa có bài để đăng')
                ->body('Nhập Domain, import file hoặc chọn danh sách đã lưu.')
                ->warning()
                ->send();

            return;
        }

        $startAt = ($data['publish_mode'] ?? 'immediate') === 'scheduled'
            ? Carbon::parse($data['scheduled_start_at'])
            : now();

        $service = app(AutoBlogQueueService::class);
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
            ->title('Đã xếp hàng '.$count.' bài')
            ->body($minutes > 0
                ? "Bắt đầu {$startLabel} · cách {$this->queueIntervalMinutes} phút/bài."
                : "Bắt đầu từ {$startLabel}.")
            ->success()
            ->send();

        $this->resetFormAfterPublish();
        $this->refreshQueue();
        $this->activeTab = 'queue';
    }

    public function saveCurrentList(array $data): void
    {
        if (! $this->prepareRecordsFromForm()) {
            return;
        }

        $records = $this->validRecordsFromForm();
        $service = app(AutoBlogSavedListService::class);
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
            ->body($list->record_count.' bài viết.')
            ->success()
            ->send();
    }

    public function loadSavedListById(int $savedListId, bool $silent = false): void
    {
        if ($savedListId <= 0) {
            return;
        }

        $list = AutoBlogSavedList::query()->find($savedListId);

        if (! $list) {
            if (! $silent) {
                Notification::make()->title('Không tìm thấy danh sách')->danger()->send();
            }

            return;
        }

        $records = app(AutoBlogSavedListService::class)->recordsForForm($savedListId);

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
                ->body($list->record_count.' bài viết.')
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

        $service = app(AutoBlogSavedListService::class);

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

    public function refreshQueue(): void
    {
        $service = app(AutoBlogQueueService::class);
        $service->recoverStaleProcessingItems();
        $this->queueStats = $service->queueStats();
        $this->queueIntervalMinutes = $service->intervalMinutes();

        if ($this->activeTab === 'queue') {
            $this->resetTable();
        }
    }

    public function cancelPendingQueue(): void
    {
        $service = app(AutoBlogQueueService::class);

        if (! $service->hasPendingQueue()) {
            Notification::make()
                ->title('Không có bài đang chờ')
                ->warning()
                ->send();

            return;
        }

        $deleted = $service->cancelPendingQueue();

        Notification::make()
            ->title('Đã hủy hàng đợi')
            ->body("Đã xóa {$deleted} bài đang chờ.")
            ->success()
            ->send();

        $this->refreshQueue();
    }

    public function canCancelPendingQueue(): bool
    {
        return app(AutoBlogQueueService::class)->hasPendingQueue();
    }

    protected function prepareRecordsFromForm(): bool
    {
        $state = $this->form->getState();

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
     * @return array<string, mixed>
     */
    protected function emptyRecordRow(): array
    {
        return [
            'brand_domain' => '',
            'blog_category_id' => null,
            'content_idea' => null,
            'aff_link' => null,
            'coupon_codes' => [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function validRecordsFromForm(): array
    {
        return collect($this->form->getState()['records'] ?? [])
            ->filter(fn (array $record): bool => filled($record['brand_domain'] ?? null))
            ->values()
            ->all();
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

        $import = app(AutoBlogImportService::class);
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
            ->filter(fn (array $record): bool => filled($record['brand_domain'] ?? null))
            ->values()
            ->all();

        $this->form->fill([
            'records' => array_values(array_merge($existing, $items)),
            'import_file' => null,
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

}
