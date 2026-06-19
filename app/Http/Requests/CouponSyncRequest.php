<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'aff_link' => ['nullable', 'url', 'max:2048'],
            'affiliate_link' => ['nullable', 'url', 'max:2048'],
            'coupon_code' => ['nullable', 'string', 'max:120'],
            'coupon_codes' => ['nullable', 'array', 'max:50'],
            'coupon_codes.*' => ['string', 'max:120'],
            'content_idea' => ['nullable', 'string', 'max:2000'],
            'blog_category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.domain' => ['nullable', 'string', 'max:255'],
            'items.*.brand_domain' => ['nullable', 'string', 'max:255'],
            'items.*.aff_link' => ['nullable', 'url', 'max:2048'],
            'items.*.affiliate_link' => ['nullable', 'url', 'max:2048'],
            'items.*.coupon_code' => ['nullable', 'string', 'max:120'],
            'items.*.coupon_codes' => ['nullable', 'array', 'max:50'],
            'items.*.coupon_codes.*' => ['string', 'max:120'],
            'items.*.content_idea' => ['nullable', 'string', 'max:2000'],
            'items.*.blog_category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->normalizedItems() === []) {
                $validator->errors()->add('domain', 'Cần domain, aff_link và ít nhất một mã coupon (coupon_code hoặc coupon_codes).');
            }
        });
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

        if ($domain === '' || $affLink === '' || $couponCodes === []) {
            return null;
        }

        $contentIdea = trim((string) ($row['content_idea'] ?? ''));
        if ($contentIdea === '') {
            $codeLabel = implode(', ', $couponCodes);
            $contentIdea = "Coupon {$codeLabel} for {$domain}";
        }

        $record = [
            'brand_domain' => $domain,
            'aff_link' => $affLink,
            'coupon_codes' => $couponCodes,
            'content_idea' => $contentIdea,
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
}
