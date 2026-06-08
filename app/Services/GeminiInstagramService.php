<?php

namespace App\Services;

use App\Support\AdminSettings;
use Illuminate\Support\Str;

class GeminiInstagramService
{
    public const DEFAULT_CONTENT_IDEA = 'Viết một đoạn ins ngắn giới thiệu về cửa hàng';

    public ?string $lastError = null;

    public bool $usedDefaultCaption = false;

    protected bool $titleFromAi = false;

    protected bool $bodyFromAiOnly = false;

    private const BODY_MIN_CHARS = 280;

    private const BODY_MIN_LINES = 4;

    private const BODY_MIN_EMOJI_BULLETS = 3;

    private const SHORT_INTRO_MIN_CHARS = 120;

    private const SHORT_INTRO_MIN_LINES = 3;

    private const SHORT_INTRO_MIN_EMOJI_BULLETS = 2;

    /**
     * @param  array<int, string>  $couponCodes
     */
    public function generateCaption(
        ?string $brandDomain = null,
        ?string $contentIdea = null,
        ?string $affLink = null,
        array $couponCodes = [],
    ): string {
        $this->usedDefaultCaption = false;
        $this->titleFromAi = false;
        $this->bodyFromAiOnly = false;
        $couponCodes = array_values(array_filter(array_map('trim', $couponCodes)));

        if (! AdminSettings::hasGeminiApiKey()) {
            return $this->useDefaultCaption($affLink, $couponCodes);
        }

        try {
            $caption = $this->attemptAiCaption($brandDomain, $contentIdea, $affLink, $couponCodes);
            if ($caption !== null) {
                return $caption;
            }
        } catch (\Throwable) {
            // Fall through to default caption.
        }

        return $this->useDefaultCaption($affLink, $couponCodes);
    }

    /**
     * @param  array<int, string>  $couponCodes
     */
    public function buildDefaultInstagramCaption(?string $affLink = null, array $couponCodes = []): string
    {
        $lines = [
            'Discover premium products designed to make your everyday life better. From quality craftsmanship to exceptional customer service, we\'re committed to bringing you the best shopping experience possible.',
            '',
            '💎 High-quality products',
            '🚚 Fast shipping',
            '🎁 Exclusive deals & special offers',
            '⭐ Trusted by customers worldwide',
            '',
            'Whether you\'re looking for the latest trends, must-have essentials, or unique finds, we\'ve got something for everyone.',
        ];

        $affUrl = $this->resolveAffLinkForDefault($affLink);
        if ($affUrl !== null) {
            $lines[] = '';
            $lines[] = '🛒 Buy now: '.$affUrl;
        }

        if ($couponCodes !== []) {
            $lines[] = '';
            $lines[] = (count($couponCodes) === 1 ? '🎟️ Coupon code: ' : '🎟️ Coupon codes: ')
                .implode(', ', $couponCodes);
        }

        $caption = trim(implode("\n", $lines));

        if (mb_strlen($caption) > 2200) {
            $caption = mb_substr($caption, 0, 2197).'...';
        }

        return $caption;
    }

    /**
     * @param  array<int, string>  $couponCodes
     */
    protected function useDefaultCaption(?string $affLink, array $couponCodes): string
    {
        $this->usedDefaultCaption = true;
        $this->lastError = null;

        return $this->buildDefaultInstagramCaption($affLink, $couponCodes);
    }

