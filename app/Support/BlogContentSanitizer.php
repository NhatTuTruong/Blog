<?php

namespace App\Support;

class BlogContentSanitizer
{
    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $html = self::unwrapMarkdownCodeFences($html);
        $html = self::unwrapPreCodeHtml($html);
        $html = self::mergeFragmentedLists($html);
        $html = self::closeListsBeforeBlockElements($html);
        $html = self::removeDuplicateListClosures($html);
        $html = preg_replace("/\n{3,}/", "\n\n", $html) ?? $html;

        return trim($html);
    }

    public static function looksLikeHtml(string $text): bool
    {
        return (bool) preg_match('/<(?:h[1-6]|p|ul|ol|li|div|blockquote|table|a|strong|em|br)\b/i', $text);
    }

    public static function unwrapMarkdownCodeFences(string $html): string
    {
        return preg_replace_callback('/```(?:\w+\s*)?\n?([\s\S]*?)```/', function (array $matches): string {
            $inner = trim($matches[1]);
            if (self::looksLikeHtml($inner)) {
                return $inner;
            }

            return '<pre><code>'.htmlspecialchars($inner, ENT_QUOTES, 'UTF-8').'</code></pre>';
        }, $html) ?? $html;
    }

    public static function unwrapPreCodeHtml(string $html): string
    {
        return preg_replace_callback('/<pre>\s*<code>([\s\S]*?)<\/code>\s*<\/pre>/i', function (array $matches): string {
            $inner = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (self::looksLikeHtml($inner)) {
                return $inner;
            }

            return $matches[0];
        }, $html) ?? $html;
    }

    public static function mergeFragmentedLists(string $html): string
    {
        $previous = null;
        while ($previous !== $html) {
            $previous = $html;
            $html = preg_replace('/<\/ul>\s*<ul>/i', '', $html) ?? $html;
            $html = preg_replace('/<\/ol>\s*<ol>/i', '', $html) ?? $html;
        }

        return $html;
    }

    public static function closeListsBeforeBlockElements(string $html): string
    {
        return preg_replace(
            '/(<\/li>)(?!\s*<\/(?:ul|ol)>)(\s*<(?:p|h[1-6]|div|blockquote)\b)/i',
            "$1\n</ul>$2",
            $html
        ) ?? $html;
    }

    public static function removeDuplicateListClosures(string $html): string
    {
        $html = preg_replace('/(<\/ul>\s*){2,}/i', '</ul>', $html) ?? $html;
        $html = preg_replace('/(<\/ol>\s*){2,}/i', '</ol>', $html) ?? $html;

        return $html;
    }
}
