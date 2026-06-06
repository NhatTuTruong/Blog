<?php

namespace App\Providers;

use App\Support\MailSettings;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Table::configureUsing(fn (Table $table): Table => \App\Filament\Admin\Support\AdminTable::make($table));

        EditAction::configureUsing(function (EditAction $action): void {
            $action
                ->label('')
                ->icon('heroicon-o-pencil-square')
                ->tooltip('Sửa');
        });

        ViewAction::configureUsing(function (ViewAction $action): void {
            $action
                ->label('')
                ->icon('heroicon-o-eye')
                ->tooltip('Xem');
        });

        DeleteAction::configureUsing(function (DeleteAction $action): void {
            $action
                ->label('')
                ->icon('heroicon-o-trash')
                ->tooltip('Xóa');
        });

        RestoreAction::configureUsing(function (RestoreAction $action): void {
            $action
                ->label('')
                ->icon('heroicon-o-arrow-uturn-left')
                ->tooltip('Khôi phục');
        });

        ForceDeleteAction::configureUsing(function (ForceDeleteAction $action): void {
            $action
                ->label('')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->tooltip('Xóa vĩnh viễn');
        });

        try {
            if (Schema::hasTable('site_contents')) {
                MailSettings::applyToConfig();
            }
        } catch (\Throwable) {
            // Bỏ qua khi migrate / chưa có DB
        }
    }
}
