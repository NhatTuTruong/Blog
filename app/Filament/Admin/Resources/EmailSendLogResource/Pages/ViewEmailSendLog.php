<?php

namespace App\Filament\Admin\Resources\EmailSendLogResource\Pages;

use App\Filament\Admin\Pages\SendTemplatedEmail;
use App\Filament\Admin\Resources\EmailSendLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEmailSendLog extends ViewRecord
{
    protected static string $resource = EmailSendLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resend')
                ->label('Gửi lại')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->url(fn (): string => SendTemplatedEmail::urlWithResend($this->record->getKey())),
        ];
    }
}
