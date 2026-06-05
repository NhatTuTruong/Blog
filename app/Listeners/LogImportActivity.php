<?php

namespace App\Listeners;

use App\Services\AuditService;
use Filament\Actions\Imports\Events\ImportCompleted;

class LogImportActivity
{
    public function handle(ImportCompleted $event): void
    {
        $import = $event->getImport();
        $importer = class_basename($import->importer);
        $rows = $import->successful_rows ?? $import->total_rows ?? 0;

        AuditService::logImport($importer, (int) $rows, $import->user_id);
    }
}
