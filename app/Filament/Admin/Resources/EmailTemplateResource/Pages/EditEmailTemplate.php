<?php

namespace App\Filament\Admin\Resources\EmailTemplateResource\Pages;

use App\Filament\Admin\Pages\SendTemplatedEmail;
use App\Filament\Admin\Resources\EmailTemplateResource;
use App\Filament\Concerns\HasEmailTemplateFormDraft;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmailTemplate extends EditRecord
{
    use HasEmailTemplateFormDraft;

    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getFormDraftDiscardAction(),
            Actions\Action::make('send')
                ->label('')
                ->icon('heroicon-o-paper-airplane')
                ->tooltip('Gửi email')
                ->color('success')
                ->url(SendTemplatedEmail::urlWithTemplate($this->record->getKey())),
            Actions\DeleteAction::make()->label(''),
        ];
    }
}
