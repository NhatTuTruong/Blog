<?php

namespace App\Providers;

use App\Support\MailSettings;
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

        try {
            if (Schema::hasTable('site_contents')) {
                MailSettings::applyToConfig();
            }
        } catch (\Throwable) {
            // Bỏ qua khi migrate / chưa có DB
        }
    }
}
