<?php

namespace App\Listeners;

use Filament\Actions\Imports\Events\ImportCompleted;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;

class SendImportCompletedNotificationWhenNoUser
{
    public function handle(ImportCompleted $event): void
    {
        $import = $event->getImport();

        if ($import->user_id !== null) {
            return;
        }

        $failedRowsCount = $import->getFailedRowsCount();

        $notification = Notification::make()
            ->title($import->importer::getCompletedNotificationTitle($import))
            ->body($import->importer::getCompletedNotificationBody($import))
            ->persistent();

        if (! $failedRowsCount) {
            $notification->success();
        } elseif ($failedRowsCount < $import->total_rows) {
            $notification->warning();
        } else {
            $notification->danger();
        }

        if ($failedRowsCount > 0) {
            $notification->actions([
                NotificationAction::make('downloadFailedRowsCsv')
                    ->label("Tải file dòng lỗi ({$failedRowsCount} dòng)")
                    ->color('danger')
                    ->url(route('admin.imports.failed-rows.download', ['import' => $import], absolute: false), shouldOpenInNewTab: true)
                    ->markAsRead(),
            ]);
        }

        $notification->send();
    }
}