    /**
     * @param  array<int, string>  $couponCodes
     */
    protected function attemptAiCaption(
        ?string $brandDomain,
        ?string $contentIdea,
        ?string $affLink,
        array $couponCodes,
    ): ?string {
        $contentIdea = filled($contentIdea) ? trim((string) $contentIdea) : '';
        if ($contentIdea === '') {
            $contentIdea = self::DEFAULT_CONTENT_IDEA;
        }

        $brandContext = $this->resolveBrandContext($brandDomain);
        if ($brandContext === null) {
            return null;
        }

        $title = $this->generateTitle($brandContext, $contentIdea);
        $body = $this->generateBodyWithRetry($brandContext, $contentIdea, $title ?? '');

        if ($title === null || ! $this->titleFromAi || ! $this->bodyFromAiOnly) {
            return null;
        }

        $affUrl = null;
        if (filled($affLink)) {
            $affUrl = $this->normalizeAffLink($affLink);
            if ($affUrl === null) {
                return null;
            }
        }

        $lines = [$title, '', $body];

        if ($affUrl !== null) {
            $lines[] = '';
            $lines[] = '🛒 Buy now: '.$affUrl;
        }

        if ($couponCodes !== []) {
            $lines[] = '';
            $lines[] = (count($couponCodes) === 1 ? '🎟️ Coupon code: ' : '🎟️ Coupon codes: ')
                .implode(', ', $couponCodes);
        }

        $hashtags = $this->generateHashtags($brandContext, $contentIdea, $title, $body);
        if ($hashtags !== []) {
            $lines[] = '';
            $lines[] = implode(' ', $hashtags);
        }

        $caption = trim(implode("\n", $lines));
        if ($caption === '') {
            return null;
        }

        if (mb_strlen($caption) > 2200) {
            $caption = mb_substr($caption, 0, 2197).'...';
        }

        return $caption;
    }

    protected function resolveAffLinkForDefault(?string $affLink): ?string
    {
        if (! filled($affLink)) {
            return null;
        }

        $normalized = $this->normalizeAffLink($affLink);
        if ($normalized !== null) {
            return $normalized;
        }

        $input = trim((string) $affLink);
        if ($input === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $input)) {
            $input = 'https://'.$input;
        }

