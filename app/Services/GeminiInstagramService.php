<?php

namespace App\Services;

use App\Support\AdminSettings;

class GeminiInstagramService
{
    public const DEFAULT_CONTENT_IDEA = 'Viết một đoạn ins ngắn giới thiệu về cửa hàng';

    public ?string $lastError = null;

    public bool $usedDefaultCaption = false;

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
        $this->lastError = null;
        $couponCodes = array_values(array_filter(array_map('trim', $couponCodes)));

        if (! AdminSettings::hasGeminiApiKey()) {
            return $this->useDefaultCaption($affLink, $couponCodes);
        }

        try {
            $caption = $this->attemptSingleAiCaption($brandDomain, $contentIdea, $affLink, $couponCodes);
            if ($caption !== null) {
                return $caption;
            }
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
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

        return $this->truncateCaption(trim(implode("\n", $lines)));
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
    protected function attemptSingleAiCaption(
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

        $affUrl = filled($affLink) ? $this->normalizeAffLink($affLink) : null;
        if (filled($affLink) && $affUrl === null) {
            $affUrl = $this->resolveAffLinkForDefault($affLink);
        }

        $prompt = $this->buildSingleCallPrompt($brandContext, $contentIdea, $affUrl, $couponCodes);

        $gemini = app(GeminiBlogService::class);
        $text = $gemini->generatePlainText($prompt, [
            'maxOutputTokens' => 2048,
            'temperature' => 0.55,
        ]);

        if ($text === null) {
            $this->lastError = $gemini->lastError ?? 'AI không trả về caption.';

            return null;
        }

        $caption = $this->finalizeAiCaption($text, $affUrl, $couponCodes);

        if (! $this->isAiCaptionUsable($caption)) {
            $this->lastError = 'AI trả về caption quá ngắn hoặc không hợp lệ.';

            return null;
        }

        return $this->truncateCaption($caption);
    }

    /**
     * @param  array{label: string, host: ?string}  $brandContext
     * @param  array<int, string>  $couponCodes
     */
    protected function buildSingleCallPrompt(
        array $brandContext,
        string $contentIdea,
        ?string $affUrl,
        array $couponCodes,
    ): string {
        $brandLabel = $brandContext['label'] ?? '';
        $host = $brandContext['host'] ?? null;

        $brandLine = $brandLabel !== ''
            ? "Brand: {$brandLabel}".($host ? " ({$host})" : '')
            : 'Brand: infer from the content idea.';

        $affInstruction = $affUrl !== null
            ? "\n6) Exact line: 🛒 Buy now: {$affUrl}"
            : "\n6) Skip the Buy now line — no affiliate link.";

        $couponInstruction = $couponCodes !== []
            ? "\n7) Exact line: ".(count($couponCodes) === 1 ? '🎟️ Coupon code: ' : '🎟️ Coupon codes: ')
                .implode(', ', $couponCodes)
            : "\n7) Skip the coupon line — no coupon codes.";

        $ideaBlock = mb_substr($contentIdea, 0, 1500);

        return <<<PROMPT
Write ONE complete Instagram promotional caption in English — a single post ready to publish.

{$brandLine}
Content idea (follow closely):
{$ideaBlock}

=== REQUIRED STRUCTURE (blank line between each section) ===
1) Title: one line wrapped with ✨ at start and end, e.g. "✨ Your headline here! ✨"
2) Blank line
3) Body:
   - Opening line: emoji + 1 hook sentence
   - Blank line
   - 3–5 bullet lines: each starts with emoji + 1 full benefit sentence (from the idea)
   - Blank line
   - Closing: 1–2 complete sentences (soft CTA)
4) Blank line
5) Hashtags: 10–15 relevant hashtags on ONE line (each starts with #){$affInstruction}{$couponInstruction}

Rules:
- English only for title, body, and hashtags.
- Use the EXACT affiliate URL and coupon text provided above — do not change them.
- Plain text with line breaks only — no markdown, no labels like "TITLE:" or "BODY:".
- Return ONLY the final caption.
PROMPT;
    }

    /**
     * @param  array<int, string>  $couponCodes
     */
    protected function finalizeAiCaption(string $text, ?string $affUrl, array $couponCodes): string
    {
        $text = trim($text);
        $text = preg_replace('/^```[a-z]*\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/i', '', $text) ?? $text;
        $text = preg_replace('/^(CAPTION|POST):\s*/im', '', $text) ?? $text;
        $text = $this->stripAffAndCouponLines($text);
        $text = rtrim($text);

        if ($affUrl !== null) {
            $text .= "\n\n🛒 Buy now: ".$affUrl;
        }

        if ($couponCodes !== []) {
            $text .= "\n\n".(count($couponCodes) === 1 ? '🎟️ Coupon code: ' : '🎟️ Coupon codes: ')
                .implode(', ', $couponCodes);
        }

        return trim($text);
    }

    protected function stripAffAndCouponLines(string $text): string
    {
        $text = preg_replace('/^🛒\s*Buy now:.*$/im', '', $text) ?? $text;
        $text = preg_replace('/^🎟️\s*Coupon code(s)?:.*$/im', '', $text) ?? $text;
        $text = preg_replace('/\bBuy now:.*$/im', '', $text) ?? $text;
        $text = preg_replace('/\bCoupon code(s)?:.*$/im', '', $text) ?? $text;

        return trim($text);
    }

    protected function isAiCaptionUsable(string $caption): bool
    {
        $caption = trim($caption);

        return $caption !== '' && mb_strlen($caption) >= 80;
    }

    protected function truncateCaption(string $caption): string
    {
        $caption = trim($caption);

        if (mb_strlen($caption) > 2200) {
            return mb_substr($caption, 0, 2197).'...';
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
            return null;
        }

        return $input;
    }
}
