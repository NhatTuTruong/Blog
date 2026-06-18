<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\AuthorizesPanelAccess;
use Filament\Pages\Page;

/**
 * @deprecated Dùng {@see SystemSettings}. Giữ lại để chuyển hướng URL cũ.
 */
class UserIntegrationSettings extends Page
{
    use AuthorizesPanelAccess;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static string $view = 'filament.admin.pages.user-integration-settings';

    protected static ?string $navigationGroup = 'Cài đặt';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return static::canAccessMemberFeatures();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->redirect(SystemSettings::getUrl(), navigate: true);
    }
}
