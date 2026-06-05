<?php

use App\Models\SiteContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        $tables = [
            'failed_import_rows',
            'customer_leads',
            'landing_page_checks',
            'activity_logs',
            'page_views',
            'clicks',
            'coupons',
            'assets',
            'campaigns',
            'brands',
            'categories',
            'blocked_ips',
            'exports',
            'imports',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }

        Schema::enableForeignKeyConstraints();

        if (Schema::hasColumn('users', 'code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('code');
            });
        }

        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }

        if (Schema::hasTable('site_contents')) {
            DB::table('site_contents')->where('key', 'page_affiliate')->delete();

            $footerDefaults = SiteContent::defaultFooterColumns();
            $this->upsertSiteContent('footer_columns', json_encode($footerDefaults));
            $this->upsertSiteContent('footer_brand_description', 'Articles, guides and stories — updated regularly.');
            $this->upsertSiteContent('header_nav', json_encode(SiteContent::defaultHeaderNav()));
        }
    }

    protected function upsertSiteContent(string $key, string $value): void
    {
        $exists = DB::table('site_contents')->where('key', $key)->exists();
        if ($exists) {
            DB::table('site_contents')->where('key', $key)->update(['value' => $value]);
        } else {
            DB::table('site_contents')->insert(['key' => $key, 'value' => $value]);
        }
    }

    public function down(): void
    {
        // Coupon tables are not restored.
    }
};
