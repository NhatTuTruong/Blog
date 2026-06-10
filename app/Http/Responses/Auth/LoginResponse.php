<?php

namespace App\Http\Responses\Auth;

use App\Filament\Admin\Pages\SocialMediaPublish;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LoginResponse implements Responsable
{
    public function toResponse($request): RedirectResponse
    {
        $panel = Filament::getCurrentPanel();
        $user = Auth::user();

        if ($user instanceof User && ! $user->isAdmin()) {
            $url = SocialMediaPublish::getUrl();
        } elseif ($panel !== null) {
            $url = $panel->getUrl();
        } else {
            $url = '/admin';
        }

        $request->session()->save();

        return new RedirectResponse($url);
    }
}
