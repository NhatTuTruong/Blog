<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteContent extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = 'site_content.' . $key;
        $value = Cache::remember($cacheKey, 3600, function () use ($key) {
            $row = static::query()->where('key', $key)->first();

            return $row?->value;
        });

        if ($value === null) {
            return $default;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $stringValue = is_string($value) ? $value : json_encode($value);
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stringValue]
        );
        Cache::forget('site_content.' . $key);
    }

    public static function defaultHeaderNav(): array
    {
        return [
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Blog', 'url' => '/blog'],
            ['label' => 'About', 'url' => '/about'],
            ['label' => 'Contact', 'url' => '/contact'],
        ];
    }

    public static function defaultFooterColumns(): array
    {
        return [
            [
                'title' => 'Explore',
                'links' => [
                    ['label' => 'Home', 'url' => '/'],
                    ['label' => 'Blog', 'url' => '/blog'],
                    ['label' => 'About', 'url' => '/about'],
                    ['label' => 'Contact', 'url' => '/contact'],
                ],
            ],
            [
                'title' => 'Legal',
                'links' => [
                    ['label' => 'Privacy Policy', 'url' => '/privacy'],
                    ['label' => 'Cookie Policy', 'url' => '/cookie-policy'],
                    ['label' => 'Terms of Use', 'url' => '/terms'],
                ],
            ],
        ];
    }

    public static function defaultErrorContent(string $code): array
    {
        return match ($code) {
            '404' => ['title' => 'Trang không tồn tại', 'message' => 'Trang bạn tìm kiếm không tồn tại hoặc đã bị di chuyển.'],
            '403' => ['title' => 'Không có quyền truy cập', 'message' => 'Bạn không có quyền truy cập trang này.'],
            '500' => ['title' => 'Lỗi máy chủ', 'message' => 'Đã xảy ra lỗi. Chúng tôi đang khắc phục.'],
            '503' => ['title' => 'Bảo trì', 'message' => 'Hệ thống đang bảo trì. Vui lòng quay lại sau.'],
            default => ['title' => 'Lỗi', 'message' => 'Đã xảy ra lỗi.'],
        };
    }

    public static function defaultPageAboutUs(): string
    {
        return <<<'HTML'
<h1 class="font-heading">About Us</h1>

<p><strong>[SITE_NAME]</strong> is an independent blog focused on helpful articles, guides, and thoughtful commentary. We publish content to inform and inspire our readers.</p>

<h2 class="font-heading">Our Mission</h2>
<ul>
<li>Publish clear, well-researched articles</li>
<li>Share practical guides and comparisons</li>
<li>Keep our content updated and easy to read</li>
</ul>

<h2 class="font-heading">Editorial Standards</h2>
<p>We aim for accuracy, clarity, and usefulness. When we update an article, we revise the content to reflect current information.</p>

<h2 class="font-heading">Contact</h2>
<p>Questions or feedback? Visit our <a href="/contact">Contact</a> page — we would love to hear from you.</p>
HTML;
    }

    public static function defaultPageContact(): string
    {
        return <<<'HTML'
<h1 class="font-heading">Contact Us</h1>
<p>If you have any questions or concerns, please feel free to reach out to us.</p>
<p>Email: [SITE_EMAIL]</p>
<p>We aim to respond to all inquiries within 24–48 hours.</p>
HTML;
    }

    public static function defaultPagePrivacy(): string
    {
        return <<<'HTML'
<h1 class="font-heading">Privacy Policy</h1>
<p class="updated">Last updated: [PRIVACY_DATE]</p>

<p>At <strong>[SITE_NAME]</strong>, we value your privacy. This policy explains how we collect, use, and protect information when you visit our website.</p>

<h2>Information We Collect</h2>
<ul>
<li>Non-personal data such as browser type, device, and pages visited</li>
<li>Information you voluntarily provide via our contact form</li>
</ul>

<h2>How We Use Information</h2>
<ul>
<li>To improve our website and content</li>
<li>To respond to your messages</li>
<li>To maintain security and prevent abuse</li>
</ul>

<h2>Cookies</h2>
<p>We may use cookies to remember preferences and understand how the site is used. You can disable cookies in your browser settings.</p>

<h2>Third-Party Services</h2>
<p>We may use analytics or hosting providers that process data on our behalf. Please review their policies for details.</p>

<h2>Your Rights</h2>
<p>Depending on your location, you may request access, correction, or deletion of personal data we hold. Contact us via the Contact page.</p>

<h2>Contact</h2>
<p>For privacy questions, please visit our <a href="/contact">Contact</a> page.</p>
HTML;
    }

    public static function defaultPageCookiePolicy(): string
    {
        return <<<'HTML'
<h1 class="font-heading">Cookie Policy</h1>
<p class="updated">Last updated: [COOKIE_DATE]</p>

<p>This Cookie Policy explains how <strong>[SITE_NAME]</strong> uses cookies when you visit our website. See also our <a href="/privacy">Privacy Policy</a>.</p>

<h2>What Are Cookies</h2>
<p>Cookies are small text files stored on your device. They help the site remember preferences and understand usage.</p>

<h2>How We Use Cookies</h2>
<ul>
<li><strong>Essential:</strong> Required for basic site functionality.</li>
<li><strong>Analytics:</strong> To understand traffic and improve content.</li>
<li><strong>Preferences:</strong> To remember settings you choose.</li>
</ul>

<h2>Managing Cookies</h2>
<p>You can control or delete cookies through your browser. Disabling cookies may affect some features.</p>

<h2>Contact</h2>
<p>Questions? See our <a href="/contact">Contact</a> page.</p>
HTML;
    }

    public static function defaultSocialLinks(): array
    {
        return collect(self::socialPlatformOptions())
            ->keys()
            ->map(fn (string $platform): array => [
                'platform' => $platform,
                'url' => '',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function socialPlatformOptions(): array
    {
        return [
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'twitter' => 'Twitter / X',
            'youtube' => 'YouTube',
            'tiktok' => 'TikTok',
            'pinterest' => 'Pinterest',
            'linkedin' => 'LinkedIn',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $links
     * @return array<string, string>
     */
    public static function socialLinksAsMap(array $links): array
    {
        $map = array_fill_keys(array_keys(self::socialPlatformOptions()), '');

        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }

            $platform = (string) ($link['platform'] ?? '');

            if (array_key_exists($platform, $map)) {
                $map[$platform] = trim((string) ($link['url'] ?? ''));
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $map
     * @return array<int, array<string, string>>
     */
    public static function socialLinksFromMap(array $map): array
    {
        return collect(self::socialPlatformOptions())
            ->map(fn (string $label, string $platform): array => [
                'platform' => $platform,
                'url' => trim((string) ($map[$platform] ?? '')),
            ])
            ->values()
            ->all();
    }

    public static function visibleSocialLinks(): \Illuminate\Support\Collection
    {
        $links = self::get('social_media_links', self::defaultSocialLinks());

        if (! is_array($links)) {
            return collect();
        }

        return collect($links)->filter(fn (mixed $item): bool => is_array($item) && filled($item['url'] ?? null));
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultSeoSettings(): array
    {
        $appName = (string) config('app.name');

        return [
            'title_suffix' => '- '.$appName,
            'meta_description_default' => 'Latest articles and insights from our blog.',
            'og_image_default' => '',
            'robots' => 'index, follow',
            'google_site_verification' => '',
            'pages' => [
                'home' => [
                    'title' => $appName.' — Blog & Articles',
                    'description' => 'Discover guides, stories and trending articles. Search by topic or browse featured categories.',
                ],
                'blog' => [
                    'title' => 'Blog — '.$appName,
                    'description' => 'Explore guides, stories and trending articles. Search by topic or browse featured categories.',
                ],
                'about' => [
                    'title' => 'About Us - '.$appName,
                    'description' => 'Learn about our blog and editorial mission.',
                ],
                'contact' => [
                    'title' => 'Contact Us - '.$appName,
                    'description' => 'Get in touch with us for questions and feedback.',
                ],
                'privacy' => [
                    'title' => 'Privacy Policy - '.$appName,
                    'description' => 'Read our privacy policy and how we handle your data.',
                ],
                'cookie' => [
                    'title' => 'Cookie Policy - '.$appName,
                    'description' => 'How we use cookies and similar technologies on our website.',
                ],
                'terms' => [
                    'title' => 'Terms of Use - '.$appName,
                    'description' => 'Terms of use for our website and blog.',
                ],
            ],
        ];
    }

    public static function socialPlatformIcon(string $platform): string
    {
        return match ($platform) {
            'facebook' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
            'instagram' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>',
            'twitter' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
            'youtube' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>',
            'tiktok' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>',
            'pinterest' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.372 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 0 1 .083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/></svg>',
            'linkedin' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
            default => '',
        };
    }
}
