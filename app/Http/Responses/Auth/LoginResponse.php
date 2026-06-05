<?php

namespace App\Http\Responses\Auth;

use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class LoginResponse implements Responsable
{
    public function toResponse($request)
    {
        // Get the panel instance first
        $panel = Filament::getCurrentPanel();
        $url = $panel ? $panel->getUrl() : '/admin';
        
        // Log authentication status before redirect
        try {
            Log::info('LoginResponse: User authenticated', [
                'user_id' => Auth::id(),
                'user_email' => Auth::user()?->email,
                'session_id' => $request->session()->getId(),
            ]);
        } catch (\Exception $e) {
            // Ignore logging errors
        }
        
        // IMPORTANT: Do NOT regenerate session here
        // Filament/Laravel handles session regeneration automatically after login
        // Regenerating here can cause the new session cookie to not be set properly
        
        // Just ensure session is saved
        $request->session()->save();
        
        try {
            Log::info('LoginResponse: Before redirect', [
                'session_id' => $request->session()->getId(),
                'auth_check' => Auth::check(),
            ]);
            Log::info('LoginResponse: Redirecting to', ['url' => $url]);
        } catch (\Exception $e) {
            // Ignore logging errors
        }
        
        // Create redirect response
        // The session middleware will handle attaching the session cookie
        return redirect($url);
    }
}

