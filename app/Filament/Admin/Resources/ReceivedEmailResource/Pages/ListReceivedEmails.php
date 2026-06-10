<?php

namespace App\Filament\Admin\Resources\ReceivedEmailResource\Pages;

use App\Filament\Admin\Resources\ReceivedEmailResource;
use App\Models\ReceivedEmail;
use App\Services\IncomingMailService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListReceivedEmails extends ListRecords
{
    protected static string $resource = ReceivedEmailResource::class;

    protected static string $view = 'filament.admin.resources.received-emails.list-records';

    public int $trackedMaxEmailId = 0;

    public function mount(): void
    {
        parent::mount();

        $this->trackedMaxEmailId = (int) ReceivedEmail::query()->max('id');
        $this->showConfigWarningsIfNeeded();
    }

    public function table(Table $table): Table
    {
        return parent::table($table)->poll(null);
    }

    public function getInboxPollInterval(): ?string
    {
        $seconds = (int) config('imap.ui_poll_seconds', 15);

        return $seconds > 0 ? "{$seconds}s" : null;
    }

    public function pollReceivedEmails(): void
    {
        $currentMax = (int) ReceivedEmail::query()->max('id');

        if ($currentMax <= $this->trackedMaxEmailId) {
            return;
        }

        $newCount = ReceivedEmail::query()
            ->where('id', '>', $this->trackedMaxEmailId)
            ->count();

        $this->trackedMaxEmailId = $currentMax;

        $this->resetTable();
    }

    protected function getTablePollingInterval(): ?string
    {
        return null;
    }

    protected function showConfigWarningsIfNeeded(): void
    {
        $service = app(IncomingMailService::class);

        if (! $service->imapExtensionAvailable()) {
            Notification::make()
                ->title('Chưa bật PHP IMAP')
                ->body('Bật extension imap trong php.ini để đồng bộ email nhận.')
                ->warning()
                ->persistent()
                ->send();
        } elseif (! $service->isConfigured()) {
            Notification::make()
                ->title('Chưa cấu hình IMAP')
                ->body('Cấu hình Email tại Cài đặt tùy chỉnh (hoặc file .env).')
                ->warning()
                ->persistent()
                ->send();
        }
    }
}
