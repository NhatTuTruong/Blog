<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\HasFormDraft;
use App\Models\EmailSendLog;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\FormDraftService;
use App\Services\EmailRecurringService;
use App\Services\TemplatedEmailService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;

class SendTemplatedEmail extends Page implements HasForms
{
    use HasFormDraft;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'Gửi email';

    protected static ?string $title = 'Gửi email theo mẫu';

    protected static ?string $navigationGroup = 'Email';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.admin.pages.send-templated-email';

    protected static ?string $slug = 'send-email';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string | Htmlable
    {
        return 'Gửi email theo mẫu';
    }

    public function mount(): void
    {
        $resendId = request()->integer('resend') ?: null;

        if ($resendId) {
            $log = EmailSendLog::query()->find($resendId);

            if ($log) {
                $this->fillFromSendLog($log);

                return;
            }
        }

        $templateId = request()->integer('template') ?: null;

        $this->form->fill([
            'email_template_id' => $templateId,
            'recipients' => [],
            'variables' => $this->prefillVariablesForTemplate($templateId),
            'custom_subject' => '',
            'custom_body' => '',
            'attachments' => [],
        ]);

        $this->restoreFormDraft();
    }

    public static function urlWithResend(int $logId): string
    {
        return static::getUrl().'?resend='.$logId;
    }

    protected function fillFromSendLog(EmailSendLog $log): void
    {
        $userId = Filament::auth()->id();

        if ($userId) {
            FormDraftService::delete($userId, FormDraftService::key('send_email'));
        }

        $recipients = EmailSendLog::normalizeArray($log->recipients);

        if ($log->isManualSend()) {
            $this->form->fill([
                'email_template_id' => null,
                'recipients' => $recipients,
                'variables' => [],
                'custom_subject' => $log->subject,
                'custom_body' => EmailTemplate::prepareBodyForEditor((string) $log->body),
                'attachments' => [],
            ]);
        } else {
            $template = EmailTemplate::query()->find($log->email_template_id);
            $variables = is_array($log->variable_values) ? $log->variable_values : [];

            if ($template) {
                $variables = array_merge($template->defaultVariableValues(), $variables);
            }

            $this->form->fill([
                'email_template_id' => $log->email_template_id,
                'recipients' => $recipients,
                'variables' => $variables,
                'custom_subject' => '',
                'custom_body' => '',
                'attachments' => [],
            ]);
        }

        $body = 'Đã điền sẵn người nhận và nội dung từ lịch sử gửi.';

        if (EmailSendLog::normalizeArray($log->attachments) !== []) {
            $body .= ' Vui lòng tải lại các file đính kèm trước khi gửi.';
        }

        Notification::make()
            ->title('Chuẩn bị gửi lại')
            ->body($body)
            ->info()
            ->send();
    }

