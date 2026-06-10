<?php

namespace App\Filament\Admin\Resources\EmailSendLogResource\Pages;

use App\Filament\Admin\Pages\SendTemplatedEmail;
use App\Filament\Admin\Resources\EmailSendLogResource;
use App\Services\EmailRecurringService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewEmailSendLog extends ViewRecord
{
    protected static string $resource = EmailSendLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('stopRecurring')
                ->label('Dừng gửi lại')
                ->icon('heroicon-o-stop-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Dừng gửi lại email')
                ->modalDescription('Email sẽ không được gửi lại theo lịch nữa.')
                ->visible(fn (): bool => $this->record->recurringSchedule?->isActive() ?? false)
                ->action(function (): void {
                    $schedule = $this->record->recurringSchedule;

                    if ($schedule === null || ! $schedule->isActive()) {
                        Notification::make()->title('Lịch gửi lại đã dừng')->warning()->send();

                        return;
                    }

                    app(EmailRecurringService::class)->stopSchedule($schedule);

                    Notification::make()
                        ->title('Đã dừng gửi lại')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('resend')
                ->label('Gửi lại')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->url(fn (): string => SendTemplatedEmail::urlWithResend($this->record->getKey())),
        ];
    }
}
