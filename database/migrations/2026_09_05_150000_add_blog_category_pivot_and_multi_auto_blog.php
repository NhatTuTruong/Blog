<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blog_blog_category')) {
            Schema::create('blog_blog_category', function (Blueprint $table) {
                $table->foreignId('blog_id')->constrained()->cascadeOnDelete();
                $table->foreignId('blog_category_id')->constrained()->cascadeOnDelete();

                $table->primary(['blog_id', 'blog_category_id']);
            });
        }

        if (Schema::hasTable('blogs') && Schema::hasTable('blog_blog_category')) {
            $existing = DB::table('blogs')
                ->whereNotNull('blog_category_id')
                ->select('id', 'blog_category_id')
                ->get();

            foreach ($existing as $row) {
                DB::table('blog_blog_category')->updateOrInsert(
                    [
                        'blog_id' => $row->id,
                        'blog_category_id' => $row->blog_category_id,
                    ],
                    [],
                );
            }
        }

        if (Schema::hasTable('auto_blog_queue_items') && ! Schema::hasColumn('auto_blog_queue_items', 'blog_category_ids')) {
            Schema::table('auto_blog_queue_items', function (Blueprint $table) {
                $table->json('blog_category_ids')->nullable()->after('blog_category_id');
            });

            DB::table('auto_blog_queue_items')
                ->whereNotNull('blog_category_id')
                ->orderBy('id')
                ->get()
                ->each(function (object $row): void {
                    DB::table('auto_blog_queue_items')
                        ->where('id', $row->id)
                        ->update([
                            'blog_category_ids' => json_encode([(int) $row->blog_category_id]),
                        ]);
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('auto_blog_queue_items') && Schema::hasColumn('auto_blog_queue_items', 'blog_category_ids')) {
            Schema::table('auto_blog_queue_items', function (Blueprint $table) {
                $table->dropColumn('blog_category_ids');
            });
        }

        Schema::dropIfExists('blog_blog_category');
    }
};
