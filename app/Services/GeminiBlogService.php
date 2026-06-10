<?php

namespace App\Services;

use App\Support\AdminSettings;
use App\Support\AffiliateContentGuidelines;
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
        $store = IntegrationSettingsStore::for($ownerUserId);
        $model = (string) $store->get('gemini_model', config('gemini.model', 'gemini-1.5-flash-latest'));
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

        return $this->callGeminiWithFallback($model, $prompt, $timeout, [
            'maxOutputTokens' => 8192,
        ], $ownerUserId);
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

        $ownerUserId = IntegrationSettingsStore::fallbackAdminUserId();
        $store = IntegrationSettingsStore::for($ownerUserId);
        $model = (string) $store->get('gemini_model', config('gemini.model', 'gemini-1.5-flash-latest'));
        $timeout = max(90, $this->resolveTimeoutSeconds($ownerUserId));

        $brandLabel = self::guessBrandNameFromDomain($host);
        $year = (int) now()->format('Y');
        $brandEsc = htmlspecialchars($brandLabel, ENT_QUOTES, 'UTF-8');

        $contentIdeaSection = filled($contentIdea)
            ? "\n## Content direction (follow closely)\n".trim($contentIdea)."\n"
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
You are an expert English SEO copywriter for **independent affiliate review** blogs (tiếp thị liên kết).

## Brand to cover (third person — you do NOT represent this brand)
- Domain: {$host}
- Official website: {$siteUrl}
- Brand name to use (adjust if you know the real name): {$brandLabel}
{$contentIdeaSection}{$affSection}{$couponSection}
{$affiliateRules}

## Task
Write ONE long-form **affiliate review / buyer's guide** about this brand. Language: **English**. Target length **1,200–1,800 words**. Tone: helpful, trustworthy, conversion-oriented but honest — as an outside recommender, not the store itself.

## HTML output rules
- Return a complete HTML **fragment** only: one `<h1>`, then `<h2>`, `<h3>`, `<p>`, `<ul>`, `<ol>` as needed. Do NOT wrap in `<html>` or `<body>`.
- **Do NOT include any `<a>` hyperlinks** in your output. Write the brand name "{$brandLabel}" as plain text only (never wrap it in a link).
- Promotional links will be added automatically at the top and bottom of the article — you must not add links yourself.

## Required structure (use English `<h2>` titles)
1. `<h1>`: Include brand name + clear value proposition (Review, Guide, or {$year} only if natural) — brand name as plain text, no links.
2. Opening: problem readers face + why this brand is worth considering (third person).
3. What is {$brandLabel}: what they sell, positioning, USP — describe the brand; never say "we" or speak as the store.
4. Products / services / highlights (optional `<h3>` subsections).
5. **Pros and cons** — both lists, balanced.
6. Who should consider shopping at {$brandLabel}.
7. Closing summary paragraph (plain text, no links or CTAs) — recommendation tone, not store ownership.

Do not claim you partnered with the brand unless factual. Do not use "we/us/our" or Vietnamese "Chúng tôi" / store-owner voice anywhere.
PROMPT;

        $result = $this->callGeminiWithFallback($model, $prompt, $timeout, [
            'maxOutputTokens' => 8192,
            'temperature' => 0.85,
        ], $ownerUserId);

        if ($result === null) {
            return null;
        }

        $result['content'] = $this->formatBrandPromoContent(
            $result['content'],
            $ctaUrl,
            $siteUrl,
            $brandLabel,
        );

        $result['domain'] = $host;

        return $result;
    }

    protected function formatBrandPromoContent(string $html, string $ctaUrl, string $siteUrl, string $brandLabel): string
    {
        $html = $this->stripInlinePromoLinks($html, $ctaUrl, $siteUrl);

        return $this->injectBrandPromoLinkBlocks($html, $ctaUrl, $brandLabel);
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
    public function generatePlainText(string $prompt, array $generationConfigOverrides = [], ?int $ownerUserId = null): ?string
    {
        $store = IntegrationSettingsStore::for($ownerUserId);
        $model = (string) $store->get('gemini_model', config('gemini.model', 'gemini-1.5-flash-latest'));
        $timeout = max(60, $this->resolveTimeoutSeconds($ownerUserId));

        $result = $this->callGeminiWithFallback($model, $prompt, $timeout, $generationConfigOverrides, $ownerUserId);

        if (! $result) {
            return null;
        }

        $text = trim((string) ($result['content'] ?? ''));

        return $text !== '' ? $text : null;
    }

    /**
     * @param  array<string, mixed>  $generationConfigOverrides
     */
    protected function callGeminiWithFallback(string $model, string $prompt, int $timeout, array $generationConfigOverrides = [], ?int $ownerUserId = null): ?array
    {
        $apiKeys = IntegrationSettingsStore::for($ownerUserId)->getGeminiApiKeys();

        if ($apiKeys === []) {
            $this->lastError = 'GEMINI_API_KEY chưa được cấu hình.';

            return null;
        }

        $errors = [];

        foreach ($this->modelsToTry($model) as $currentModel) {
            foreach ($apiKeys as $index => $apiKey) {
                $attempt = $this->attemptGeminiCallWithRetries(
                    $apiKey,
                    $currentModel,
                    $prompt,
                    $timeout,
                    $generationConfigOverrides,
                );

                if ($attempt['success']) {
                    $this->lastError = null;

                    if ($currentModel !== $model) {
                        Log::info('GeminiBlogService: đã chuyển sang model dự phòng', [
                            'from' => $model,
                            'to' => $currentModel,
                        ]);
                    }

                    if ($index > 0) {
                        Log::info('GeminiBlogService: đã chuyển sang API key dự phòng', [
                            'key_number' => $index + 1,
                        ]);
                    }

                    return $attempt['result'];
                }

                $errors[] = "{$currentModel} / Key ".($index + 1).': '.$attempt['error'];
                $this->lastError = $attempt['error'];

                if ($index < count($apiKeys) - 1 && $attempt['retryable']) {
                    Log::warning('GeminiBlogService: key lỗi, thử key tiếp theo', [
                        'model' => $currentModel,
                        'key_number' => $index + 1,
                        'error' => $attempt['error'],
                    ]);
                }
            }
        }

        $this->lastError = 'Tất cả Gemini API key đều lỗi. '.implode(' | ', $errors);

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function modelsToTry(string $primary): array
    {
        $fallbacks = [
            'gemini-flash-latest' => ['gemini-2.5-flash-lite', 'gemini-2.5-flash'],
            'gemini-2.5-flash' => ['gemini-2.5-flash-lite', 'gemini-flash-latest'],
            'gemini-2.5-flash-lite' => ['gemini-2.5-flash', 'gemini-flash-latest'],
            'gemini-1.5-flash-latest' => ['gemini-2.5-flash-lite', 'gemini-2.5-flash'],
            'gemini-2.0-flash' => ['gemini-2.5-flash-lite', 'gemini-2.5-flash'],
        ];

        $models = [$primary];

        foreach ($fallbacks[$primary] ?? ['gemini-2.5-flash-lite', 'gemini-2.5-flash', 'gemini-flash-latest'] as $fallback) {
            if (! in_array($fallback, $models, true)) {
                $models[] = $fallback;
            }
        }

        return $models;
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
}
