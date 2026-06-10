<?php

namespace App\Providers;

use App\Support\MailSettings;
use App\Support\PublicStorage;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
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
        $this->app->singleton(
            \Filament\Http\Responses\Auth\Contracts\LoginResponse::class,
            \App\Http\Responses\Auth\LoginResponse::class,
        );
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        app()->setLocale((string) config('app.locale', 'vi'));

        PublicStorage::ensureDirectory('');

        FileUpload::configureUsing(function (FileUpload $component): void {
            $component->validationMessages([
                'max' => 'Dung lượng file không được vượt quá :max KB.',
                'uploaded' => 'Tải file lên thất bại — file vượt giới hạn PHP (upload_max_filesize '.ini_get('upload_max_filesize').', post_max_size '.ini_get('post_max_size').'). Khởi động lại Apache/WAMP sau khi sửa php.ini.',
                'mimes' => 'Định dạng file không được hỗ trợ.',
            ]);

            if ($component->getDiskName() !== 'public') {
                return;
            }

            $component->saveUploadedFileUsing(function ($file, BaseFileUpload $component): ?string {
                $directory = (string) ($component->getDirectory() ?? '');
                $filename = $component->getUploadedFileNameForStorage($file);
                $stored = PublicStorage::storeUploadedFile($file, $directory, $filename);

                return PublicStorage::syncUploadedPath($stored);
            });
        });

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
