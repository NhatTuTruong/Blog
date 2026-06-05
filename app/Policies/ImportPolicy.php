<?php

namespace App\Policies;

use Filament\Actions\Imports\Models\Import;
use Illuminate\Contracts\Auth\Authenticatable;

class ImportPolicy
{
    public function view(?Authenticatable $user, Import $import): bool
    {
        if ($import->user_id === null) {
            return true;
        }

        return $user && $import->user()->is($user);
    }
}
