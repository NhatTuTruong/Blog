<?php

use App\Models\SiteContent;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateNavUrls('header_nav');
        $this->updateFooterColumns();
    }

    public function down(): void
    {
        $this->revertNavUrls('header_nav', '/blogs', '/blog');
        $this->revertFooterColumns('/blogs', '/blog');
    }

    protected function updateNavUrls(string $key): void
    {
        $links = SiteContent::get($key);

        if (! is_array($links)) {
            return;
        }

        $updated = collect($links)->map(function (array $link): array {
            if (isset($link['url'])) {
                $link['url'] = $this->normalizeBlogListingUrl((string) $link['url']);
            }

            return $link;
        })->all();

        SiteContent::set($key, $updated);
    }

    protected function updateFooterColumns(): void
    {
        $columns = SiteContent::get('footer_columns');

        if (! is_array($columns)) {
            return;
        }

        $updated = collect($columns)->map(function (array $column): array {
            if (! isset($column['links']) || ! is_array($column['links'])) {
                return $column;
            }

            $column['links'] = collect($column['links'])->map(function (array $link): array {
                if (isset($link['url'])) {
                    $link['url'] = $this->normalizeBlogListingUrl((string) $link['url']);
                }

                return $link;
            })->all();

            return $column;
        })->all();

        SiteContent::set('footer_columns', $updated);
    }

    protected function normalizeBlogListingUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '/blog' || $url === 'blog') {
            return '/blogs';
        }

        if ($url === '/blog1' || $url === 'blog1') {
            return '/blogs';
        }

        if (preg_match('~^/blog/([^/?#]+)$~', $url, $matches)) {
            return '/blogs/'.$matches[1];
        }

        if (preg_match('~^blog/([^/?#]+)$~', $url, $matches)) {
            return '/blogs/'.$matches[1];
        }

        return $url;
    }

    protected function revertNavUrls(string $key, string $from, string $to): void
    {
        $links = SiteContent::get($key);

        if (! is_array($links)) {
            return;
        }

        $updated = collect($links)->map(function (array $link) use ($from, $to): array {
            if (($link['url'] ?? null) === $from) {
                $link['url'] = $to;
            }

            return $link;
        })->all();

        SiteContent::set($key, $updated);
    }

    protected function revertFooterColumns(string $from, string $to): void
    {
        $columns = SiteContent::get('footer_columns');

        if (! is_array($columns)) {
            return;
        }

        $updated = collect($columns)->map(function (array $column) use ($from, $to): array {
            if (! isset($column['links']) || ! is_array($column['links'])) {
                return $column;
            }

            $column['links'] = collect($column['links'])->map(function (array $link) use ($from, $to): array {
                if (($link['url'] ?? null) === $from) {
                    $link['url'] = $to;
                }

                return $link;
            })->all();

            return $column;
        })->all();

        SiteContent::set('footer_columns', $updated);
    }
};
