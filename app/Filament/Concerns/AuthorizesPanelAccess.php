<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Filament\Facades\Filament;

trait AuthorizesPanelAccess
{
    protected static function panelUser(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function canAccessAdminFeatures(): bool
    {
        return static::panelUser()?->isAdmin() ?? false;
    }

    public static function canAccessMemberFeatures(): bool
    {
        return static::panelUser() !== null;
    }
}
