<?php

namespace App\Notifications;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Notifications\Notification as BaseNotification;

/**
 * Giống Filament\DatabaseNotification nhưng không queue — ghi thẳng vào bảng notifications.
 */
class SyncFilamentDatabaseNotification extends BaseNotification implements Arrayable
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $data,
    ) {}

    /**
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
