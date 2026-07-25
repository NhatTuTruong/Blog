<?php

namespace App\Filament\Admin\Resources\ReceivedEmailResource\Pages;

use App\Filament\Admin\Resources\ReceivedEmailResource;
use App\Services\IncomingMailService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewReceivedEmail extends ViewRecord
{
    protected static string $resource = ReceivedEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('fetchAttachments')
                ->label('')
                ->icon('heroicon-o-arrow-down-tray')
                ->tooltip('Tải đính kèm')
                ->color('gray')
                ->visible(fn (): bool => $this->record->attachments_count > 0 && ! $this->record->hasStoredAttachments())
                ->action(function (): void {
                    $service = app(IncomingMailService::class);
                    $success = $service->fetchAttachmentsForRecord($this->record);

                    if (! $success) {
                        Notification::make()
                            ->title('Không tải được đính kèm')
                            ->body($service->lastError ?? 'Không lấy được file từ hộp thư IMAP.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->record->refresh();

                    Notification::make()
                        ->title('Đã tải đính kèm')
                        ->body('Các file đính kèm đã sẵn sàng để xem và tải xuống.')
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make()->label(''),
        ];
    }
}
