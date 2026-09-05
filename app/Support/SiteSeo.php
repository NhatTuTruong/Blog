<?php

namespace App\Support;

use App\Models\SiteContent;

class SiteSeo
{
    public static function settings(): array
    {
        $defaults = SiteContent::defaultSeoSettings();
        $stored = SiteContent::get('seo_settings');

        if (! is_array($stored)) {
            $stored = self::legacySettings();
        }

        return array_replace_recursive($defaults, $stored);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function legacySettings(): array
    {
        $appName = config('app.name');

        return [
            'title_suffix' => (string) AdminSettings::get('seo_title_suffix', '- '.$appName),
            'meta_description_default' => (string) AdminSettings::get(
                'seo_meta_description_default',
                'Latest articles and insights from our blog.'
            ),
            'og_image_default' => (string) AdminSettings::get('seo_og_image_default', ''),
        ];
    }

    public static function get(string $path, mixed $default = null): mixed
    {
        $value = self::settings();
        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        if ($value === null || $value === '') {
            return $default;
        }

        return $value;
    }

    public static function pageTitle(string $page): string
    {
        return (string) self::get("pages.{$page}.title", '');
    }

    public static function pageDescription(string $page): string
    {
        return (string) self::get("pages.{$page}.description", '');
    }
}