        return filter_var($input, FILTER_VALIDATE_URL) !== false ? $input : null;
    }

    /**
     * @return array{label: string, host: ?string}|null
     */
    protected function resolveBrandContext(?string $brandDomain): ?array
    {
        if (! filled($brandDomain)) {
            return ['label' => '', 'host' => null];
        }

        $host = GeminiBlogService::normalizeDomain($brandDomain);
        if ($host === null) {
            $this->lastError = 'Domain brand không hợp lệ.';

            return null;
        }

        return [
            'label' => $this->formatBrandLabel($host),
            'host' => $host,
        ];
    }

    protected function formatBrandLabel(string $host): string
    {
        $label = GeminiBlogService::guessBrandNameFromDomain($host);

        if (preg_match('/^hotelcollection$/i', str_replace([' ', '-', '_'], '', $label))) {
            return 'Hotel Collection';
        }

        return $label;
    }

    /**
     * @param  array{label: string, host: ?string}|null  $brandContext
     */
    protected function generateTitle(?array $brandContext, string $contentIdea): ?string
    {
        $brandLabel = $brandContext['label'] ?? '';
        $host = $brandContext['host'] ?? null;

        if ($brandLabel === '' && $this->isDefaultShortIntro($contentIdea)) {
            $this->titleFromAi = false;

            return '✨ Discover something special! ✨';
        }

        $gemini = app(GeminiBlogService::class);

        $brandLine = $brandLabel !== ''
            ? "Brand: {$brandLabel}".($host ? " ({$host})" : '')
            : 'Brand: infer from content idea.';

        $ideaPreview = mb_substr($contentIdea !== '' ? $contentIdea : "Promo for {$brandLabel}", 0, 600);

        $prompt = <<<PROMPT
Write ONE Instagram post title (headline only).

Rules:
- Wrap with ✨ at start and end, e.g. "✨ Transform your home into a luxury retreat! ✨"
- English only, max 120 characters inside the emojis.
- Based on the brand and content idea below.
- Return ONLY the title line — no body, hashtags, or links.

{$brandLine}
Content idea:
{$ideaPreview}
PROMPT;

        $text = $gemini->generatePlainText($prompt, [
            'maxOutputTokens' => 256,
            'temperature' => 0.6,
        ]);

        if ($text !== null) {
            $title = $this->cleanTitleLine($text);
            if ($title !== '') {
                if (! str_starts_with($title, '✨')) {
                    $title = '✨ '.$title;
                }
                if (! str_ends_with($title, '✨')) {
                    $title = rtrim($title, '! .').' ✨';
                }

                $this->titleFromAi = true;

                return $title;
            }
        }

        if ($brandLabel !== '') {
            $this->titleFromAi = false;

            return "✨ Discover {$brandLabel}! ✨";
        }

        $this->titleFromAi = false;
        $this->lastError = $gemini->lastError ?? 'Không thể tạo tiêu đề từ AI.';

        return null;
    }

    /**
     * @param  array{label: string, host: ?string}|null  $brandContext
     */
    protected function generateBodyWithRetry(?array $brandContext, string $contentIdea, string $title): string
    {
        $this->bodyFromAiOnly = false;
        $lastAttempt = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $body = $this->generateBody($brandContext, $contentIdea, $title, $attempt, $lastAttempt);

            if ($body !== null && trim($body) !== '' && $this->isBodySufficient($body, $contentIdea)) {
                $this->bodyFromAiOnly = true;

                return trim($body);
            }

            if ($body !== null && trim($body) !== '') {
                $lastAttempt = trim($body);
            }
        }

        if ($lastAttempt !== null && $lastAttempt !== '') {
            $enhanced = $this->enhanceBodyStructure($lastAttempt, $brandContext, $contentIdea);
            if ($enhanced !== '') {
                $this->lastError = null;

                return $enhanced;
            }
        }

        $this->lastError = null;

        return $this->buildGuaranteedBody($brandContext, $contentIdea);
    }

    /**
     * @param  array{label: string, host: ?string}|null  $brandContext
     */
    protected function generateBody(
        ?array $brandContext,
        string $contentIdea,
        string $title,
        int $attempt,
        ?string $previousAttempt,
    ): ?string {
        $brandLabel = $brandContext['label'] ?? '';
        $host = $brandContext['host'] ?? null;
        $gemini = app(GeminiBlogService::class);

        $brandLine = $brandLabel !== ''
            ? "Brand name: {$brandLabel}".($host ? " ({$host})" : '')
            : 'Brand: infer from content idea.';

        $shortIntro = $this->isDefaultShortIntro($contentIdea);
        $ideaBlock = $shortIntro
            ? 'Write a SHORT Instagram intro about the store/brand (welcome + 2–3 benefits + soft closing).'
            : ($contentIdea !== ''
                ? $contentIdea
                : "Write a promotional post for {$brandLabel}.");

        $points = $shortIntro ? [] : $this->extractIdeaPoints($contentIdea);
        $pointsBlock = '';
        if ($points !== []) {
            $numbered = [];
            foreach ($points as $i => $point) {
                $numbered[] = ($i + 1).'. '.$point;
            }
            $pointsBlock = "MANDATORY — turn EACH point below into one emoji bullet line (do not skip any):\n"
                .implode("\n", $numbered);
        }

        $minLines = $this->bodyMinLines($contentIdea);
        $minChars = $this->bodyMinChars($contentIdea);

        $retryNote = $attempt > 1
            ? "\n\nRETRY #{$attempt}: Your previous output was TOO SHORT or incomplete. "
                ."Write the FULL body: opening hook + ALL bullet lines + closing paragraph. "
                ."Minimum {$minLines} lines and {$minChars} characters.\n"
                .'Previous incomplete output:\n'.mb_substr((string) $previousAttempt, 0, 300)
            : '';

        $structureBlock = $shortIntro
            ? <<<STRUCTURE
=== REQUIRED STRUCTURE (short store intro) ===
1) Opening line: emoji + 1 welcome sentence
2) Blank line
3) 2–3 bullet lines: emoji + 1 sentence each (quality, deals, service)
4) Blank line
5) Closing: 1 complete sentence (soft invite to shop)

Rules:
- English only.
- Minimum {$minLines} non-empty lines, at least {$minChars} characters total.
- Keep it concise but complete — every sentence must end properly.
STRUCTURE
            : <<<STRUCTURE
=== REQUIRED STRUCTURE (all sections required) ===
1) Opening line: emoji + 1 complete sentence (hook from the idea)
2) Blank line
3) 3–5 bullet lines: each starts with emoji + 1 full sentence benefit (from idea points above)
4) Blank line
5) Closing paragraph: 1–2 complete sentences (summary / soft CTA from the idea)

