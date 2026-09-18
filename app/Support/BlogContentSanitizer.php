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
        $html = self::normalizeInlineVideos($html);

        return trim($html);
    }

    public static function normalizeInlineVideos(string $html): string
    {
        $html = preg_replace_callback(
            '/<figure\b[^>]*class="[^"]*attachment[^"]*"[^>]*>([\s\S]*?)<\/figure>/is',
            function (array $matches): string {
                $inner = $matches[1];
                $href = self::extractVideoHrefFromAttachmentHtml($matches[0], $inner);

                if ($href === null) {
                    return $matches[0];
                }

                return self::buildInlineVideoHtml($href);
            },
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '/<a\b[^>]*href=(["\'])([^"\']+)\1[^>]*>.*?<\/a>/is',
            function (array $matches): string {
                if (! self::isVideoUrl($matches[2])) {
                    return $matches[0];
                }

                return self::buildInlineVideoHtml($matches[2]);
            },
            $html
        ) ?? $html;

        return preg_replace_callback('/<video\b([^>]*)>/i', function (array $matches): string {
            $attrs = $matches[1];

            if (! preg_match('/\bcontrols\b/i', $attrs)) {
                $attrs .= ' controls';
            }

            if (! preg_match('/\bplaysinline\b/i', $attrs)) {
                $attrs .= ' playsinline';
            }

            if (! preg_match('/\bpreload=/i', $attrs)) {
                $attrs .= ' preload="metadata"';
            }

            if (preg_match('/\bclass=(["\'])(.*?)\1/i', $attrs, $classMatches)) {
                $quote = $classMatches[1];
                $classes = trim($classMatches[2].' blog-inline-video__player');

                $attrs = preg_replace(
                    '/\bclass=(["\']).*?\1/i',
                    'class='.$quote.$classes.$quote,
                    $attrs
                ) ?? $attrs;
            } else {
                $attrs .= ' class="blog-inline-video__player"';
            }

            return '<video'.rtrim($attrs).'>';
        }, $html) ?? $html;
    }

    protected static function isVideoUrl(string $url): bool
    {
        $path = parse_url(html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_PATH) ?? $url;

        return (bool) preg_match('/\.(mp4|webm|ogg|mov|m4v)(?:$|[?#])/i', $path);
    }

    protected static function videoMimeType(string $url): string
    {
        $path = parse_url(html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_PATH) ?? $url;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'mov' => 'video/quicktime',
            'm4v' => 'video/mp4',
            default => 'video/mp4',
        };
    }

    protected static function normalizeMediaUrl(string $url): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $path = preg_replace('#^(?:/)?storage/#', '', $url) ?? $url;
        $path = PublicStorage::normalizePath($path);

        if ($path !== '' && PublicStorage::exists($path)) {
            return PublicStorage::url($path);
        }

        return $url;
    }

    protected static function extractVideoHrefFromAttachmentHtml(string $figureHtml, string $innerHtml): ?string
    {
        if (preg_match('/\bdata-trix-attachment=(["\'])(.*?)\1/is', $figureHtml, $dataMatches)) {
            $payload = html_entity_decode($dataMatches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $data = json_decode($payload, true);

            if (is_array($data)) {
                foreach (['url', 'href'] as $key) {
                    if (! empty($data[$key]) && self::isVideoUrl((string) $data[$key])) {
                        return (string) $data[$key];
                    }
                }
            }
        }

        if (preg_match('/<a\b[^>]*href=(["\'])([^"\']+)\1/i', $innerHtml, $anchorMatches)
            && self::isVideoUrl($anchorMatches[2])) {
            return $anchorMatches[2];
        }

        return null;
    }

    protected static function buildInlineVideoHtml(string $href): string
    {
        $src = htmlspecialchars(self::normalizeMediaUrl($href), ENT_QUOTES, 'UTF-8');
        $mime = htmlspecialchars(self::videoMimeType($href), ENT_QUOTES, 'UTF-8');

        return '<figure class="blog-inline-video">'
            .'<video class="blog-inline-video__player" controls preload="metadata" playsinline src="'.$src.'">'
            .'<source src="'.$src.'" type="'.$mime.'">'
            .'</video>'
            .'</figure>';
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
