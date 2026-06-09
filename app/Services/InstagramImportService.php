<?php

namespace App\Services;

use Maatwebsite\Excel\Facades\Excel;

class InstagramImportService
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

        if ($columnMap === []) {
            $this->lastError = 'File thiếu cột dữ liệu. Tải file mẫu Excel để xem định dạng.';

            return [];
        }

        $items = [];

        foreach ($rows as $row) {
            $brandDomain = $this->cellValue($row, $columnMap['brand_domain'] ?? null);
            $contentIdea = $this->cellValue($row, $columnMap['content_idea'] ?? null);
            $affLink = $this->cellValue($row, $columnMap['aff_link'] ?? null);
            $couponRaw = $this->cellValue($row, $columnMap['coupon_codes'] ?? null);
            $couponCodes = $this->parseCouponCodes($couponRaw);

            if ($brandDomain === '' && $contentIdea === '' && $affLink === '' && $couponCodes === []) {
                continue;
            }

            $items[] = [
                'media' => null,
                'brand_domain' => $brandDomain !== '' ? $brandDomain : null,
                'content_idea' => $contentIdea !== '' ? $contentIdea : null,
                'aff_link' => $affLink !== '' ? $affLink : null,
                'coupon_codes' => $couponCodes,
            ];
        }

        if ($items === []) {
            $this->lastError = 'Không có dòng hợp lệ (cần ít nhất Domain, ý tưởng, Link Affiliate hoặc Coupon).';
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
                in_array($normalized, ['noi dung y tuong', 'nội dung ý tưởng', 'nội dung / ý tưởng', 'content idea', 'content_idea', 'content', 'idea', 'y tuong caption', 'ý tưởng caption'], true) => 'content_idea',
                in_array($normalized, ['Link Affiliate', 'aff link', 'aff_link', 'affiliate', 'aff'], true) => 'aff_link',
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
}
