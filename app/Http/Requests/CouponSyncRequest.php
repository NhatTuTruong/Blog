<?php

namespace App\Http\Requests;

use App\Support\CouponSyncPlatforms;
use App\Support\SocialMediaMediaType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CouponSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'domain' => ['nullable', 'string', 'max:255'],
            'brand_domain' => ['nullable', 'string', 'max:255'],
            'aff_link' => ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                $this->validateOptionalUrl($value, $fail);
            }],
            'affiliate_link' => ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                $this->validateOptionalUrl($value, $fail);
            }],
            'coupon_code' => ['nullable', 'string', 'max:120'],
            'coupon_codes' => ['nullable', 'array', 'max:50'],
            'coupon_codes.*' => ['string', 'max:120'],
            'content_idea' => ['nullable', 'string', 'max:2000'],
            'blog_category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'type' => ['nullable', 'string', 'max:32', function (string $attribute, mixed $value, \Closure $fail): void {
                $this->validateMediaType($value, $fail);
            }],
            'media_type' => ['nullable', 'string', 'max:32', function (string $attribute, mixed $value, \Closure $fail): void {
                $this->validateMediaType($value, $fail);
            }],
            'platforms' => ['nullable', 'array', 'min:1', 'max:3'],
            'platforms.*' => ['string', 'max:32', function (string $attribute, mixed $value, \Closure $fail): void {
                $this->validatePlatform($value, $fail);
            }],
            'items' => ['nullable', 'array'],
            'items.*.domain' => ['nullable', 'string', 'max:255'],
            'items.*.brand_domain' => ['nullable', 'string', 'max:255'],
            'items.*.aff_link' => ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                $this->validateOptionalUrl($value, $fail);
            }],
            'items.*.affiliate_link' => ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                $this->validateOptionalUrl($value, $fail);
            }],
            'items.*.coupon_code' => ['nullable', 'string', 'max:120'],
            'items.*.coupon_codes' => ['nullable', 'array', 'max:50'],
            'items.*.coupon_codes.*' => ['string', 'max:120'],
            'items.*.content_idea' => ['nullable', 'string', 'max:2000'],
            'items.*.blog_category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'items.*.type' => ['nullable', 'string', 'max:32', function (string $attribute, mixed $value, \Closure $fail): void {
                $this->validateMediaType($value, $fail);
            }],
            'items.*.media_type' => ['nullable', 'string', 'max:32', function (string $attribute, mixed $value, \Closure $fail): void {
                $this->validateMediaType($value, $fail);
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'domain.string' => 'Domain phải là chuỗi ký tự.',
            'brand_domain.string' => 'Brand domain phải là chuỗi ký tự.',
            'aff_link.url' => 'Link affiliate (aff_link) phải là URL hợp lệ (hoặc để trống).',
            'affiliate_link.url' => 'Link affiliate phải là URL hợp lệ (hoặc để trống).',
            'coupon_codes.array' => 'Danh sách mã coupon (coupon_codes) phải là mảng.',
            'coupon_codes.max' => 'Tối đa 50 mã coupon mỗi bản ghi.',
            'coupon_codes.*.string' => 'Mỗi mã coupon phải là chuỗi ký tự.',
            'coupon_codes.*.max' => 'Mỗi mã coupon tối đa 120 ký tự.',
            'content_idea.max' => 'Ý tưởng nội dung tối đa 2000 ký tự.',
            'blog_category_id.exists' => 'Danh mục blog không tồn tại.',
            'platforms.array' => 'Trường platforms phải là mảng.',
            'platforms.min' => 'Cần chọn ít nhất một nền tảng trong platforms.',
            'platforms.max' => 'Tối đa 3 nền tảng: blog, instagram, facebook.',
            'items.array' => 'Trường items phải là mảng.',
            'items.*.domain.string' => 'Domain trong items phải là chuỗi ký tự.',
            'items.*.aff_link.url' => 'aff_link trong items phải là URL hợp lệ (hoặc để trống).',
            'items.*.affiliate_link.url' => 'Link affiliate trong items phải là URL hợp lệ (hoặc để trống).',
            'items.*.coupon_codes.array' => 'coupon_codes trong items phải là mảng.',
            'items.*.blog_category_id.exists' => 'Danh mục blog trong items không tồn tại.',
        ];
    }

    public function attributes(): array
    {
        return [
            'domain' => 'domain',
            'brand_domain' => 'brand domain',
            'aff_link' => 'link affiliate',
            'affiliate_link' => 'link affiliate',
            'coupon_code' => 'mã coupon',
            'coupon_codes' => 'danh sách mã coupon',
            'content_idea' => 'ý tưởng nội dung',
            'type' => 'loại media',
            'media_type' => 'loại media',
            'platforms' => 'danh sách nền tảng',
            'platforms.*' => 'nền tảng',
            'items' => 'danh sách bản ghi',
            'items.*.domain' => 'domain',
            'items.*.aff_link' => 'link affiliate',
            'items.*.coupon_codes' => 'mã coupon',
            'items.*.type' => 'loại media',
            'items.*.media_type' => 'loại media',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Dữ liệu gửi lên không hợp lệ.',
            'errors' => $validator->errors(),
        ], 422));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->normalizedItems() === []) {
                $validator->errors()->add(
                    'items',
                    'Cần ít nhất một bản ghi hợp lệ có domain (brand_domain hoặc domain). aff_link và coupon_codes có thể để trống.',
                );
            }

            $platforms = $this->input('platforms');
            if (is_array($platforms) && $platforms !== [] && $this->normalizedPlatforms() === []) {
                $validator->errors()->add(
                    'platforms',
                    CouponSyncPlatforms::invalidMessage(),
                );
            }
        });
    }

    /**
     * @return array<int, string>
     */
    public function normalizedPlatforms(): array
    {
        $platforms = $this->input('platforms');

        if (! is_array($platforms) || $platforms === []) {
            return CouponSyncPlatforms::defaultsFromConfig();
        }

        return CouponSyncPlatforms::normalize($platforms);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function normalizedItems(): array
    {
        $items = $this->input('items');

        if (is_array($items) && $items !== []) {
            return collect($items)
                ->map(fn (mixed $row): ?array => $this->normalizeRow(is_array($row) ? $row : []))
                ->filter()
                ->values()
                ->all();
        }

        $single = $this->normalizeRow([
            'domain' => $this->input('domain'),
            'brand_domain' => $this->input('brand_domain'),
            'aff_link' => $this->input('aff_link'),
            'affiliate_link' => $this->input('affiliate_link'),
            'coupon_code' => $this->input('coupon_code'),
            'coupon_codes' => $this->input('coupon_codes'),
            'content_idea' => $this->input('content_idea'),
            'blog_category_id' => $this->input('blog_category_id'),
            'type' => $this->input('type'),
            'media_type' => $this->input('media_type'),
        ]);

        return $single !== null ? [$single] : [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    protected function normalizeRow(array $row): ?array
    {
        $domain = trim((string) ($row['brand_domain'] ?? $row['domain'] ?? ''));
        $affLink = trim((string) ($row['aff_link'] ?? $row['affiliate_link'] ?? ''));
        $couponCodes = $this->normalizeCouponCodes($row);

        if ($domain === '') {
            return null;
        }

        $contentIdea = trim((string) ($row['content_idea'] ?? ''));
        if ($contentIdea === '') {
            $contentIdea = $couponCodes !== []
                ? 'Coupon '.implode(', ', $couponCodes)." for {$domain}"
                : "Content for {$domain}";
        }

        $record = [
            'brand_domain' => $domain,
            'aff_link' => $affLink !== '' ? $affLink : null,
            'coupon_codes' => $couponCodes,
            'content_idea' => $contentIdea,
            'media_type' => $this->resolveMediaType($row),
        ];

        if (filled($row['blog_category_id'] ?? null)) {
            $record['blog_category_id'] = (int) $row['blog_category_id'];
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    protected function normalizeCouponCodes(array $row): array
    {
        $codes = [];

        if (filled($row['coupon_code'] ?? null)) {
            $codes[] = trim((string) $row['coupon_code']);
        }

        $extra = $row['coupon_codes'] ?? [];

        if (is_array($extra)) {
            foreach ($extra as $code) {
                $codes[] = trim((string) $code);
            }
        }

        return collect($codes)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function resolveMediaType(array $row): string
    {
        $raw = $row['type'] ?? $row['media_type'] ?? null;

        if ($raw === null || trim((string) $raw) === '') {
            return SocialMediaMediaType::IMAGE;
        }

        return SocialMediaMediaType::normalizeFromApi((string) $raw);
    }

    protected function validateMediaType(mixed $value, \Closure $fail): void
    {
        if (! SocialMediaMediaType::isValidApiType($value)) {
            $fail(SocialMediaMediaType::invalidApiTypeMessage());
        }
    }

    protected function validatePlatform(mixed $value, \Closure $fail): void
    {
        if (! CouponSyncPlatforms::isValid($value)) {
            $fail(CouponSyncPlatforms::invalidMessage());
        }
    }

    protected function validateOptionalUrl(mixed $value, \Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        $url = trim((string) $value);

        if ($url === '') {
            return;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $fail('Link affiliate phải là URL hợp lệ (hoặc để trống).');
        }
    }
}