Rules:
- English only.
- Minimum {$minLines} non-empty lines, at least {$minChars} characters total.
- Finish every sentence — do not cut off mid-sentence.
STRUCTURE;

        $prompt = <<<PROMPT
Write ONLY the BODY text for an Instagram promotional post (not the title).

=== PRIMARY SOURCE — follow every point ===
{$ideaBlock}

{$pointsBlock}

{$brandLine}
Title already written (do NOT repeat): {$title}
{$retryNote}

{$structureBlock}
- Do NOT include the title, "Buy now", links, coupon codes, or hashtags.
- Plain text with line breaks only.

Return ONLY the body text.
PROMPT;

        $text = $gemini->generatePlainText($prompt, [
            'maxOutputTokens' => 2048,
            'temperature' => $attempt === 1 ? 0.55 : 0.65,
        ]);

        if ($text === null) {
            return null;
        }

        $body = $this->cleanBodyText($text);

        return $body !== '' ? $body : null;
    }

    protected function isDefaultShortIntro(string $contentIdea): bool
    {
        $contentIdea = trim($contentIdea);

        if ($contentIdea === self::DEFAULT_CONTENT_IDEA) {
            return true;
        }

        return mb_strlen($contentIdea) < 80 && ! str_contains($contentIdea, "\n");
    }

    protected function bodyMinLines(?string $contentIdea = null): int
    {
        return $this->isDefaultShortIntro((string) $contentIdea)
            ? self::SHORT_INTRO_MIN_LINES
            : self::BODY_MIN_LINES;
    }

    protected function bodyMinChars(?string $contentIdea = null): int
    {
        return $this->isDefaultShortIntro((string) $contentIdea)
            ? self::SHORT_INTRO_MIN_CHARS
            : self::BODY_MIN_CHARS;
    }

    /**
     * @param  array{label: string, host: ?string}|null  $brandContext
     */
    protected function fallbackShortIntroBody(?array $brandContext): string
    {
        $brand = filled($brandContext['label'] ?? '')
            ? (string) $brandContext['label']
            : 'our store';

        return implode("\n", [
            "🏪 Welcome to {$brand}!",
            '',
            '✨ Curated picks you will love',
            '🎁 Great deals every day',
            '💫 Shop with confidence',
            '',
            'Stop by and see what makes us special today.',
        ]);
    }

    /**
     * @param  array{label: string, host: ?string}|null  $brandContext
     */
    protected function buildGuaranteedBody(?array $brandContext, string $contentIdea): string
    {
        if ($this->isDefaultShortIntro($contentIdea)) {
            return $this->fallbackShortIntroBody($brandContext);
        }

        $brand = filled($brandContext['label'] ?? '')
            ? (string) $brandContext['label']
            : 'this brand';
        $points = $this->extractIdeaPoints($contentIdea);
        $emojis = ['🌟', '🎁', '💎', '🔥', '✨', '🛍️'];

        $hook = "✨ Here's why {$brand} is worth your attention!";
        if ($points !== []) {
            $hook = '🌟 '.$this->ensureSentenceEnding($this->translateIdeaSnippet($points[0], $brand));
        }

        $bullets = [];
        foreach (array_slice($points, 0, 5) as $index => $point) {
            $emoji = $emojis[$index % count($emojis)];
            $bullets[] = $emoji.' '.$this->ensureSentenceEnding($this->translateIdeaSnippet($point, $brand));
        }

        $genericBullets = [
            '🎁 Exclusive offers you do not want to miss',
            '💫 Quality picks curated just for you',
            '✨ Shop smarter and save more today',
        ];

        foreach ($genericBullets as $generic) {
            if (count($bullets) >= 3) {
                break;
            }
            $bullets[] = $generic;
        }

        $closing = "Explore {$brand} today and grab the deal that fits you best.";

        return implode("\n", array_merge([$hook, ''], $bullets, ['', $closing]));
    }

    /**
     * @param  array{label: string, host: ?string}|null  $brandContext
     */
    protected function enhanceBodyStructure(string $body, ?array $brandContext, string $contentIdea): string
    {
        $body = trim($body);
        if ($body === '') {
            return '';
        }

        if ($this->isBodySufficient($body, $contentIdea)) {
            return $body;
        }

        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $body) ?: []),
            fn (string $line): bool => $line !== '',
        ));

        $emojiLines = array_values(array_filter(
            $lines,
            fn (string $line): bool => (bool) preg_match('/^[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $line),
        ));

        $points = $this->extractIdeaPoints($contentIdea);
        $emojis = ['🎁', '💎', '🔥', '✨', '🛍️', '💫'];
        $pointIndex = 0;

        while (count($emojiLines) < 3) {
            $point = $points[$pointIndex] ?? null;
            $emoji = $emojis[count($emojiLines) % count($emojis)];
            $emojiLines[] = $point !== null
                ? $emoji.' '.$this->ensureSentenceEnding($this->translateIdeaSnippet($point, $brandContext['label'] ?? 'our store'))
                : match (count($emojiLines)) {
                    0 => '🎁 Exclusive deals waiting for you',
                    1 => '💫 Quality products you can trust',
                    default => '✨ Shop now and save more',
                };
            $pointIndex++;
        }

        $hook = $lines[0] ?? '';
        if (! preg_match('/^[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $hook)) {
            $brand = filled($brandContext['label'] ?? '') ? (string) $brandContext['label'] : 'our store';
            $hook = '🌟 Discover the best of '.$brand.' today!';
        }

        $lastLine = $lines[array_key_last($lines)] ?? '';
        $closing = (mb_strlen($lastLine) >= 30 && (str_ends_with($lastLine, '.') || str_ends_with($lastLine, '!')))
            ? $lastLine
            : 'Start shopping today and enjoy every benefit above.';

        $bulletLines = array_slice($emojiLines, 0, 5);
        if (in_array($hook, $bulletLines, true)) {
            $bulletLines = array_values(array_filter($bulletLines, fn (string $line): bool => $line !== $hook));
        }

        return implode("\n", array_merge([$hook, ''], $bulletLines, ['', $closing]));
    }

    protected function translateIdeaSnippet(string $point, string $brand): string
    {
        $point = trim($point);
        if ($point === '') {
            return "Something special from {$brand}";
        }

        if (preg_match('/[\x{00C0}-\x{1EF9}]/u', $point)) {
            return "Discover more about {$brand} — ".$point;
        }

        return $point;
    }

    protected function ensureSentenceEnding(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return $text;
        }

        if (preg_match('/[.!?]$/u', $text)) {
            return $text;
        }

        return $text.'.';
    }

    protected function isBodySufficient(string $body, ?string $contentIdea = null): bool
    {
        if (mb_strlen($body) < $this->bodyMinChars($contentIdea)) {
            return false;
        }

        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $body) ?: []),
            fn (string $line): bool => $line !== '',
        ));

        if (count($lines) < $this->bodyMinLines($contentIdea)) {
            return false;
        }

        $emojiBullets = 0;
        foreach ($lines as $line) {
            if (preg_match('/^[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $line)) {
                $emojiBullets++;
            }
        }

        $minBullets = $this->isDefaultShortIntro((string) $contentIdea)
            ? self::SHORT_INTRO_MIN_EMOJI_BULLETS
            : self::BODY_MIN_EMOJI_BULLETS;

        if ($emojiBullets < $minBullets) {
            return false;
        }

        if (preg_match('/\b(your ultimate|and more|stay tuned)\s*$/iu', $body)) {
            return false;
        }

        $lastLine = $lines[array_key_last($lines)] ?? '';

        return mb_strlen($lastLine) >= 40 || str_ends_with($lastLine, '.') || str_ends_with($lastLine, '!');
    }

    /**
     * @return array<int, string>
     */
    protected function extractIdeaPoints(string $contentIdea): array
    {
        $contentIdea = trim($contentIdea);
        if ($contentIdea === '') {
            return [];
        }

        $points = [];
        $lines = preg_split('/\r\n|\r|\n/', $contentIdea) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            $line = preg_replace('/^[-*•]\s*/u', '', $line) ?? $line;
            if (mb_strlen($line) >= 3) {
                $points[] = $line;
            }
        }

        if (count($points) <= 1) {
            $sentences = preg_split('/(?<=[.!?])\s+/u', $contentIdea) ?: [];
            foreach ($sentences as $sentence) {
                $sentence = trim($sentence);
                if (mb_strlen($sentence) >= 5) {
                    $points[] = $sentence;
                }
            }
        }

        if ($points === [] && mb_strlen($contentIdea) >= 3) {
            $points[] = $contentIdea;
        }

        return array_values(array_unique($points));
    }

    protected function cleanTitleLine(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^TITLE:\s*/i', '', $text) ?? $text;
        $text = preg_replace('/^["\']|["\']$/u', '', $text) ?? $text;
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

        return trim((string) ($lines[0] ?? ''));
    }

    protected function cleanBodyText(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^BODY:\s*/im', '', $text) ?? $text;
        $text = preg_replace('/^#+\s*/m', '', $text) ?? $text;
        $text = preg_replace('/🛒\s*Buy now:.*$/im', '', $text) ?? $text;
        $text = preg_replace('/🎟️\s*Coupon code(s)?:.*$/im', '', $text) ?? $text;
        $text = preg_replace('/\bBuy now:.*$/im', '', $text) ?? $text;
        $text = preg_replace('/\bCoupon code(s)?:.*$/im', '', $text) ?? $text;
        $text = preg_replace('/^TITLE:.*$/im', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * @param  array{label: string, host: ?string}|null  $brandContext
     * @return array<int, string>
     */
    protected function generateHashtags(
        ?array $brandContext,
        string $contentIdea,
        string $title,
        string $body,
    ): array {
        $brandLabel = $brandContext['label'] ?? '';
        $host = $brandContext['host'] ?? null;

        $gemini = app(GeminiBlogService::class);

        $brandLine = $brandLabel !== ''
            ? "Brand: {$brandLabel}".($host ? " ({$host})" : '')
            : 'Brand: (not specified)';

        $context = mb_substr(trim("Idea:\n{$contentIdea}\n\nPost:\n{$title}\n{$body}"), 0, 1000);

        $prompt = <<<PROMPT
Generate Instagram hashtags for a promotional / deal post.

Rules:
- Return 10–15 hashtags on ONE line, separated by spaces.
- Each hashtag must start with #.
- English only; relevant to brand, products, and benefits in the post.
- No emojis, no other text — ONLY hashtags.

{$brandLine}
Post context:
{$context}

Return ONLY the hashtag line.
PROMPT;

        $text = $gemini->generatePlainText($prompt, [
            'maxOutputTokens' => 384,
            'temperature' => 0.5,
        ]);

        $tags = $text !== null ? $this->parseHashtags($text) : [];

        if ($tags !== []) {
            return $tags;
        }

        return $this->fallbackHashtags($brandLabel !== '' ? $brandLabel : null);
    }

    /**
     * @return array<int, string>
     */
    protected function parseHashtags(string $text): array
    {
        preg_match_all('/#([A-Za-z0-9_]+)/u', $text, $matches);
        $tags = [];

        foreach ($matches[1] as $word) {
            $word = preg_replace('/[^A-Za-z0-9_]/', '', (string) $word) ?? '';
            if ($word === '') {
                continue;
            }

            $normalized = '#'.$word;
            if (! in_array($normalized, $tags, true)) {
                $tags[] = $normalized;
            }
        }

        return array_slice($tags, 0, 15);
    }

    /**
     * @return array<int, string>
     */
    protected function fallbackHashtags(?string $brandLabel): array
    {
        $tags = ['#deals', '#coupon', '#shopping', '#savemoney', '#promo', '#discount', '#offers', '#lifestyle'];

        if (filled($brandLabel)) {
            $brandTag = '#'.Str::camel(Str::slug($brandLabel, ''));
            if (strlen($brandTag) > 1) {
                array_unshift($tags, $brandTag);
            }
        }

        return array_slice(array_values(array_unique($tags)), 0, 15);
    }

    protected function normalizeAffLink(?string $input): ?string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $input)) {
            $input = 'https://'.$input;
        }

        if (filter_var($input, FILTER_VALIDATE_URL) === false) {
            $this->lastError = 'Link AFF không hợp lệ.';

            return null;
        }

        return $input;
    }
}
