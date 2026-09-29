<?php

namespace App\Support;

class BlogContentAffiliateLayout
{
    /**
     * Split HTML after the first affiliate promo block (sponsored link).
     *
     * @return array{before: string, after: string, aff_url: ?string}
     */
    public static function splitAfterFirstPromoLink(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return ['before' => '', 'after' => '', 'aff_url' => null];
        }

        $patterns = [
            '#<p>\s*<a\s(?=[^>]*\brel=["\'][^"\']*sponsored[^"\']*["\'])(?=[^>]*\bhref=(["\'])([^"\']+)\1)[^>]*>.*?</a>\s*</p>#is',
            '#<p>\s*<a\s(?=[^>]*\bhref=(["\'])([^"\']+)\1)(?=[^>]*\brel=["\'][^"\']*sponsored[^"\']*["\'])[^>]*>.*?</a>\s*</p>#is',
            '#<div>\s*<a\s(?=[^>]*\brel=["\'][^"\']*sponsored[^"\']*["\'])(?=[^>]*\bhref=(["\'])([^"\']+)\1)[^>]*>.*?</a>\s*</div>#is',
            '#<div>\s*<a\s(?=[^>]*\bhref=(["\'])([^"\']+)\1)(?=[^>]*\brel=["\'][^"\']*sponsored[^"\']*["\'])[^>]*>.*?</a>\s*</div>#is',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches, PREG_OFFSET_CAPTURE)) {
                return self::splitAtMatch($html, $matches);
            }
        }

        $anchorPattern = '#<a\s(?=[^>]*\bhref=(["\'])(https?://[^"\']+)\1)(?=[^>]*\brel=["\'][^"\']*sponsored[^"\']*["\'])[^>]*>.*?</a>#is';
        if (preg_match($anchorPattern, $html, $matches, PREG_OFFSET_CAPTURE)) {
            return self::splitAfterAnchorBlock($html, $matches);
        }

        $fallback = '#<a\s[^>]*\bhref=(["\'])(https?://[^"\']+)\1[^>]*>\s*(?:<strong>)?\s*(?:Visit|Get the deal|Shop now)[^<]*#is';
        if (preg_match($fallback, $html, $matches, PREG_OFFSET_CAPTURE)) {
            return self::splitAfterAnchorBlock($html, $matches);
        }

        return ['before' => '', 'after' => $html, 'aff_url' => null];
    }

    /**
     * @param  array<int, array{0: string, 1: int}>  $matches
     * @return array{before: string, after: string, aff_url: ?string}
     */
    private static function splitAtMatch(string $html, array $matches): array
    {
        $block = $matches[0][0];
        $offset = $matches[0][1];
        $affUrl = $matches[2][0] ?? null;
        $end = $offset + strlen($block);

        return [
            'before' => substr($html, 0, $end),
            'after' => ltrim(substr($html, $end)),
            'aff_url' => is_string($affUrl) && $affUrl !== '' ? $affUrl : null,
        ];
    }

    /**
     * @param  array<int, array{0: string, 1: int}>  $matches
     * @return array{before: string, after: string, aff_url: ?string}
     */
    private static function splitAfterAnchorBlock(string $html, array $matches): array
    {
        $anchorStart = $matches[0][1];
        $affUrl = $matches[2][0] ?? null;
        $anchorEnd = $anchorStart + strlen($matches[0][0]);

        $beforeStart = $anchorStart;
        $prefix = substr($html, 0, $anchorStart);
        if (preg_match('#<(p|div)\b[^>]*>\s*$#is', $prefix, $openMatch, PREG_OFFSET_CAPTURE)) {
            $beforeStart = $openMatch[0][1];
        }

        $suffix = substr($html, $anchorEnd);
        $blockEnd = $anchorEnd;
        if (preg_match('#^\s*</(p|div)>#is', $suffix, $closeMatch)) {
            $blockEnd = $anchorEnd + strlen($closeMatch[0]);
        }

        return [
            'before' => substr($html, 0, $blockEnd),
            'after' => ltrim(substr($html, $blockEnd)),
            'aff_url' => is_string($affUrl) && $affUrl !== '' ? $affUrl : null,
        ];
    }
}
