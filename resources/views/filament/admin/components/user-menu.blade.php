@php
    use Filament\Facades\Filament;
    $user = Filament::auth()->user();
    $panel = Filament::getCurrentPanel();
@endphp

@if($user)
<div class="fi-topbar-user-menu flex items-center gap-3">
    <div class="flex items-center gap-2">
        <div class="fi-topbar-user-avatar flex items-center justify-center w-8 h-8 rounded-full bg-primary-500 text-white font-medium text-sm">
            {{ strtoupper(substr($user->name ?? $user->email, 0, 1)) }}
        </div>
        <div class="hidden md:block">
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ $user->name ?? $user->email }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                {{ $user->email }}
            </div>
        </div>
    </div>
    <form method="POST" action="{{ $panel?->getLogoutUrl() ?? '/admin/logout' }}" class="inline">
        @csrf
        <button type="submit" 
                class="fi-btn fi-btn-size-sm fi-color-danger fi-btn-color-danger inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg bg-danger-50 text-danger-700 hover:bg-danger-100 dark:bg-danger-500/10 dark:text-danger-400 dark:hover:bg-danger-500/20 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
            </svg>
            <span class="hidden sm:inline">Đăng xuất</span>
        </button>
    </form>
</div>
@else
<div class="fi-topbar-user-menu flex items-center gap-3">
    <a href="{{ $panel?->getLoginUrl() ?? '/admin/login' }}" 
       class="fi-btn fi-btn-size-sm fi-color-primary fi-btn-color-primary inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg bg-primary-50 text-primary-700 hover:bg-primary-100 dark:bg-primary-500/10 dark:text-primary-400 dark:hover:bg-primary-500/20 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
        </svg>
        <span>Đăng nhập</span>
    </a>
</div>
@endif

<style>
.fi-topbar-user-menu {
    margin-left: auto;
    padding-right: 1rem;
}

@media (max-width: 768px) {
    .fi-topbar-user-menu .hidden.md\:block {
        display: none;
    }
}
</style>
