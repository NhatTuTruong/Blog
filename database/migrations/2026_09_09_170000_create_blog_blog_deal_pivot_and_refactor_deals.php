<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blog_blog_deal')) {
            Schema::create('blog_blog_deal', function (Blueprint $table) {
                $table->id();
                $table->foreignId('blog_id')->constrained('blogs')->cascadeOnDelete();
                $table->foreignId('blog_deal_id')->constrained('blog_deals')->cascadeOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['blog_id', 'blog_deal_id']);
                $table->index(['blog_deal_id', 'sort_order']);
            });
        }

        if (! Schema::hasColumn('blog_deals', 'blog_id')) {
            return;
        }

        if (DB::table('blog_blog_deal')->count() === 0) {
            $existingDeals = DB::table('blog_deals')
                ->whereNotNull('blog_id')
                ->orderBy('id')
                ->get(['id', 'blog_id', 'sort_order']);

            foreach ($existingDeals as $deal) {
                DB::table('blog_blog_deal')->insert([
                    'blog_id' => $deal->blog_id,
                    'blog_deal_id' => $deal->id,
                    'sort_order' => $deal->sort_order ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->dropBlogIdColumnFromBlogDeals();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('blog_deals', 'blog_id')) {
            Schema::table('blog_deals', function (Blueprint $table) {
                $table->foreignId('blog_id')->nullable()->after('id')->constrained('blogs')->cascadeOnDelete();
                $table->index(['blog_id', 'sort_order']);
            });

            $pivotRows = DB::table('blog_blog_deal')
                ->orderBy('blog_deal_id')
                ->orderBy('sort_order')
                ->get(['blog_id', 'blog_deal_id', 'sort_order']);

            $assigned = [];
            foreach ($pivotRows as $row) {
                if (isset($assigned[$row->blog_deal_id])) {
                    continue;
                }

                DB::table('blog_deals')
                    ->where('id', $row->blog_deal_id)
                    ->update([
                        'blog_id' => $row->blog_id,
                        'sort_order' => $row->sort_order,
                    ]);

                $assigned[$row->blog_deal_id] = true;
            }

            Schema::table('blog_deals', function (Blueprint $table) {
                $table->foreignId('blog_id')->nullable(false)->change();
            });
        }

        Schema::dropIfExists('blog_blog_deal');
    }

    private function dropBlogIdColumnFromBlogDeals(): void
    {
        if (! Schema::hasColumn('blog_deals', 'blog_id')) {
            return;
        }

        $this->dropForeignIfExists('blog_deals', 'blog_id');
        $this->dropIndexesOnColumn('blog_deals', 'blog_id');

        Schema::table('blog_deals', function (Blueprint $table) {
            $table->dropColumn('blog_id');
        });
    }

    /**
     * Drop foreign key if it exists (avoids MySQL error 1091).
     */
    private function dropForeignIfExists(string $table, string $column): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            try {
                Schema::table($table, function (Blueprint $table) use ($column) {
                    $table->dropForeign([$column]);
                });
            } catch (\Throwable) {
                // Column may never have had a FK on this driver.
            }

            return;
        }

        $constraints = DB::select(
            '
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
            ',
            [$table, $column]
        );

        if ($constraints === []) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($column) {
            $table->dropForeign([$column]);
        });
    }

    /**
     * Drop non-primary indexes that include the column (required before dropColumn on MySQL).
     */
    private function dropIndexesOnColumn(string $table, string $column): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $indexes = DB::select(
            '
            SELECT DISTINCT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND INDEX_NAME != ?
            ',
            [$table, $column, 'PRIMARY']
        );

        if ($indexes === []) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexes) {
            foreach ($indexes as $index) {
                $table->dropIndex($index->INDEX_NAME);
            }
        });
    }
};
