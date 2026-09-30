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
        $html = self::expandInlineVideos($html);
        $html = self::normalizeInlineVideoTags($html);
        $html = self::cleanPublicVideoFigures($html);
        $html = self::stripVideoFilenameLinks($html);
        $html = self::stripAttachmentCaptions($html);
        $html = preg_replace("/\n{3,}/", "\n\n", $html) ?? $html;

        return trim($html);
    }

    public static function expandInlineVideos(string $html): string
    {
        $html = preg_replace_callback(
            '/<figure\b[^>]*\sdata-trix-attachment=(["\'])((?:.(?!\1))*?)\1[^>]*>[\s\S]*?<\/figure>/is',
            function (array $matches): string {
                $json = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $data = json_decode($json, true);
                if (! is_array($data)) {
                    return $matches[0];
                }

                $contentType = (string) ($data['contentType'] ?? '');
                $url = (string) ($data['url'] ?? $data['href'] ?? '');
                if ($url === '') {
                    return $matches[0];
                }

                if (self::looksLikeVideoUrl($url, $contentType)) {
                    return self::inlineVideoHtml($url);
                }

                return $matches[0];
            },
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '/<figure\b[^>]*class="[^"]*\bblog-inline-video\b[^"]*"[^>]*(?:data-video-src=(["\'])(.*?)\1)?[^>]*>[\s\S]*?<\/figure>/is',
            function (array $matches): string {
                $url = isset($matches[2]) ? html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
                if ($url === '' && preg_match('/<video\b[^>]*\bsrc=(["\'])(.*?)\1/is', $matches[0], $videoMatch)) {
                    $url = html_entity_decode($videoMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }

                return $url !== '' ? self::inlineVideoHtml($url) : $matches[0];
            },
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '/<figure\b[^>]*class="[^"]*attachment[^"]*"[^>]*>[\s\S]*?<a[^>]+href=(["\'])([^"\']+)\1[\s\S]*?<\/figure>/is',
            function (array $matches): string {
                $url = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (self::looksLikeVideoUrl($url, '')) {
                    return self::inlineVideoHtml($url);
                }

                return $matches[0];
            },
            $html
        ) ?? $html;

        return $html;
    }

    public static function normalizeInlineVideoTags(string $html): string
    {
        return preg_replace_callback(
            '/<video\b([^>]*)>/i',
            function (array $matches): string {
                if (! preg_match('/\bsrc=(["\'])(.*?)\1/i', $matches[1], $srcMatch)) {
                    return $matches[0];
                }

                $url = self::normalizeMediaUrl(html_entity_decode($srcMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($url === '') {
                    return $matches[0];
                }

                $attrs = preg_replace('/\bsrc=(["\']).*?\1/i', '', $matches[1]) ?? $matches[1];
                $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

                if (! str_contains(strtolower($attrs), 'controls')) {
                    $attrs .= ' controls';
                }
                if (! str_contains(strtolower($attrs), 'playsinline')) {
                    $attrs .= ' playsinline';
                }
                if (! str_contains(strtolower($attrs), 'preload=')) {
                    $attrs .= ' preload="metadata"';
                }

                return '<video'.$attrs.' src="'.$safeUrl.'">';
            },
            $html
        ) ?? $html;
    }

    public static function cleanPublicVideoFigures(string $html): string
    {
        return preg_replace_callback(
            '/<figure\b[^>]*class="[^"]*\bblog-inline-video\b[^"]*"[^>]*>[\s\S]*?<\/figure>/is',
            function (array $matches): string {
                if (preg_match('/<video\b[^>]*\bsrc=(["\'])(.*?)\1/is', $matches[0], $videoMatch)) {
                    return self::inlineVideoHtml(html_entity_decode($videoMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }
                if (preg_match('/\bdata-video-src=(["\'])(.*?)\1/i', $matches[0], $dataMatch)) {
                    return self::inlineVideoHtml(html_entity_decode($dataMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }

                return $matches[0];
            },
            $html
        ) ?? $html;
    }

    public static function stripVideoFilenameLinks(string $html): string
    {
        $html = preg_replace_callback(
            '/<figure\b[^>]*>[\s\S]*?<\/figure>/is',
            function (array $matches): string {
                if (preg_match('/<video\b/i', $matches[0])) {
                    if (preg_match('/<video\b[^>]*\bsrc=(["\'])(.*?)\1/is', $matches[0], $videoMatch)) {
                        return self::inlineVideoHtml(html_entity_decode($videoMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    }

                    return $matches[0];
                }

                if (preg_match('/\bhref=(["\'])([^"\']+)\1/i', $matches[0], $hrefMatch)
                    && self::looksLikeVideoUrl(html_entity_decode($hrefMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'), '')) {
                    return self::inlineVideoHtml(html_entity_decode($hrefMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }

                return $matches[0];
            },
            $html
        ) ?? $html;

        return preg_replace_callback(
            '/<a\b[^>]*\bhref=(["\'])([^"\']+\.(?:mp4|webm|ogg)[^"\']*)\1[^>]*>[\s\S]*?<\/a>/i',
            fn (array $matches): string => self::inlineVideoHtml(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            $html
        ) ?? $html;
    }

    public static function stripAttachmentCaptions(string $html): string
    {
        $html = preg_replace('/<figcaption\b[^>]*class="[^"]*attachment__caption[^"]*"[^>]*>[\s\S]*?<\/figcaption>/i', '', $html) ?? $html;
        $html = preg_replace('/<figcaption\b[^>]*class="[^"]*blog-media-label[^"]*"[^>]*>[\s\S]*?<\/figcaption>/i', '', $html) ?? $html;
        $html = preg_replace('/<span\b[^>]*class="[^"]*attachment__metadata[^"]*"[^>]*>[\s\S]*?<\/span>/i', '', $html) ?? $html;

        return $html;
    }

    protected static function looksLikeVideoUrl(string $url, string $contentType): bool
    {
        if ($contentType !== '' && str_starts_with($contentType, 'video/')) {
            return true;
        }

        return (bool) preg_match('/\.(mp4|webm|ogg)(\?|#|$)/i', $url);
    }

    protected static function normalizeMediaUrl(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '//')) {
            $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

            return $scheme.':'.$url;
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $base = rtrim((string) config('app.url'), '/');

        if (str_starts_with($url, '/')) {
            return $base.$url;
        }

        return $base.'/storage/'.ltrim($url, '/');
    }

    protected static function inlineVideoHtml(string $url): string
    {
        $normalized = self::normalizeMediaUrl($url);
        if ($normalized === '') {
            return '';
        }

        $safeUrl = htmlspecialchars($normalized, ENT_QUOTES, 'UTF-8');

        return '<figure class="blog-inline-video"><video controls playsinline preload="metadata" src="'.$safeUrl.'"></video></figure>';
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