    protected function formDraftKey(): string
    {
        return FormDraftService::key('send_email');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDraftBeforeRestore(array $data): array
    {
        $recipients = $data['recipients'] ?? null;

        if (is_string($recipients) && filled($recipients)) {
            $data['recipients'] = app(TemplatedEmailService::class)->parseRecipients($recipients);
        }

        if (! is_array($data['recipients'] ?? null)) {
            $data['recipients'] = [];
        }

        return $data;
    }

    protected function formDraftHasContent(array $data): bool
    {
        $recipients = $data['recipients'] ?? null;
        $hasRecipients = is_array($recipients)
            ? $recipients !== []
            : filled($recipients);

        return $hasRecipients
            || filled($data['custom_subject'] ?? null)
            || filled($data['custom_body'] ?? null)
            || (is_array($data['attachments'] ?? null) && $data['attachments'] !== [])
            || (is_array($data['variables'] ?? null) && collect($data['variables'])->filter(fn (mixed $value): bool => filled($value))->isNotEmpty());
    }

    protected function resetPageFormAfterDraftDiscard(): void
    {
        $this->form->fill([
            'email_template_id' => null,
            'recipients' => [],
            'variables' => [],
            'custom_subject' => '',
            'custom_body' => '',
            'attachments' => [],
        ]);
        $this->formDraftRestored = false;
    }

    public static function urlWithTemplate(?int $templateId): string
    {
        $url = static::getUrl();

        if ($templateId) {
            $url .= '?template='.$templateId;
        }

        return $url;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Chọn mẫu & người nhận')
                    ->description('Mail gửi qua SMTP theo cấu hình tại Cài đặt → Gemini & MXH & Email. Có thể chọn mẫu hoặc tự nhập tiêu đề/nội dung.')
                    ->schema([
                        Forms\Components\Select::make('email_template_id')
                            ->label('Mẫu email')
                            ->options(EmailTemplate::optionsForSelect())
                            ->searchable()
                            ->placeholder('Không dùng mẫu — tự nhập nội dung')
                            ->live()
                            ->afterStateUpdated(function (?string $state, Forms\Set $set): void {
                                $set('variables', $this->prefillVariablesForTemplate($state));

                                if (filled($state)) {
                                    $set('custom_subject', '');
                                    $set('custom_body', '');
                                }
                            })
                            ->helperText('Để trống nếu muốn tự nhập tiêu đề và nội dung bên dưới.'),
                        Forms\Components\TagsInput::make('recipients')
                            ->label('Danh sách người nhận')
                            ->placeholder('Enter')
                            ->required()
                            ->live()
                            ->nestedRecursiveRules([
                                'email',
                                'distinct',
                            ])
                            ->helperText('Nhập email rồi nhấn Enter để thêm. Mỗi tag là một người nhận.')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
                Forms\Components\Section::make('Nội dung tự nhập')
                    ->description('Hiển thị khi không chọn mẫu email.')
                    ->schema([
                        Forms\Components\TextInput::make('custom_subject')
                            ->label('Tiêu đề email')
                            ->maxLength(255)
                            ->required(fn (Get $get): bool => blank($get('email_template_id')))
                            ->live(onBlur: true)
                            ->columnSpanFull(),
                        Forms\Components\RichEditor::make('custom_body')
                            ->label('Nội dung')
                            ->required(fn (Get $get): bool => blank($get('email_template_id')))
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strikeThrough',
                                'link',
                                'orderedList',
                                'bulletList',
                                'h2',
                                'h3',
                                'blockquote',
                                'redo',
                                'undo',
                            ])
                            ->live(onBlur: true)
                            ->extraInputAttributes(['style' => 'min-height: 240px;'])
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Get $get): bool => blank($get('email_template_id'))),
                Forms\Components\Section::make('Giá trị biến thể')
                    ->description(fn (Get $get): string => $this->variableSectionDescription($get('email_template_id')))
                    ->schema([
                        Forms\Components\KeyValue::make('variables')
                            ->label('')
                            ->keyLabel('Biến')
                            ->valueLabel('Giá trị')
                            ->addable(false)
                            ->deletable(false)
                            ->editableKeys(false)
                            ->reorderable(false)
                            ->live()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Get $get): bool => filled($get('email_template_id'))),
                Forms\Components\Section::make('Tệp đính kèm')
                    ->description(fn (Get $get): string => filled($get('email_template_id'))
                        ? 'File trong mẫu sẽ tự động đính kèm. Bạn có thể thêm file riêng cho lần gửi này bên dưới.'
                        : 'Tùy chọn. Các file sẽ được đính kèm vào mọi email trong lần gửi này.')
                    ->schema([
                        Forms\Components\Placeholder::make('template_attachments')
                            ->label('File đính kèm trong mẫu')
                            ->content(function (Get $get): string {
                                $template = $this->resolveTemplate($get('email_template_id'));

                                if (! $template) {
                                    return '';
                                }

                                $names = app(TemplatedEmailService::class)->attachmentNamesForLog(
                                    $template->attachmentStoragePaths(),
                                );

                                return $names !== []
                                    ? implode(', ', $names)
                                    : 'Mẫu này chưa có file đính kèm.';
                            })
                            ->visible(fn (Get $get): bool => filled($get('email_template_id'))),
                        Forms\Components\FileUpload::make('attachments')
                            ->label(fn (Get $get): string => filled($get('email_template_id'))
                                ? 'File đính kèm thêm (lần gửi này)'
                                : '')
                            ->multiple()
                            ->disk('local')
                            ->directory('email-attachments')
                            ->visibility('private')
                            ->maxSize(15360)
                            ->maxFiles(5)
                            ->live()
                            ->helperText('Tối đa 5 file, mỗi file 15MB (PDF, Word, Excel, ảnh, ZIP, …).')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function variableSectionDescription(mixed $templateId): string
    {
        $base = 'Lấy từ «Nội dung biến thể» trong mẫu email. Bạn có thể sửa từng giá trị trước khi nhấn Gửi Mail.';

        $template = $this->resolveTemplate($templateId);

        if (! $template) {
            return $base;
        }

        $definitions = $template->variableDefinitions();

        if ($definitions === []) {
            return 'Mẫu này không có biến thể — nội dung gửi giữ nguyên như trong mẫu.';
        }

        $hints = collect($definitions)
            ->map(fn (array $definition): string => '['.$definition['key'].']')
            ->implode(', ');

        return $base.' Thay thế trong mẫu: '.$hints.'.';
    }

    /**
     * @return array<string, string>
     */
    protected function prefillVariablesForTemplate(mixed $templateId): array
    {
        if (blank($templateId)) {
            return [];
        }

        $template = EmailTemplate::query()->find($templateId);

        return $template?->defaultVariableValues() ?? [];
    }

    /**
     * @return array<string, string>
     */
    protected function resolvedVariables(): array
    {
        $template = $this->resolveTemplate($this->data['email_template_id'] ?? null);

        if (! $template) {
            return [];
        }

        $formValues = $this->data['variables'] ?? [];

        if (! is_array($formValues)) {
            $formValues = [];
        }

        $merged = array_merge($template->defaultVariableValues(), $formValues);

        foreach ($merged as $key => $value) {
            $merged[$key] = trim((string) $value);
        }

        return $merged;
    }

    public function getPreviewSubjectProperty(): string
    {
        $template = $this->resolveTemplate($this->data['email_template_id'] ?? null);

        if ($template) {
            return $template->renderSubject($this->resolvedVariables());
        }

        return trim((string) ($this->data['custom_subject'] ?? ''));
    }

    public function getPreviewBodyHtmlProperty(): string
    {
        $template = $this->resolveTemplate($this->data['email_template_id'] ?? null);

        if ($template) {
            return $template->renderBody($this->resolvedVariables());
        }

        $body = trim((string) ($this->data['custom_body'] ?? ''));

        if ($body === '') {
            return '';
        }

        return EmailTemplate::formatBodyForEmail($body);
    }

    public function getShowPreviewProperty(): bool
    {
        if (filled($this->data['email_template_id'] ?? null)) {
            return true;
        }

        return filled($this->data['custom_subject'] ?? null)
            || filled($this->data['custom_body'] ?? null);
    }

    /**
     * @return array<int, string>
     */
    public function getPreviewAttachmentNamesProperty(): array
    {
        $service = app(TemplatedEmailService::class);
        $templatePaths = $this->resolveTemplate($this->data['email_template_id'] ?? null)?->attachmentStoragePaths() ?? [];
        $sendPaths = is_array($this->data['attachments'] ?? null) ? $this->data['attachments'] : [];

        return $service->attachmentNamesForLog(
            $service->mergeAttachmentStoragePaths($templatePaths, $sendPaths),
        );
    }

    protected function resolveTemplate(mixed $templateId): ?EmailTemplate
    {
        if (blank($templateId)) {
            return null;
        }

        return EmailTemplate::query()->find($templateId);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getFormDraftDiscardAction(),
            Action::make('send')
                ->label('Gửi Mail')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->modalHeading('Gửi email')
                ->modalSubmitActionLabel('Gửi')
                ->form([
                    Forms\Components\Radio::make('send_mode')
                        ->label('Chế độ gửi')
                        ->options([
                            'once' => 'Gửi 1 lần',
                            'recurring' => 'Gửi lại nhiều lần',
                        ])
                        ->default('once')
                        ->live()
                        ->required(),
                    Forms\Components\TextInput::make('repeat_interval_hours')
                        ->label('Khoảng cách gửi lại (giờ)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(8760)
                        ->default(24)
                        ->required(fn (Get $get): bool => $get('send_mode') === 'recurring')
                        ->visible(fn (Get $get): bool => $get('send_mode') === 'recurring'),
                ])
                ->action(fn (array $data) => $this->sendEmails($data)),
        ];
    }

    /**
     * @param  array<string, mixed>  $sendOptions
     */
    public function sendEmails(array $sendOptions = []): void
    {
        $data = $this->form->getState();
        $template = EmailTemplate::query()->find($data['email_template_id'] ?? null);

        if (! $template) {
            $customSubject = trim((string) ($data['custom_subject'] ?? ''));
            $customBody = trim((string) ($data['custom_body'] ?? ''));

            if ($customSubject === '' || $customBody === '') {
                Notification::make()
                    ->title('Thiếu tiêu đề hoặc nội dung')
                    ->body('Khi không chọn mẫu, vui lòng nhập đầy đủ tiêu đề và nội dung email.')
                    ->danger()
                    ->send();

                return;
            }
        }

        $user = Filament::auth()->user();
        $sender = $user instanceof User ? $user : null;

        $formVariables = is_array($data['variables'] ?? null) ? $data['variables'] : [];
        $variables = $template
            ? array_merge($template->defaultVariableValues(), $formVariables)
            : [];

        $attachmentPaths = is_array($data['attachments'] ?? null) ? $data['attachments'] : [];

        $service = app(TemplatedEmailService::class);
        $result = $service->send(
            $template,
            is_array($data['recipients'] ?? null) ? $data['recipients'] : (string) ($data['recipients'] ?? ''),
            $variables,
            $sender,
            $data['custom_subject'] ?? null,
            $data['custom_body'] ?? null,
            $attachmentPaths,
        );

        if ($result['sent'] === 0) {
            Notification::make()
                ->title('Gửi email thất bại')
                ->body($service->lastError ?? 'Không gửi được email nào.')
                ->danger()
                ->send();

            return;
        }

        $sendMode = (string) ($sendOptions['send_mode'] ?? 'once');
        $isRecurring = $sendMode === 'recurring';

        if ($isRecurring) {
            $intervalHours = max(1, min(8760, (int) ($sendOptions['repeat_interval_hours'] ?? 24)));
            $recurringService = app(EmailRecurringService::class);
            $schedule = $recurringService->createSchedule(
                template: $template,
                recipients: $result['recipients'],
                variableValues: $variables,
                subject: $result['subject'],
                body: $result['body'],
                extraAttachmentPaths: $attachmentPaths,
                intervalHours: $intervalHours,
                sender: $sender,
            );
            $recurringService->linkLogToSchedule($result['log'], $schedule);
        }

        if ($attachmentPaths !== []) {
            Storage::disk('local')->delete($attachmentPaths);
            $this->data['attachments'] = [];
        }

        $body = "Đã gửi thành công {$result['sent']} email.";
        if ($result['failed'] > 0) {
            $body .= " Thất bại: {$result['failed']}.";
        }
        if ($isRecurring) {
            $intervalHours = max(1, min(8760, (int) ($sendOptions['repeat_interval_hours'] ?? 24)));
            $body .= " Đã bật gửi lại mỗi {$intervalHours} giờ — có thể dừng tại Lịch sử gửi mail.";
        }

        Notification::make()
            ->title($isRecurring ? 'Đã gửi và lên lịch gửi lại' : 'Gửi email hoàn tất')
            ->body($body)
            ->success()
            ->send();

        $this->clearFormDraft();
        $this->form->fill([
            'email_template_id' => null,
            'recipients' => [],
            'variables' => [],
            'custom_subject' => '',
            'custom_body' => '',
            'attachments' => [],
        ]);

        if ($result['failed'] > 0 && $result['errors'] !== []) {
            Notification::make()
                ->title('Chi tiết lỗi')
                ->body(implode("\n", array_slice($result['errors'], 0, 5)))
                ->warning()
                ->send();
        }
    }
}
