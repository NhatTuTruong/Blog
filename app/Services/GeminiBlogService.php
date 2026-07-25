<?php

namespace App\Services;

use App\Support\AffiliateContentGuidelines;
use App\Support\GeminiKeyScope;
use App\Support\GeminiSettings;
use App\Support\IntegrationSettingsStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeminiBlogService
{
    public ?string $lastError = null;

    /** Timeout tối thiểu khi gọi API (bài dài dễ vượt 8–30s). */
    protected const MIN_TIMEOUT_SECONDS = 60;

    public function generateBlog(string $category, string $variant): ?array
    {
        $ownerUserId = IntegrationSettingsStore::fallbackAdminUserId();
        $timeout = $this->resolveTimeoutSeconds($ownerUserId);

        $topicPrompt = match ($variant) {
            'best' => "Write a \"Best {$category}\" style article (e.g. best products or options in {$category}).",
            'guide' => "Write a buying guide for the {$category} category.",
            'comparison' => "Write a comparison article between common options in {$category}.",
            default => "Write a high-quality blog article about {$category}.",
        };

        $prompt = <<<PROMPT
You are a professional English SEO copywriter.

Requirements:
- Language: English, SEO-friendly, about 1,000–1,500 words.
- Structure: one <h1>, main sections with <h2>, optional <h3>.
- Helpful editorial content only — no coupon codes, affiliate CTAs, or store promotions.
- Return complete HTML: <h1>, <p>, <ul>/<ol>, <h2>, <h3>. Do not wrap in <html>/<body>.

Category: {$category}
Article type: {$variant}
{$topicPrompt}
PROMPT;

        return $this->callGeminiWithFallback($prompt, $timeout, [
            'maxOutputTokens' => 8192,
        ], $ownerUserId, GeminiKeyScope::AUTO_BLOG);
    }

    /**
     * Bài blog quảng cáo / giới thiệu brand theo domain.
     *
     * @param  array<int, string>  $couponCodes
     * @return array{title: string, content: string, featured_image: ?string, domain: string}|null
     */
    public function generateBrandPromoBlog(
        string $domainInput,
        ?string $contentIdea = null,
        ?string $affLink = null,
        array $couponCodes = [],
        ?int $ownerUserId = null,
    ): ?array {
        $host = self::normalizeDomain($domainInput);
        if ($host === null) {
            $this->lastError = 'Domain không hợp lệ. Ví dụ: nike.com hoặc https://www.amazon.com';

            return null;
        }

        $siteUrl = 'https://'.$host;
        $ctaUrl = $siteUrl;

        if (filled($affLink)) {
            $normalizedAffLink = $this->normalizeAffLink($affLink);
            if ($normalizedAffLink === null) {
                return null;
            }
            $ctaUrl = $normalizedAffLink;
        }

        $ownerUserId ??= IntegrationSettingsStore::fallbackAdminUserId();
        $timeout = max(90, $this->resolveTimeoutSeconds($ownerUserId));

        $brandLabel = self::guessBrandNameFromDomain($host);
        $brandLabel = $this->getCorrectBrandName($host, $brandLabel);
        $year = (int) now()->format('Y');
        $brandEsc = htmlspecialchars($brandLabel, ENT_QUOTES, 'UTF-8');

        $detectedLang = $this->detectLanguage($contentIdea ?? '');
        $langConfig = $this->getLanguageInstructions($detectedLang);
        $langLabel = $langConfig['label'];
        $brandLang = $langConfig['h1_format'];
        $brandStructure = $langConfig['structure'];

        $wordCount = $this->extractWordCount($contentIdea ?? '');
        $wordCountInstruction = $wordCount > 0
            ? "Target length: approximately **{$wordCount} words**."
            : 'Target length: approximately **1,200–1,500 words**.';

        $contentIdeaSection = filled($contentIdea)
            ? <<<SECTION


## Content direction (MANDATORY - follow this exactly)
The article MUST cover these specific topics:
{$contentIdea}

IMPORTANT: 
- Cover ALL the topics listed above in detail.
- Use the specific terms and content provided as your source material.
- Do NOT add, invent, or hallucinate additional information not in the topic list.
- Present the information in a well-structured, engaging format.
SECTION
            : '';

        $affSection = $ctaUrl !== $siteUrl
            ? "\n## Affiliate / CTA link (for reference only — do NOT add links in your HTML)\nPromotional URL: {$ctaUrl}\n"
            : "\n## Store URL (for reference only — do NOT add links in your HTML)\n{$siteUrl}\n";

        $couponCodes = array_values(array_filter(array_map('trim', $couponCodes)));
        $couponSection = '';
        if ($couponCodes !== []) {
            $codesList = implode(', ', array_map(
                fn (string $code) => htmlspecialchars($code, ENT_QUOTES, 'UTF-8'),
                $couponCodes
            ));
            $couponSection = <<<COUPONS

## Coupon codes (must include in article)
Include these exact coupon codes prominently in a dedicated `<h2>` section titled like "Coupon Codes" or "How to Save":
{$codesList}
- Present each code clearly (e.g. in `<strong>` or a list).
- Explain briefly how readers can apply each code at checkout on {$brandEsc}.
- Do NOT invent additional codes beyond this list.

COUPONS;
        } else {
            $couponSection = "\nDo not invent specific coupon codes or % discounts. You may say readers can check {$siteUrl} for current offers.\n";
        }

        $affiliateRules = AffiliateContentGuidelines::promptRules();

        $prompt = <<<PROMPT
You are an expert SEO copywriter for **independent affiliate review** blogs.

## Brand to cover (third person — you do NOT represent this brand)
- Domain: {$host}
- Official website: {$siteUrl}
- Brand name to use: {$brandLabel}
{$contentIdeaSection}{$affSection}{$couponSection}
{$affiliateRules}

## Task
Write ONE long-form **affiliate review / buyer's guide** about this brand. Language: **{$langLabel}**. {$wordCountInstruction} Tone: helpful, trustworthy, conversion-oriented but honest — as an outside recommender, not the store itself.

## HTML output rules (CRITICAL)
- Output MUST be complete HTML only: `<h1>`, `<h2>`, `<h3>`, `<p>`, `<ul>`, `<ol>`, `<li>`, `<strong>`, `<em>`.
- Do NOT output Markdown format (no `**bold**`, `*italic*`, `- bullet`, `# heading`, etc.).
- Do NOT wrap in `<html>` or `<body>`.
- ALL formatting must be in HTML tags.
- Do NOT include any `<a>` hyperlinks in your output.
- Brand name "{$brandLabel}" must appear correctly (check spelling!) in `<h1>`, `<h2>`, and throughout the article.

## Required structure
1. `<h1>`: Brand name + clear value proposition ({$brandLang}).
2. Opening: problem readers face + why this brand is worth considering (third person).
3. What is {$brandLabel}: what they sell, positioning, USP.
4. Products / services / highlights (optional `<h3>` subsections).
5. **Pros and cons** — both lists, balanced.
6. Who should consider shopping at {$brandLabel}.
7. Closing summary paragraph.

Structure format: {$brandStructure}.

Do not claim you partnered with the brand unless factual. Do not use "we/us/our" or Vietnamese "Chúng tôi" / store-owner voice anywhere.
PROMPT;

        $result = $this->callGeminiWithFallback($prompt, $timeout, [
            'maxOutputTokens' => 8192,
            'temperature' => 0.85,
        ], $ownerUserId, GeminiKeyScope::AUTO_BLOG);

        if ($result === null) {
            return null;
        }

        $result['content'] = $this->convertMarkdownToHtml($result['content']);
        $result['content'] = $this->formatBrandPromoContent(
            $result['content'],
            $ctaUrl,
            $siteUrl,
            $brandLabel,
        );

        $result['domain'] = $host;

        return $result;
    }

    protected function convertMarkdownToHtml(string $text): string
    {
        $original = $text;

        $text = trim($text);

        $text = preg_replace('/^#{1}\s+(.+)$/m', '<h1>$1</h1>', $text);
        $text = preg_replace('/^#{2}\s+(.+)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^#{3}\s+(.+)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^#{4,6}\s+(.+)$/m', '<h4>$1</h4>', $text);

        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $text);
        $text = preg_replace('/_(.+?)_/s', '<em>$1</em>', $text);

        $text = preg_replace_callback('/^(\s*)- (.+)$/m', function ($matches) {
            return $matches[1].'<li>'.trim($matches[2]).'</li>';
        }, $text);
        $text = preg_replace_callback('/^(\s*)\* (.+)$/m', function ($matches) {
            return $matches[1].'<li>'.trim($matches[2]).'</li>';
        }, $text);
        $text = preg_replace('/(<li>.*<\/li>)\n(?!<li>)/s', "$1</ul>\n\n", $text);
        $text = preg_replace('/(<\/ul>)\n(<li>)/s', "$1\n<ul>$2", $text);
        $text = preg_replace('/(<\/li>)(?!\n(<\/ul>|\s*$))/s', "$1\n", $text);
        if (! str_contains($text, '<ul>') && preg_match_all('/<li>.+?<\/li>/s', $text, $matches)) {
            foreach ($matches[0] as $block) {
                $text = str_replace($block, '<ul>'.$block.'</ul>', $text, $count = 1);
            }
        }

        $text = preg_replace('/^\d+\.\s+(.+)$/m', '<li>$1</li>', $text);
        $text = preg_replace('/(<\/li>)\n(?!<li>)/s', "$1\n", $text);

        $lines = explode("\n", $text);
        $result = [];
        $inUl = false;
        $inOl = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (preg_match('/^<ul>/', $trimmed) || preg_match('/^<li>/', $trimmed)) {
                if (! $inUl) {
                    if ($inOl) {
                        $result[] = '</ol>';
                        $inOl = false;
                    }
                    $result[] = '<ul>';
                    $inUl = true;
                }
                if (preg_match('/^<li>/', $trimmed)) {
                    $result[] = $trimmed;
                }
            } else {
                if ($inUl) {
                    $result[] = '</ul>';
                    $inUl = false;
                } elseif ($inOl) {
                    $result[] = '</ol>';
                    $inOl = false;
                }
                if ($trimmed !== '') {
                    $result[] = $trimmed;
                }
            }
        }
        if ($inUl) {
            $result[] = '</ul>';
        }
        if ($inOl) {
            $result[] = '</ol>';
        }
        $text = implode("\n", $result);

        $text = preg_replace('/```[\w]*\n([\s\S]*?)```/m', '<pre><code>$1</code></pre>', $text);
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

        $text = preg_replace('/^---+\s*$/m', '<hr>', $text);

        $text = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $text);

        $paragraphs = preg_split('/\n{2,}/', $text);
        $text = '';
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') {
                continue;
            }
            if (! preg_match('/^<(h[1-6]|ul|ol|li|pre|hr|blockquote)/', $para)) {
                $para = '<p>'.preg_replace('/\n/', ' ', $para).'</p>';
            }
            $text .= $para."\n\n";
        }
        $text = preg_replace('/<p>\s*<\/p>/', '', $text);

        $text = preg_replace('/(<\/h[1-6]>)(\S)/', "$1\n$2", $text);
        $text = preg_replace('/(<\/p>)(\S)/', "$1\n$2", $text);
        $text = preg_replace('/(\S)(<h[1-6]>)/', "$1\n$2", $text);
        $text = preg_replace('/(\S)(<ul>)/', "$1\n$2", $text);
        $text = preg_replace('/(\S)(<ol>)/', "$1\n$2", $text);
        $text = preg_replace('/(<\/ul>)(\S)/', "$1\n$2", $text);
        $text = preg_replace('/(<\/ol>)(\S)/', "$1\n$2", $text);

        if ($original !== $text && str_contains($original, '**')) {
            Log::info('GeminiBlogService: đã convert Markdown sang HTML', [
                'original_preview' => Str::limit($original, 100),
                'converted_preview' => Str::limit($text, 100),
            ]);
        }

        return $text;
    }

    protected function formatBrandPromoContent(string $html, string $ctaUrl, string $siteUrl, string $brandLabel): string
    {
        $html = $this->stripInlinePromoLinks($html, $ctaUrl, $siteUrl);
        $html = $this->removeMarkdownArtifacts($html);

        return $this->injectBrandPromoLinkBlocks($html, $ctaUrl, $brandLabel);
    }

    protected function removeMarkdownArtifacts(string $html): string
    {
        $original = $html;

        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
        $html = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $html);
        $html = preg_replace('/_(.+?)_/s', '<em>$1</em>', $html);

        $html = preg_replace('/^#{1}\s+(.+)$/m', '<h1>$1</h1>', $html);
        $html = preg_replace('/^#{2}\s+(.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^#{3}\s+(.+)$/m', '<h3>$1</h3>', $html);

        $html = preg_replace('/```[\w]*\n?([\s\S]*?)```/', '<pre><code>$1</code></pre>', $html);
        $html = preg_replace('/`([^`\n]+)`/', '<code>$1</code>', $html);

        $html = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $html);

        $html = preg_replace('/^[-*+]\s+(?!\w)/m', '<li>', $html);
        $html = preg_replace('/^\d+\.\s+(.+)$/m', '<li>$1</li>', $html);

        $html = preg_replace('/^---+\s*$/m', '<hr>', $html);

        $lines = explode("\n", $html);
        $clean = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (preg_match('/^\*\*[^*]+\*\*$/', $trimmed) && ! preg_match('/<[^>]+>/', $trimmed)) {
                continue;
            }
            if (preg_match('/^\* [^*]+$/', $trimmed) && ! preg_match('/<[^>]+>/', $trimmed)) {
                continue;
            }
            if (preg_match('/^#{1,6}\s/', $trimmed) && ! preg_match('/<h[1-6]/', $trimmed)) {
                continue;
            }
            $clean[] = $line;
        }
        $html = implode("\n", $clean);

        return $html;
    }

    protected function stripInlinePromoLinks(string $html, string $ctaUrl, string $siteUrl): string
    {
        $hosts = array_unique(array_filter([
            parse_url($ctaUrl, PHP_URL_HOST),
            parse_url($siteUrl, PHP_URL_HOST),
        ]));

        return preg_replace_callback(
            '#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is',
            function (array $matches) use ($hosts): string {
                $href = $matches[1];
                foreach ($hosts as $host) {
                    if (is_string($host) && $host !== '' && str_contains($href, $host)) {
                        $text = trim(strip_tags($matches[2]));

                        return $text !== '' ? $text : strip_tags($matches[2]);
                    }
                }

                return $matches[0];
            },
            $html
        ) ?? $html;
    }

    protected function injectBrandPromoLinkBlocks(string $html, string $ctaUrl, string $brandLabel): string
    {
        $ctaUrlEsc = htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8');
        $brandEsc = htmlspecialchars($brandLabel, ENT_QUOTES, 'UTF-8');
        $linkAttrs = 'href="'.$ctaUrlEsc.'" rel="nofollow sponsored" target="_blank"';

        $openBlock = '<p><a '.$linkAttrs.'><strong>Visit '.$brandEsc.' — Shop now</strong></a></p>';
        $closeBlock = '<p><a '.$linkAttrs.'><strong>Get the deal at '.$brandEsc.' →</strong></a></p>';

        if (preg_match('/<\/h1>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
            $position = $match[0][1] + strlen($match[0][0]);
            $html = substr($html, 0, $position)."\n".$openBlock.substr($html, $position);
        } else {
            $html = $openBlock."\n".$html;
        }

        return rtrim($html)."\n".$closeBlock;
    }

    public static function normalizeDomain(string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $input)) {
            $input = 'https://'.$input;
        }

        $host = parse_url($input, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower($host);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        if (! preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/', $host)) {
            return null;
        }

        return $host;
    }

    public static function guessBrandNameFromDomain(string $host): string
    {
        $parts = explode('.', $host);
        $label = $parts[0] ?? $host;
        $label = str_replace(['-', '_'], ' ', $label);

        return Str::title(trim($label));
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
            $this->lastError = 'Link Affiliate không hợp lệ.';

            return null;
        }

        return $input;
    }

    protected function resolveTimeoutSeconds(?int $ownerUserId = null): int
    {
        $configured = (int) IntegrationSettingsStore::for($ownerUserId)->get('gemini_timeout', config('gemini.timeout', 120));

        return max(self::MIN_TIMEOUT_SECONDS, min(600, $configured));
    }

    /**
     * @param  array<string, mixed>  $generationConfigOverrides
     */
    public function generatePlainText(
        string $prompt,
        array $generationConfigOverrides = [],
        ?int $ownerUserId = null,
        string $scope = GeminiKeyScope::INSTAGRAM,
    ): ?string {
        $timeout = max(60, $this->resolveTimeoutSeconds($ownerUserId));

        $result = $this->callGeminiWithFallback($prompt, $timeout, $generationConfigOverrides, $ownerUserId, $scope);

        if (! $result) {
            return null;
        }

        $text = trim((string) ($result['content'] ?? ''));

        return $text !== '' ? $text : null;
    }

    /**
     * @param  array<string, mixed>  $generationConfigOverrides
     */
    protected function callGeminiWithFallback(
        string $prompt,
        int $timeout,
        array $generationConfigOverrides = [],
        ?int $ownerUserId = null,
        string $scope = GeminiKeyScope::AUTO_BLOG,
    ): ?array {
        $apiKeys = GeminiSettings::getApiKeys($scope, $ownerUserId);
        $primaryModel = GeminiSettings::primaryModel($ownerUserId);

        if ($apiKeys === []) {
            $this->lastError = 'Gemini API key cho '.GeminiKeyScope::label($scope).' chưa được cấu hình.';

            return null;
        }

        $errors = [];

        foreach (GeminiSettings::modelsToTry($ownerUserId) as $currentModel) {
            foreach ($apiKeys as $apiKey) {
                $attempt = $this->attemptGeminiCallWithRetries(
                    $apiKey,
                    $currentModel,
                    $prompt,
                    $timeout,
                    $generationConfigOverrides,
                );

                if ($attempt['success']) {
                    $this->lastError = null;

                    if ($currentModel !== $primaryModel) {
                        Log::info('GeminiBlogService: đã chuyển sang model dự phòng', [
                            'from' => $primaryModel,
                            'to' => $currentModel,
                            'scope' => $scope,
                        ]);
                    }

                    return $attempt['result'];
                }

                if (! $this->shouldFallbackToNextModel($attempt['error'], $attempt['retryable'])) {
                    $this->lastError = $attempt['error'];

                    return null;
                }

                $errors[] = "{$currentModel} / ".GeminiKeyScope::label($scope).': '.$attempt['error'];
                $this->lastError = $attempt['error'];
            }
        }

        $this->lastError = 'Gemini API ('.GeminiKeyScope::label($scope).') lỗi. '.implode(' | ', $errors);

        return null;
    }

    protected function shouldFallbackToNextModel(string $error, bool $retryable): bool
    {
        if ($retryable) {
            return true;
        }

        $error = strtolower($error);

        foreach ([
            'api key not valid',
            'invalid api key',
            'permission denied',
            'unauthenticated',
        ] as $pattern) {
            if (str_contains($error, $pattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $generationConfigOverrides
     * @return array{success: bool, result: ?array, error: string, retryable: bool}
     */
    protected function attemptGeminiCallWithRetries(
        string $apiKey,
        string $model,
        string $prompt,
        int $timeout,
        array $generationConfigOverrides = [],
        int $maxAttempts = 3,
    ): array {
        $lastAttempt = [
            'success' => false,
            'result' => null,
            'error' => 'Unknown error',
            'retryable' => false,
        ];

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $lastAttempt = $this->attemptGeminiCall($apiKey, $model, $prompt, $timeout, $generationConfigOverrides);

            if ($lastAttempt['success']) {
                return $lastAttempt;
            }

            if (! $this->shouldRetrySameGeminiCall($lastAttempt['error'], $attempt, $maxAttempts)) {
                return $lastAttempt;
            }

            $delay = min(10, 2 ** $attempt);
            Log::info('GeminiBlogService: thử lại sau lỗi tạm thời', [
                'model' => $model,
                'attempt' => $attempt + 1,
                'delay_seconds' => $delay,
                'error' => $lastAttempt['error'],
            ]);
            sleep($delay);
        }

        return $lastAttempt;
    }

    protected function shouldRetrySameGeminiCall(string $error, int $attempt, int $maxAttempts): bool
    {
        if ($attempt >= $maxAttempts - 1) {
            return false;
        }

        $error = strtolower($error);

        return str_contains($error, '503')
            || str_contains($error, '429')
            || str_contains($error, 'timed out')
            || str_contains($error, 'high demand')
            || str_contains($error, 'resource exhausted');
    }

    /**
     * @param  array<string, mixed>  $generationConfigOverrides
     * @return array{success: bool, result: ?array, error: string, retryable: bool}
     */
    protected function attemptGeminiCall(
        string $apiKey,
        string $model,
        string $prompt,
        int $timeout,
        array $generationConfigOverrides = [],
    ): array {
        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            $model
        );

        $generationConfig = array_merge([
            'temperature' => 0.9,
            'topP' => 0.8,
            'topK' => 40,
        ], $generationConfigOverrides);

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => $generationConfig,
        ];

        $connectTimeout = max(10, (int) config('gemini.connect_timeout', 30));

        try {
            $response = Http::connectTimeout($connectTimeout)
                ->timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post($endpoint.'?key='.urlencode($apiKey), $payload);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'timed out') || str_contains($message, 'cURL error 28')) {
                $error = "Hết thời gian chờ API ({$timeout}s). Vào Cài đặt hệ thống → tăng Timeout (khuyến nghị 120–180 giây) hoặc thử lại.";
            } else {
                $error = $message;
            }

            Log::warning('GeminiBlogService HTTP error', ['error' => $message, 'model' => $model, 'timeout' => $timeout]);

            return [
                'success' => false,
                'result' => null,
                'error' => $error,
                'retryable' => true,
            ];
        }

        if (! $response->successful()) {
            $body = $response->json();
            $msg = (string) data_get($body, 'error.message', $response->body());
            $error = "HTTP {$response->status()}: {$msg}";

            Log::warning('GeminiBlogService API error', ['status' => $response->status(), 'body' => $body]);

            return [
                'success' => false,
                'result' => null,
                'error' => $error,
                'retryable' => $this->shouldRetryGeminiWithNextKey($response->status(), $body),
            ];
        }

        $data = $response->json();

        $blockReason = data_get($data, 'candidates.0.finishReason');
        if (in_array($blockReason, ['SAFETY', 'RECITATION', 'OTHER'], true)) {
            return [
                'success' => false,
                'result' => null,
                'error' => "Response blocked: {$blockReason}",
                'retryable' => false,
            ];
        }

        $parts = data_get($data, 'candidates.0.content.parts', []);
        if (empty($parts)) {
            return [
                'success' => false,
                'result' => null,
                'error' => 'Response không có parts.',
                'retryable' => false,
            ];
        }

        $text = '';
        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }

        if (trim($text) === '') {
            return [
                'success' => false,
                'result' => null,
                'error' => 'Response không có nội dung text.',
                'retryable' => false,
            ];
        }

        $title = null;
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $text, $m)) {
            $title = trim(strip_tags($m[1]));
        }
        if (! $title) {
            $firstLine = strtok($text, "\n");
            $title = Str::limit(trim(strip_tags((string) $firstLine)), 120, '');
        }

        return [
            'success' => true,
            'result' => [
                'title' => $title,
                'content' => $text,
                'featured_image' => null,
            ],
            'error' => '',
            'retryable' => false,
        ];
    }

    protected function shouldRetryGeminiWithNextKey(int $status, mixed $body): bool
    {
        if (in_array($status, [401, 403, 429, 500, 503], true)) {
            return true;
        }

        $message = strtolower((string) data_get($body, 'error.message', ''));
        $errorStatus = strtolower((string) data_get($body, 'error.status', ''));

        $patterns = [
            'quota',
            'rate limit',
            'resource exhausted',
            'exceeded',
            'billing',
            'api key not valid',
            'permission denied',
            'invalid api key',
            'unauthenticated',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($message, $pattern) || str_contains($errorStatus, $pattern)) {
                return true;
            }
        }

        return $status === 400 && str_contains($message, 'api key');
    }

    /**
     * Extract word count from content idea if specified (e.g., "viết 2000 từ", "2000 words").
     */
    protected function extractWordCount(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        $patterns = [
            '/(\d[\d,]*)\s*(?:từ|words?|word|từ\s)/ui',
            '/(?:viết|khoảng|about|approximately)\s*(\d[\d,]*)\s*(?:từ|words?)/ui',
            '/(\d[\d,]*)\s*(?:từ|words?)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $number = (int) str_replace([','], '', $matches[1]);
                if ($number >= 100 && $number <= 10000) {
                    return $number;
                }
            }
        }

        return 0;
    }

    /**
     * Detect language from content idea string.
     * Priority: 1. Explicit language mention (e.g., "viết tiếng Thái"), 2. Vietnamese characters, 3. Default 'en'
     */
    protected function detectLanguage(string $text): string
    {
        if ($text === '') {
            return 'en';
        }

        $explicitLangMap = [
            // Asian
            'tiếng việt' => 'vi', 'tieng viet' => 'vi', 'vietnamese' => 'vi',
            'tiếng thái' => 'th', 'tieng thai' => 'th', 'thai' => 'th', 'ภาษาไทย' => 'th',
            'tiếng trung' => 'zh', 'tieng trung' => 'zh', 'chinese' => 'zh', '中文' => 'zh',
            'tiếng nhật' => 'ja', 'tieng nhat' => 'ja', 'japanese' => 'ja', '日本語' => 'ja',
            'tiếng hàn' => 'ko', 'tieng han' => 'ko', 'korean' => 'ko', '한국어' => 'ko',
            'tiếng indonesia' => 'id', 'tieng indonesia' => 'id', 'indonesian' => 'id', 'bahasa indonesia' => 'id',
            'tiếng malaysia' => 'ms', 'tieng malaysia' => 'ms', 'malaysian' => 'ms', 'bahasa melayu' => 'ms',
            'tiếng ấn độ' => 'hi', 'tieng an do' => 'hi', 'hindi' => 'hi',
            'tiếng ả rập' => 'ar', 'tieng a rap' => 'ar', 'arabic' => 'ar', 'العربية' => 'ar',
            'tiếng hồi giáo' => 'ar', 'tieng hoi giao' => 'ar',
            // European
            'tiếng anh' => 'en', 'tieng anh' => 'en', 'english' => 'en',
            'tiếng pháp' => 'fr', 'tieng phap' => 'fr', 'french' => 'fr', 'français' => 'fr',
            'tiếng đức' => 'de', 'tieng duc' => 'de', 'german' => 'de', 'deutsch' => 'de',
            'tiếng tây ban nha' => 'es', 'tieng tay ban nha' => 'es', 'spanish' => 'es', 'español' => 'es',
            'tiếng ý' => 'it', 'tieng i' => 'it', 'italian' => 'it', 'italiano' => 'it',
            'tiếng bồ đào nha' => 'pt', 'tieng bo dao nha' => 'pt', 'portuguese' => 'pt', 'português' => 'pt',
            'tiếng nga' => 'ru', 'tieng nga' => 'ru', 'russian' => 'ru', 'русский' => 'ru',
            'tiếng hà lan' => 'nl', 'tieng ha lan' => 'nl', 'dutch' => 'nl', 'nederlands' => 'nl',
            'tiếng ba lan' => 'pl', 'tieng ba lan' => 'pl', 'polish' => 'pl', 'polski' => 'pl',
            'tiếng thụy điển' => 'sv', 'tieng thuy dien' => 'sv', 'swedish' => 'sv', 'svenska' => 'sv',
            'tiếng na uy' => 'no', 'tieng na uy' => 'no', 'norwegian' => 'no', 'norsk' => 'no',
            'tiếng đan mạch' => 'da', 'tieng dan mach' => 'da', 'danish' => 'da', 'dansk' => 'da',
            'tiếng phần lan' => 'fi', 'tieng phan lan' => 'fi', 'finnish' => 'fi', 'suomi' => 'fi',
            'tiếng hy lạp' => 'el', 'tieng hy lap' => 'el', 'greek' => 'el', 'ελληνικά' => 'el',
            'tiếng hungary' => 'hu', 'tieng hungary' => 'hu', 'hungarian' => 'hu', 'magyar' => 'hu',
            'tiếng sec' => 'cs', 'tieng sec' => 'cs', 'czech' => 'cs', 'čeština' => 'cs',
            'tiếng romania' => 'ro', 'tieng romania' => 'ro', 'romanian' => 'ro', 'română' => 'ro',
            'tiếng bulgaria' => 'bg', 'tieng bulgaria' => 'bg', 'bulgarian' => 'bg', 'български' => 'bg',
            'tiếng turkey' => 'tr', 'tieng turkey' => 'tr', 'turkish' => 'tr', 'türkçe' => 'tr',
        ];

        $lowerText = mb_strtolower($text);

        foreach ($explicitLangMap as $keyword => $lang) {
            if (str_contains($lowerText, $keyword)) {
                return $lang;
            }
        }

        $vietnameseIndicators = [
            'ạ', 'ả', 'ã', 'ă', 'â', 'ấ', 'ầ', 'ẩ', 'ẫ', 'ậ',
            'ẹ', 'ẻ', 'ẽ', 'ê', 'ế', 'ề', 'ể', 'ễ', 'ệ',
            'ị', 'ỉ', 'ĩ',
            'ọ', 'ỏ', 'õ', 'ô', 'ố', 'ồ', 'ổ', 'ỗ', 'ộ',
            'ơ', 'ớ', 'ờ', 'ở', 'ỡ', 'ợ',
            'ụ', 'ủ', 'ũ', 'ư', 'ứ', 'ừ', 'ử', 'ữ', 'ự',
            'y', 'ỳ', 'ỷ', 'ỹ', 'ỵ',
            'à', 'á', 'ả', 'ã', 'è', 'é', 'ẻ', 'ẽ', 'ì', 'í', 'ỉ', 'ĩ',
            'ò', 'ó', 'ỏ', 'õ', 'ù', 'ú', 'ủ', 'ũ', 'ỳ', 'ý', 'ỷ', 'ỹ',
            'đ',
            'của', 'và', 'là', 'có', 'được', 'trong', 'cho', 'với', 'này', 'các',
        ];

        $vietCount = 0;
        foreach ($vietnameseIndicators as $indicator) {
            if (str_contains($text, $indicator)) {
                $vietCount++;
                if ($vietCount >= 2) {
                    return 'vi';
                }
            }
        }

        $viChars = preg_match_all('/[àáảãạăằắẳẵặâầấẩẫậèéẻẽẹêềếểễệìíỉĩịòóỏõọôồốổỗộơờớởỡợùúủũụưừứửữựỳýỷỹỵđ]/u', $text, $matches);
        if ($viChars > 2) {
            return 'vi';
        }

        return 'en';
    }

    /**
     * Get language label and instructions for prompt.
     */
    protected function getLanguageInstructions(string $lang): array
    {
        $langConfig = [
            // Asian
            'vi' => [
                'label' => 'Vietnamese (Tiếng Việt)',
                'structure' => 'viết các thẻ <h2> bằng tiếng Việt (ví dụ: "Giới thiệu về Nike", "Sản phẩm nổi bật", "Ưu điểm và nhược điểm")',
                'h1_format' => 'viết bằng tiếng Việt',
            ],
            'th' => [
                'label' => 'Thai (ภาษาไทย)',
                'structure' => 'use Thai <h2> titles (e.g., "เกี่ยวกับ Nike", "สินค้ายอดนิยม", "ข้อดีและข้อเสีย")',
                'h1_format' => 'written in Thai',
            ],
            'zh' => [
                'label' => 'Chinese (中文)',
                'structure' => 'use Chinese <h2> titles (e.g., "关于Nike", "热门产品", "优点和缺点")',
                'h1_format' => 'written in Chinese',
            ],
            'ja' => [
                'label' => 'Japanese (日本語)',
                'structure' => 'use Japanese <h2> titles (e.g., "Nikeについて", "注目の製品", "メリットとデメリット")',
                'h1_format' => 'written in Japanese',
            ],
            'ko' => [
                'label' => 'Korean (한국어)',
                'structure' => 'use Korean <h2> titles (e.g., "나이키 소개", "인기 제품", "장단점")',
                'h1_format' => 'written in Korean',
            ],
            'id' => [
                'label' => 'Indonesian (Bahasa Indonesia)',
                'structure' => 'use Indonesian <h2> titles (e.g., "Tentang Nike", "Produk Unggulan", "Kelebihan dan Kekurangan")',
                'h1_format' => 'written in Indonesian',
            ],
            'ms' => [
                'label' => 'Malay (Bahasa Melayu)',
                'structure' => 'use Malay <h2> titles (e.g., "Mengenai Nike", "Produk Pilihan", "Kelebihan dan Kelemahan")',
                'h1_format' => 'written in Malay',
            ],
            'hi' => [
                'label' => 'Hindi (हिन्दी)',
                'structure' => 'use Hindi <h2> titles (e.g., "Nike के बारे में", "प्रमुख उत्पाद", "पेशेवरों और विपक्ष")',
                'h1_format' => 'written in Hindi',
            ],
            'ar' => [
                'label' => 'Arabic (العربية)',
                'structure' => 'use Arabic <h2> titles (e.g., "حول نايك", "المنتجات المميزة", "المميزات والعيوب")',
                'h1_format' => 'written in Arabic',
            ],
            // European
            'en' => [
                'label' => 'English',
                'structure' => 'use English <h2> titles (e.g., "About Nike", "Featured Products", "Pros and Cons")',
                'h1_format' => 'written in English',
            ],
            'fr' => [
                'label' => 'French (Français)',
                'structure' => 'use French <h2> titles (e.g., "À propos de Nike", "Produits phares", "Avantages et Inconvénients")',
                'h1_format' => 'written in French',
            ],
            'de' => [
                'label' => 'German (Deutsch)',
                'structure' => 'use German <h2> titles (e.g., "Über Nike", "Top-Produkte", "Vorteile und Nachteile")',
                'h1_format' => 'written in German',
            ],
            'es' => [
                'label' => 'Spanish (Español)',
                'structure' => 'use Spanish <h2> titles (e.g., "Sobre Nike", "Productos destacados", "Ventajas y Desventajas")',
                'h1_format' => 'written in Spanish',
            ],
            'it' => [
                'label' => 'Italian (Italiano)',
                'structure' => 'use Italian <h2> titles (e.g., "Chi è Nike", "Prodotti in evidenza", "Pro e Contro")',
                'h1_format' => 'written in Italian',
            ],
            'pt' => [
                'label' => 'Portuguese (Português)',
                'structure' => 'use Portuguese <h2> titles (e.g., "Sobre a Nike", "Produtos em destaque", "Prós e Contras")',
                'h1_format' => 'written in Portuguese',
            ],
            'ru' => [
                'label' => 'Russian (Русский)',
                'structure' => 'use Russian <h2> titles (e.g., "О Nike", "Популярные продукты", "Плюсы и минусы")',
                'h1_format' => 'written in Russian',
            ],
            'nl' => [
                'label' => 'Dutch (Nederlands)',
                'structure' => 'use Dutch <h2> titles (e.g., "Over Nike", "Populaire producten", "Voordelen en Nadelen")',
                'h1_format' => 'written in Dutch',
            ],
            'pl' => [
                'label' => 'Polish (Polski)',
                'structure' => 'use Polish <h2> titles (e.g., "O Nike", "Popularne produkty", "Zalety i wady")',
                'h1_format' => 'written in Polish',
            ],
            'sv' => [
                'label' => 'Swedish (Svenska)',
                'structure' => 'use Swedish <h2> titles (e.g., "Om Nike", "Populära produkter", "Fördelar och nackdelar")',
                'h1_format' => 'written in Swedish',
            ],
            'no' => [
                'label' => 'Norwegian (Norsk)',
                'structure' => 'use Norwegian <h2> titles (e.g., "Om Nike", "Populære produkter", "Fordeler og ulemper")',
                'h1_format' => 'written in Norwegian',
            ],
            'da' => [
                'label' => 'Danish (Dansk)',
                'structure' => 'use Danish <h2> titles (e.g., "Om Nike", "Populære produkter", "Fordele og ulemper")',
                'h1_format' => 'written in Danish',
            ],
            'fi' => [
                'label' => 'Finnish (Suomi)',
                'structure' => 'use Finnish <h2> titles (e.g., "Tietoa Nikestä", "Suositut tuotteet", "Hyvät ja huonot puolet")',
                'h1_format' => 'written in Finnish',
            ],
            'el' => [
                'label' => 'Greek (Ελληνικά)',
                'structure' => 'use Greek <h2> titles (e.g., "Σχετικά με τη Nike", "Δημοφιλή προϊόντα", "Πλεονεκτήματα και μειονεκτήματα")',
                'h1_format' => 'written in Greek',
            ],
            'hu' => [
                'label' => 'Hungarian (Magyar)',
                'structure' => 'use Hungarian <h2> titles (e.g., "A Nike bemutatása", "Népszerű termékek", "Előnyök és hátrányok")',
                'h1_format' => 'written in Hungarian',
            ],
            'cs' => [
                'label' => 'Czech (Čeština)',
                'structure' => 'use Czech <h2> titles (e.g., "O značce Nike", "Oblíbené produkty", "Výhody a nevýhody")',
                'h1_format' => 'written in Czech',
            ],
            'ro' => [
                'label' => 'Romanian (Română)',
                'structure' => 'use Romanian <h2> titles (e.g., "Despre Nike", "Produse populare", "Avantaje și dezavantaje")',
                'h1_format' => 'written in Romanian',
            ],
            'bg' => [
                'label' => 'Bulgarian (Български)',
                'structure' => 'use Bulgarian <h2> titles (e.g., "За Nike", "Популярни продукти", "Предимства и недостатъци")',
                'h1_format' => 'written in Bulgarian',
            ],
            'tr' => [
                'label' => 'Turkish (Türkçe)',
                'structure' => 'use Turkish <h2> titles (e.g., "Nike hakkında", "Popüler ürünler", "Avantajlar ve dezavantajlar")',
                'h1_format' => 'written in Turkish',
            ],
        ];

        return $langConfig[$lang] ?? $langConfig['en'];
    }

    /**
     * Get the correct brand name for known brands.
     * Falls back to domain extraction if brand not recognized.
     */
    protected function getCorrectBrandName(string $domain, string $fallback): string
    {
        $knownBrands = [
            'nike.com' => 'Nike',
            'adidas.com' => 'Adidas',
            'puma.com' => 'Puma',
            'reebok.com' => 'Reebok',
            'newbalance.com' => 'New Balance',
            'underarmour.com' => 'Under Armour',
            'asics.com' => 'ASICS',
            'converse.com' => 'Converse',
            'vans.com' => 'Vans',
            'jordan.com' => 'Jordan',
            'fila.com' => 'Fila',
            'skechers.com' => 'Skechers',
            'salomon.com' => 'Salomon',
            'amazon.com' => 'Amazon',
            'ebay.com' => 'eBay',
            'walmart.com' => 'Walmart',
            'target.com' => 'Target',
            'bestbuy.com' => 'Best Buy',
            'homedepot.com' => 'Home Depot',
            'macys.com' => "Macy's",
            'nordstrom.com' => 'Nordstrom',
            'zara.com' => 'Zara',
            'hm.com' => 'H&M',
            'uniqlo.com' => 'Uniqlo',
            'gap.com' => 'Gap',
            'levis.com' => "Levi's",
            'nike.com' => 'Nike',
            'apple.com' => 'Apple',
            'samsung.com' => 'Samsung',
            'sony.com' => 'Sony',
            'dell.com' => 'Dell',
            'hp.com' => 'HP',
            'lenovo.com' => 'Lenovo',
            'asus.com' => 'ASUS',
            'acer.com' => 'Acer',
            'microsoft.com' => 'Microsoft',
            'google.com' => 'Google',
            'nintendo.com' => 'Nintendo',
            'playstation.com' => 'PlayStation',
            'xbox.com' => 'Xbox',
            'sephora.com' => 'Sephora',
            'ulta.com' => 'Ulta',
            '丝芙兰.com' => 'Sephora',
        ];

        foreach ($knownBrands as $key => $name) {
            if (str_contains($domain, $key)) {
                return $name;
            }
        }

        return $fallback;
    }
}
