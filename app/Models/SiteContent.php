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
}
