<?php

namespace App\Http\Responses\Auth;

use App\Filament\Admin\Pages\SocialMediaPublish;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements Responsable
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $panel = Filament::getCurrentPanel();
        $user = Auth::user();

        if ($user instanceof User && ! $user->isAdmin()) {
            return redirect()->to(SocialMediaPublish::getUrl());
        }

        if ($panel !== null) {
            return redirect()->intended($panel->getUrl());
        }

        return redirect()->intended('/admin');
    }
}
