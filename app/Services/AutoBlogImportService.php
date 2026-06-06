<?php

namespace App\Services;

use App\Models\BlogCategory;
use Maatwebsite\Excel\Facades\Excel;

class AutoBlogImportService
{
    public ?string $lastError = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseFile(string $absolutePath): array
    {
        $this->lastError = null;

        if (! is_file($absolutePath)) {
            $this->lastError = 'Không tìm thấy file import.';

            return [];
        }

        try {
            $sheets = Excel::toArray(null, $absolutePath);
        } catch (\Throwable $e) {
            $this->lastError = 'Không đọc được file: '.$e->getMessage();

            return [];
        }

        $rows = $sheets[0] ?? [];

        if ($rows === []) {
            $this->lastError = 'File trống hoặc không có dữ liệu.';

            return [];
        }

        $headerRow = array_map(
            fn (mixed $cell): string => $this->fixUtf8Text((string) $cell),
            array_shift($rows),
        );
        $columnMap = $this->mapColumns($headerRow);

        if (! isset($columnMap['brand_domain'])) {
            $this->lastError = 'Thiếu cột «Domain brand». Tải file mẫu CSV để xem định dạng.';

            return [];
        }

        $items = [];
        $categoryOptions = BlogCategory::optionsForSelect();
        $categoryByName = array_flip($categoryOptions);

        foreach ($rows as $index => $row) {
            $domain = $this->cellValue($row, $columnMap['brand_domain'] ?? null);

            if ($domain === '') {
                continue;
            }

            $categoryInput = $this->cellValue($row, $columnMap['blog_category_id'] ?? null);
            $blogCategoryId = null;

            if ($categoryInput !== '') {
                if (is_numeric($categoryInput)) {
                    $blogCategoryId = (int) $categoryInput;
                } else {
                    $blogCategoryId = $categoryByName[$categoryInput] ?? null;
                }
            }

            $couponRaw = $this->cellValue($row, $columnMap['coupon_codes'] ?? null);
            $couponCodes = $this->parseCouponCodes($couponRaw);

            $items[] = [
                'brand_domain' => $domain,
                'blog_category_id' => $blogCategoryId,
                'content_idea' => $this->cellValue($row, $columnMap['content_idea'] ?? null) ?: null,
                'aff_link' => $this->cellValue($row, $columnMap['aff_link'] ?? null) ?: null,
                'coupon_codes' => $couponCodes,
            ];
        }

        if ($items === []) {
            $this->lastError = 'Không có dòng hợp lệ (cần ít nhất Domain brand).';
        }

        return $items;
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array<string, int>
     */
    protected function mapColumns(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);

            if ($normalized === '') {
                continue;
            }

            $field = match (true) {
                in_array($normalized, ['domain brand', 'brand domain', 'domain', 'brand_domain', 'brand'], true) => 'brand_domain',
                in_array($normalized, ['danh muc bai viet', 'danh mục bài viết', 'category', 'blog category', 'blog_category'], true) => 'blog_category_id',
                in_array($normalized, ['noi dung y tuong', 'nội dung ý tưởng', 'nội dung / ý tưởng', 'content idea', 'content_idea', 'content', 'idea'], true) => 'content_idea',
                in_array($normalized, ['link aff', 'aff link', 'aff_link', 'affiliate', 'aff'], true) => 'aff_link',
                in_array($normalized, ['coupon code', 'coupon codes', 'coupon_code', 'coupon_codes', 'coupon', 'coupons'], true) => 'coupon_codes',
                default => null,
            };

            if ($field !== null && ! isset($map[$field])) {
                $map[$field] = (int) $index;
            }
        }

        return $map;
    }

    protected function normalizeHeader(string $header): string
    {
        $header = trim(mb_strtolower($header));
        $header = str_replace(['_', '-', '/'], ' ', $header);

        return preg_replace('/\s+/', ' ', $header) ?? $header;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    protected function cellValue(array $row, ?int $index): string
    {
        if ($index === null) {
            return '';
        }

        return $this->fixUtf8Text((string) ($row[$index] ?? ''));
    }

    protected function fixUtf8Text(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            $value = substr($value, 3);
        }

        if (preg_match('/(?:Ã.|áº|á»|Æ°|Ä|Å)/u', $value)) {
            $repaired = @mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');

            if ($repaired !== false && mb_check_encoding($repaired, 'UTF-8')) {
                $value = $repaired;
            }
        }

        if (! mb_check_encoding($value, 'UTF-8')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');

            if ($converted !== false) {
                $value = $converted;
            }
        }

        return trim($value);
    }

    /**
     * @return array<int, string>
     */
    protected function parseCouponCodes(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[,;|]+/', $raw) ?: [];

        return collect($parts)
            ->map(fn (string $code): string => trim($code))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function templateCsvContent(): string
    {
        $lines = [
            'Domain brand,Danh mục bài viết,Nội dung / ý tưởng,Link AFF,Coupon code',
            'nike.com,Shoes,Review giày chạy bộ mới,https://example.com/aff,SAVE10',
            'amazon.com,Tech,Top laptop 2026,,DEAL20;EXTRA5',
        ];

        return "\xEF\xBB\xBF".implode("\r\n", $lines);
    }
}
