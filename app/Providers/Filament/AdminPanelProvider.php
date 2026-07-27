<?php

namespace App\Providers\Filament;

use App\Http\Middleware\RememberAdminIpMiddleware;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use App\Filament\Admin\Pages\Dashboard as AdminDashboard;
use App\Filament\Admin\Pages\SocialMediaPublish;
use App\Models\User;
use App\Filament\Admin\Widgets\BlogCategoryChartWidget;
use App\Filament\Admin\Widgets\BlogPostsChartWidget;
use App\Filament\Admin\Widgets\BlogStatsOverviewWidget;
use App\Filament\Admin\Widgets\TopBlogPostsWidget;
use Filament\Enums\ThemeMode;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\View\PanelsRenderHook;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->homeUrl(function (): string {
                $user = auth()->user();

                if ($user instanceof User && ! $user->isAdmin()) {
                    return SocialMediaPublish::getUrl();
                }

                return AdminDashboard::getUrl();
            })
            ->login()
            ->authGuard('web')
            ->authMiddleware([Authenticate::class])
            ->databaseNotifications()
            ->databaseNotificationsPolling(
                ((int) config('imap.notifications_poll_seconds', 10)).'s'
            )
            ->font('Inter')
            ->colors([
                'primary' => Color::hex('#1d9bf0'),
                'gray' => [
                    50 => '240, 244, 248',
                    100 => '200, 205, 212',
                    200 => '72, 79, 88',
                    300 => '72, 79, 88',
                    400 => '139, 148, 158',
                    500 => '139, 148, 158',
                    600 => '110, 118, 129',
                    700 => '72, 79, 88',
                    800 => '28, 33, 40',
                    900 => '22, 27, 34',
                    950 => '14, 17, 22',
                ],
            ])
            ->defaultThemeMode(ThemeMode::Dark)
            ->darkMode(isForced: true)
            ->maxContentWidth(\Filament\Support\Enums\MaxWidth::Full)
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->navigationGroups([
                NavigationGroup::make()->label('Blog'),
                NavigationGroup::make()->label('Mạng xã hội'),
                NavigationGroup::make()->label('Email'),
                NavigationGroup::make()->label('Cài đặt'),
            ])
            ->pages([
                AdminDashboard::class,
            ])
            ->widgets([
                BlogStatsOverviewWidget::class,
                BlogPostsChartWidget::class,
                BlogCategoryChartWidget::class,
                TopBlogPostsWidget::class,
            ])
            ->middleware([
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                RememberAdminIpMiddleware::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('components.filament-admin-theme')
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('components.admin-favicon')
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('components.filament-file-upload-fouc-fix')
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn () => view('components.rich-editor-paste-normalize')
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn () => view('components.admin-error-toast')
            );
    }
}
