<?php

namespace App\Support;

/**
 * Các toggle Auto Blog trong Cài đặt hệ thống (dùng chung cron + nút AI trong admin).
 */
class AutoBlogSettings
{
    /**
     * @return array<int, string> best|guide|comparison
     */
    public static function enabledCategoryVariants(): array
    {
        $variants = [];
        if ((bool) AdminSettings::get('auto_blog_variant_best', true)) {
            $variants[] = 'best';
        }
        if ((bool) AdminSettings::get('auto_blog_variant_guide', true)) {
            $variants[] = 'guide';
        }
        if ((bool) AdminSettings::get('auto_blog_variant_comparison', true)) {
            $variants[] = 'comparison';
        }

        return $variants;
    }

    /**
     * Các chế độ nút "Tạo bài bằng AI".
     *
     * @return array<int, string>
     */
    public static function enabledManualAiModes(): array
    {
        return static::enabledCategoryVariants();
    }
}
