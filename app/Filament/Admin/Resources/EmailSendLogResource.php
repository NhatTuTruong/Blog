<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EmailSendLogResource\Pages;
use App\Models\EmailSendLog;
use App\Models\User;
use App\Services\EmailRecurringService;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmailSendLogResource extends Resource
{
    protected static ?string $model = EmailSendLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Lịch sử gửi mail';

    protected static ?string $modelLabel = 'Lịch sử';

    protected static ?string $pluralModelLabel = 'Lịch sử gửi mail';

    protected static ?string $navigationGroup = 'Email';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Thông tin gửi')
                    ->schema([
                        Infolists\Components\TextEntry::make('template_name')
                            ->label('Mẫu')
                            ->columnSpan(['default' => 2, 'lg' => 1]),
                        Infolists\Components\TextEntry::make('sent_count')
                            ->label('Gửi thành công')
                            ->badge()
                            ->color('success')
                            ->columnSpan(1),
                        Infolists\Components\TextEntry::make('failed_count')
                            ->label('Thất bại')
                            ->badge()
                            ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                            ->columnSpan(1),
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Người gửi (admin)')
                            ->placeholder('—')
                            ->columnSpan(['default' => 2, 'lg' => 1]),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Thời gian gửi')
                            ->dateTime('d/m/Y H:i')
                            ->columnSpan(1),
                        Infolists\Components\TextEntry::make('recurring_status')
                            ->label('Gửi lại')
                            ->getStateUsing(fn (EmailSendLog $record): string => $record->recurringSchedule?->statusLabel() ?? 'Gửi 1 lần')
                            ->visible(fn (EmailSendLog $record): bool => $record->hasRecurringSchedule())
                            ->columnSpan(1),
                        Infolists\Components\TextEntry::make('recurringSchedule.next_send_at')
                            ->label('Lần gửi lại tiếp theo')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—')
                            ->visible(fn (EmailSendLog $record): bool => $record->recurringSchedule?->isActive() ?? false)
                            ->columnSpan(1),
                        Infolists\Components\TextEntry::make('recipients')
                            ->label('Người nhận')
                            ->getStateUsing(fn (EmailSendLog $record): string => EmailSendLog::formatList($record->recipients))
                            ->placeholder('—')
                            ->columnSpan(['default' => 2, 'lg' => 2]),
                    ])
                    ->columns(['default' => 2, 'lg' => 3])
                    ->compact(),
                Infolists\Components\Section::make('Nội dung đã gửi')
                    ->schema([
                        Infolists\Components\TextEntry::make('subject')
                            ->label('Tiêu đề')
                            ->columnSpan(['default' => 2, 'lg' => 3]),
                        Infolists\Components\TextEntry::make('body')
                            ->label('Nội dung')
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 2, 'lg' => 3])
                    ->compact(),
                Infolists\Components\Section::make('Tệp đính kèm')
                    ->schema([
                        Infolists\Components\ViewEntry::make('attachments_list')
                            ->hiddenLabel()
                            ->view('filament.admin.email-send-logs.attachments')
                            ->columnSpanFull(),
                    ])
                    ->compact()
                    ->visible(fn (EmailSendLog $record): bool => EmailSendLog::normalizeArray($record->attachments) !== []),
                Infolists\Components\Section::make('Lỗi')
                    ->schema([
                        Infolists\Components\TextEntry::make('errors')
                            ->label('')
                            ->getStateUsing(function (EmailSendLog $record): string {
                                $errors = EmailSendLog::normalizeArray($record->errors);

                                return $errors !== [] ? implode("\n", $errors) : '—';
                            })
                            ->columnSpanFull(),
                    ])
                    ->compact()
                    ->visible(fn (EmailSendLog $record): bool => EmailSendLog::normalizeArray($record->errors) !== []),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query): void {
                $query->with('recurringSchedule');

                $user = auth()->user();
                if ($user instanceof User && ! $user->isAdmin()) {
                    $query->where('user_id', $user->id);
                }
            })
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Chưa có lịch sử gửi mail')
            ->emptyStateDescription('Lịch sử sẽ hiển thị sau khi bạn gửi email từ mẫu.')
            ->columns([
                Tables\Columns\TextColumn::make('template_name')
                    ->label('Mẫu')
                    ->searchable(),
                Tables\Columns\TextColumn::make('recipients')
                    ->label('Số người nhận')
                    ->formatStateUsing(fn (mixed $state): int => count(EmailSendLog::normalizeArray($state)))
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('sent_count')
                    ->label('OK')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('failed_count')
                    ->label('Lỗi')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Tiêu đề')
                    ->limit(35),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Người gửi')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Thời gian')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('recurring_status')
                    ->label('Gửi lại')
                    ->badge()
                    ->getStateUsing(function (EmailSendLog $record): ?string {
                        $schedule = $record->recurringSchedule;

                        if ($schedule === null) {
                            return null;
                        }

                        return $schedule->isActive()
                            ? 'Mỗi '.$schedule->intervalLabel()
                            : 'Đã dừng';
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        null => 'gray',
                        'Đã dừng' => 'gray',
                        default => 'info',
                    })
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('stopRecurring')
                    ->label('')
                    ->icon('heroicon-o-stop-circle')
                    ->tooltip('Dừng gửi lại')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Dừng gửi lại email')
                    ->modalDescription('Email sẽ không được gửi lại theo lịch nữa. Các lần đã gửi vẫn giữ trong lịch sử.')
                    ->visible(fn (EmailSendLog $record): bool => $record->recurringSchedule?->isActive() ?? false)
                    ->action(function (EmailSendLog $record): void {
                        $schedule = $record->recurringSchedule;

                        if ($schedule === null || ! $schedule->isActive()) {
                            Notification::make()
                                ->title('Lịch gửi lại đã dừng trước đó')
                                ->warning()
                                ->send();

                            return;
                        }

                        app(EmailRecurringService::class)->stopSchedule($schedule);

                        Notification::make()
                            ->title('Đã dừng gửi lại')
                            ->body('Email «'.$record->subject.'» sẽ không gửi lại theo lịch.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('stopRecurringBulk')
                    ->label('')
                    ->icon('heroicon-o-stop-circle')
                    ->tooltip('Dừng gửi lại đã chọn')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Dừng gửi lại các email đã chọn')
                    ->modalDescription('Các lịch gửi lại đang chạy sẽ bị dừng.')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (\Illuminate\Support\Collection $records): void {
                        $service = app(EmailRecurringService::class);
                        $stopped = 0;
                        $scheduleIds = [];

                        foreach ($records as $record) {
                            if (! $record instanceof EmailSendLog) {
                                continue;
                            }

                            $record->loadMissing('recurringSchedule');
                            $schedule = $record->recurringSchedule;

                            if ($schedule !== null && isset($scheduleIds[$schedule->id])) {
                                continue;
                            }

                            if ($schedule !== null && $schedule->isActive()) {
                                $service->stopSchedule($schedule);
                                $scheduleIds[$schedule->id] = true;
                                $stopped++;
                            }
                        }

                        Notification::make()
                            ->title($stopped > 0 ? 'Đã dừng '.$stopped.' lịch gửi lại' : 'Không có lịch đang chạy')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailSendLogs::route('/'),
            'view' => Pages\ViewEmailSendLog::route('/{record}'),
        ];
    }
}

