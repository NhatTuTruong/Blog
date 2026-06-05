<?php

namespace App\Listeners;

use App\Models\User;
use Filament\Actions\Imports\Events\ImportCompleted;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;

class SendImportCompletedDatabaseNotification
{
    public function handle(ImportCompleted $event): void
    {
        $import = $event->getImport();
        $user = $import->user_id ? User::find($import->user_id) : null;

        if (! $user) {
            return;
        }

        $failedRowsCount = $import->getFailedRowsCount();
        $successCount = $import->successful_rows ?? 0;
        $totalRows = $import->total_rows ?? 0;
        $fileName = $import->file_name ?? 'file.csv';

        $notification = Notification::make()
            ->title($failedRowsCount ? 'Import có lỗi' : 'Import hoàn tất')
            ->body($this->buildBody($successCount, $totalRows, $failedRowsCount, $fileName))
            ->persistent();

        if (! $failedRowsCount) {
            $notification->success()->icon('heroicon-o-check-circle');
        } elseif ($failedRowsCount < $totalRows) {
            $notification->warning()->icon('heroicon-o-exclamation-triangle');
        } else {
            $notification->danger()->icon('heroicon-o-x-circle');
        }

        if ($failedRowsCount > 0) {
            $notification->actions([
                NotificationAction::make('downloadFailedRows')
                    ->label("Tải file dòng lỗi ({$failedRowsCount})")
                    ->color('danger')
                    ->url(route('admin.imports.failed-rows.download', ['import' => $import], absolute: false), shouldOpenInNewTab: true)
                    ->markAsRead(),
            ]);
        }

        $notification->sendToDatabase($user);
    }

    private function buildBody(int $successCount, int $totalRows, int $failedCount, string $fileName): string
    {
        $parts = ["{$fileName}: {$successCount}/{$totalRows} dòng thành công."];
        if ($failedCount > 0) {
            $parts[] = "{$failedCount} dòng lỗi.";
        }
        return implode(' ', $parts);
    }
}
