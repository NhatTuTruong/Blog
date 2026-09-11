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
        $html = self::normalizeInlineImages($html);

        return trim($html);
    }

    public static function normalizeInlineImages(string $html): string
    {
        return preg_replace_callback('/<img\b([^>]*?)\/?>/i', function (array $matches): string {
            $attrs = $matches[1];
            $selfClosing = str_ends_with(rtrim($matches[0]), '/>');

            $attrs = preg_replace('/\sheight=(["\'])[^"\']*\1/i', '', $attrs) ?? $attrs;
            $attrs = preg_replace('/\swidth=(["\'])[^"\']*\1/i', '', $attrs) ?? $attrs;

            $attrs = preg_replace_callback('/\sstyle=(["\'])(.*?)\1/is', function (array $styleMatches): string {
                $style = $styleMatches[2];
                $style = preg_replace('/\s*height\s*:\s*[^;]+;?/i', '', $style) ?? $style;
                $style = preg_replace('/\s*width\s*:\s*[^;]+;?/i', '', $style) ?? $style;
                $style = trim($style, " \t\n\r\0\x0B;");

                if ($style === '') {
                    return '';
                }

                $quote = $styleMatches[1];

                return ' style='.$quote.$style.$quote;
            }, $attrs) ?? $attrs;

            $attrs = trim($attrs);
            $suffix = $selfClosing ? ' />' : '>';

            return $attrs === '' ? '<img'.$suffix : '<img '.$attrs.$suffix;
        }, $html) ?? $html;
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
        $offset = 0;

        while (preg_match(
            '/(<\/li>)(?!\s*<\/(?:ul|ol)>)(\s*<(?:p|h[1-6]|div|blockquote)\b)/i',
            $html,
            $matches,
            PREG_OFFSET_CAPTURE,
            $offset
        )) {
            $matchPos = $matches[0][1];
            $closeTag = self::listCloseTagBefore($html, $matchPos);
            $replacement = $matches[1][0]."\n".$closeTag.$matches[2][0];
            $html = substr_replace($html, $replacement, $matchPos, strlen($matches[0][0]));
            $offset = $matchPos + strlen($replacement);
        }

        return $html;
    }

    protected static function listCloseTagBefore(string $html, int $position): string
    {
        $before = substr($html, 0, $position);
        $ulOpens = preg_match_all('/<ul\b/i', $before) ?: 0;
        $ulCloses = preg_match_all('/<\/ul>/i', $before) ?: 0;
        $olOpens = preg_match_all('/<ol\b/i', $before) ?: 0;
        $olCloses = preg_match_all('/<\/ol>/i', $before) ?: 0;

        if ($olOpens > $olCloses) {
            return '</ol>';
        }

        if ($ulOpens > $ulCloses) {
            return '</ul>';
        }

        return '</ul>';
    }

    public static function removeDuplicateListClosures(string $html): string
    {
        $html = preg_replace('/(<\/ul>\s*){2,}/i', '</ul>', $html) ?? $html;
        $html = preg_replace('/(<\/ol>\s*){2,}/i', '</ol>', $html) ?? $html;

        return $html;
    }
}
