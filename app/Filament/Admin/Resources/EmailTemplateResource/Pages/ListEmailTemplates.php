<?php

namespace App\Filament\Admin\Resources\EmailTemplateResource\Pages;

use App\Filament\Admin\Pages\SendTemplatedEmail;
use App\Filament\Admin\Resources\EmailTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmailTemplates extends ListRecords
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sendEmail')
                ->label('Gửi email')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->url(SendTemplatedEmail::getUrl()),
            Actions\CreateAction::make()
                ->label('Thêm mẫu'),
        ];
    }
}
