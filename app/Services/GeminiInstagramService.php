<?php

namespace App\Services;

class GeminiInstagramService
{
    public ?string $lastError = null;

    /**
     * @param  array<int, string>  $couponCodes
     */
    public function generateCaption(
        ?string $brandDomain = null,
        ?string $contentIdea = null,
        ?string $affLink = null,
        array $couponCodes = [],
    ): ?string {
        $gemini = app(GeminiBlogService::class);

        $brandSection = '';
        if (filled($brandDomain)) {
            $host = GeminiBlogService::normalizeDomain($brandDomain);
            if ($host === null) {
                $this->lastError = 'Domain brand không hợp lệ.';

                return null;
            }
            $brandLabel = GeminiBlogService::guessBrandNameFromDomain($host);
            $brandSection = "Brand: {$brandLabel} ({$host})\n";
        }

        $ideaSection = filled($contentIdea)
            ? "Content direction:\n".trim($contentIdea)."\n"
            : '';

        $affSection = filled($affLink)
            ? "Promo / affiliate link (mention as «link in bio» — do NOT paste raw URL):\n".trim($affLink)."\n"
            : '';

        $couponCodes = array_values(array_filter(array_map('trim', $couponCodes)));
        $couponSection = $couponCodes !== []
            ? 'Coupon codes to highlight: '.implode(', ', $couponCodes)."\n"
            : '';

        $prompt = <<<PROMPT
You are a professional Instagram copywriter for promotional / deal posts.

Write ONE Instagram caption in English:
- Max 2,000 characters total (including hashtags).
- Engaging opening line, short paragraphs or line breaks, clear CTA.
- Include 8–15 relevant hashtags at the end on separate lines or grouped.
- Use emojis sparingly (2–5 max).
- Plain text only — no HTML, no markdown.
- If coupon codes are provided, highlight them clearly.
- If an affiliate link is provided, say "link in bio" instead of pasting the URL.

{$brandSection}{$ideaSection}{$affSection}{$couponSection}
Return ONLY the caption text, nothing else.
PROMPT;

        $text = $gemini->generatePlainText($prompt, [
            'maxOutputTokens' => 1024,
            'temperature' => 0.85,
        ]);

        if ($text === null) {
            $this->lastError = $gemini->lastError ?? 'Không thể tạo caption từ AI.';

            return null;
        }

        $caption = trim($text);
        if ($caption === '') {
            $this->lastError = 'AI trả về caption rỗng.';

            return null;
        }

        if (mb_strlen($caption) > 2200) {
            $caption = mb_substr($caption, 0, 2197).'...';
        }

        return $caption;
    }
}
