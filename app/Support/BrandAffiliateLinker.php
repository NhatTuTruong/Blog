<?php

namespace App\Support;

use Illuminate\Support\Str;

class BrandAffiliateLinker
{
    /**
     * Wrap visible brand name mentions with an affiliate (or store) link.
     * Skips text inside existing anchors, script, style, and HTML tags.
     */
    public static function linkMentions(
        string $html,
        string $url,
        string $brandLabel,
        ?string $domainHost = null,
    ): string {
        $html = trim($html);
        $url = trim($url);

        if ($html === '' || $url === '') {
            return $html;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return $html;
        }

        $names = self::brandNameVariants($brandLabel, $domainHost);
        if ($names === []) {
            return $html;
        }

        usort($names, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $pattern = self::buildMatchPattern($names);
        if ($pattern === null) {
            return $html;
        }

        $urlEsc = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $linkOpen = '<a href="'.$urlEsc.'" rel="nofollow sponsored" target="_blank" class="brand-affiliate-link">';

        $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
        }

        $anchorDepth = 0;
        $skipDepth = 0;
        $result = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (str_starts_with($part, '<')) {
                if (preg_match('/^<\s*(\/?)\s*([a-z0-9]+)/i', $part, $tagMatch)) {
                    $closing = $tagMatch[1] === '/';
                    $tag = strtolower($tagMatch[2]);

                    if ($tag === 'a') {
                        $anchorDepth = max(0, $anchorDepth + ($closing ? -1 : 1));
                    } elseif (in_array($tag, ['script', 'style'], true)) {
                        $skipDepth = max(0, $skipDepth + ($closing ? -1 : 1));
                    }
                }

                $result .= $part;

                continue;
            }

            if ($anchorDepth > 0 || $skipDepth > 0) {
                $result .= $part;

                continue;
            }

            $result .= preg_replace_callback(
                $pattern,
                function (array $matches) use ($linkOpen): string {
                    return $linkOpen.$matches[0].'</a>';
                },
                $part
            ) ?? $part;
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    public static function brandNameVariants(string $brandLabel, ?string $domainHost): array
    {
        $variants = [];

        $label = trim($brandLabel);
        if ($label !== '') {
            $variants[] = $label;
        }

        if ($domainHost !== null && $domainHost !== '') {
            $host = strtolower($domainHost);
            if (str_starts_with($host, 'www.')) {
                $host = substr($host, 4);
            }

            $fromDomain = Str::title(str_replace(['-', '_'], ' ', explode('.', $host)[0] ?? ''));
            if ($fromDomain !== '') {
                $variants[] = $fromDomain;
            }

            $variants[] = $host;
            $variants[] = str_replace('.com', '', $host);
        }

        $unique = [];
        foreach ($variants as $name) {
            $name = trim($name);
            if ($name === '' || strlen($name) < 2) {
                continue;
            }
            $key = mb_strtolower($name, 'UTF-8');
            if (! isset($unique[$key])) {
                $unique[$key] = $name;
            }
        }

        return array_values($unique);
    }

    /**
     * @param  array<int, string>  $names
     */
    protected static function buildMatchPattern(array $names): ?string
    {
        $quoted = [];
        foreach ($names as $name) {
            $quoted[] = preg_quote($name, '/');
        }

        if ($quoted === []) {
            return null;
        }

        $alternation = implode('|', $quoted);

        return '/(?<![\w\x{00C0}-\x{1EF9}])(?:'.$alternation.')(?![\w\x{00C0}-\x{1EF9}])/iu';
    }
}
