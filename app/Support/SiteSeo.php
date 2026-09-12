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

        return self::decodePlainTextStrings(array_replace_recursive($defaults, $stored));
    }

    /** Chuỗi SEO lưu dạng plain text; decode entity tránh hiển thị &amp; trên &lt;title&gt;. */
    public static function plainText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($decoded !== $value && str_contains($decoded, '&')) {
            $again = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($again !== $decoded) {
                return $again;
            }
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function decodePlainTextStrings(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = self::plainText($value);
            } elseif (is_array($value)) {
                $data[$key] = self::decodePlainTextStrings($value);
            }
        }

        return $data;
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
        $title = trim((string) self::get("pages.{$page}.title", ''));

        return $title !== '' ? $title : (string) config('app.name');
    }

    public static function pageDescription(string $page): string
    {
        $description = trim((string) self::get("pages.{$page}.description", ''));

        return $description !== '' ? $description : (string) self::get(
            'meta_description_default',
            'Latest articles and insights from our blog.'
        );
    }

    public static function absoluteUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }
}
