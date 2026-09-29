<?php

namespace App\Services;

use App\Models\Blog;
use App\Support\BlogCategorySelection;
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
            $this->lastError = 'Thiếu cột «Domain brand». Tải file mẫu Excel để xem định dạng.';

            return [];
        }

        $items = $this->parseGroupedRows($rows, $columnMap);

        if ($items === []) {
            $this->lastError = 'Không có dòng hợp lệ (cần ít nhất một dòng có Domain brand).';
        }

        return $items;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<string, int>  $columnMap
     * @return array<int, array<string, mixed>>
     */
    protected function parseGroupedRows(array $rows, array $columnMap): array
    {
        $items = [];
        $current = null;

        foreach ($rows as $row) {
            $domain = $this->cellValue($row, $columnMap['brand_domain'] ?? null);
            $deal = $this->parseDealFromRow($row, $columnMap);
            $hasDeal = $this->dealHasData($deal);

            if ($domain !== '') {
                if ($current !== null) {
                    $items[] = $this->finalizeArticleRecord($current);
                }

                $current = $this->parseArticleFromRow($row, $columnMap, $domain);

                if ($hasDeal) {
                    $current['deals_data'][] = $deal;
                }

                continue;
            }

            if ($current !== null && $hasDeal) {
                $current['deals_data'][] = $deal;
            }
        }

        if ($current !== null) {
            $items[] = $this->finalizeArticleRecord($current);
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
                in_array($normalized, ['loai bai viet', 'loại bài viết', 'post type', 'post_type', 'type', 'loai bai'], true) => 'post_type',
                in_array($normalized, ['danh muc bai viet', 'danh mục bài viết', 'category', 'blog category', 'blog_category'], true) => 'blog_category_id',
                in_array($normalized, ['noi dung y tuong', 'nội dung ý tưởng', 'nội dung / ý tưởng', 'content idea', 'content_idea', 'content', 'idea'], true) => 'content_idea',
                in_array($normalized, ['link affiliate', 'aff link', 'aff_link', 'affiliate', 'aff'], true) => 'aff_link',
                in_array($normalized, ['coupon code', 'coupon codes', 'coupon_code', 'coupon_codes', 'coupon', 'coupons'], true) => 'coupon_codes',
                in_array($normalized, ['deal tieu de', 'deal tiêu đề', 'deal title', 'deal_title', 'tieu de deal', 'tiêu đề deal'], true) => 'deal_title',
                in_array($normalized, ['deal mo ta', 'deal mô tả', 'deal description', 'deal_description', 'mo ta deal', 'mô tả deal'], true) => 'deal_description',
                in_array($normalized, ['deal ma coupon', 'deal mã coupon', 'deal coupon', 'deal coupon code', 'deal_coupon_code', 'ma coupon deal', 'mã coupon deal'], true) => 'deal_coupon_code',
                in_array($normalized, ['deal link shop', 'deal shop url', 'deal link', 'deal_shop_url', 'link shop deal', 'link cửa hàng deal'], true) => 'deal_shop_url',
                default => null,
            };

            if ($field !== null && ! isset($map[$field])) {
                $map[$field] = (int) $index;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>
     */
    protected function parseArticleFromRow(array $row, array $columnMap, string $domain): array
    {
        $categoryInput = $this->cellValue($row, $columnMap['blog_category_id'] ?? null);
        $categoryIds = BlogCategorySelection::normalizeIds($categoryInput !== '' ? $categoryInput : null);

        $couponRaw = $this->cellValue($row, $columnMap['coupon_codes'] ?? null);

        return [
            'brand_domain' => $domain,
            'post_type' => $this->parsePostType($this->cellValue($row, $columnMap['post_type'] ?? null)),
            'blog_category_ids' => $categoryIds,
            'featured_image' => null,
            'content_idea' => $this->cellValue($row, $columnMap['content_idea'] ?? null) ?: null,
            'aff_link' => $this->cellValue($row, $columnMap['aff_link'] ?? null) ?: null,
            'coupon_codes' => $this->parseCouponCodes($couponRaw),
            'deals_data' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function finalizeArticleRecord(array $record): array
    {
        $record['deals_data'] = app(BlogDealsSyncService::class)->buildDealsPayload(
            is_array($record['deals_data'] ?? null) ? $record['deals_data'] : [],
            is_array($record['coupon_codes'] ?? null) ? $record['coupon_codes'] : [],
            filled($record['aff_link'] ?? null) ? (string) $record['aff_link'] : null,
            (string) ($record['brand_domain'] ?? ''),
        );

        return $record;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<string, string|null>
     */
    protected function parseDealFromRow(array $row, array $columnMap): array
    {
        return [
            'title' => $this->cellValue($row, $columnMap['deal_title'] ?? null),
            'description' => $this->cellValue($row, $columnMap['deal_description'] ?? null) ?: null,
            'coupon_code' => $this->cellValue($row, $columnMap['deal_coupon_code'] ?? null) ?: null,
            'shop_url' => $this->cellValue($row, $columnMap['deal_shop_url'] ?? null),
        ];
    }

    /**
     * @param  array<string, string|null>  $deal
     */
    protected function dealHasData(array $deal): bool
    {
        return filled($deal['title'] ?? null) || filled($deal['shop_url'] ?? null);
    }

    protected function parsePostType(string $raw): string
    {
        $value = mb_strtolower(trim($raw));

        return match ($value) {
            'blog', 'blogs', 'comparison', 'so sanh', 'so sánh' => Blog::TYPE_BLOG,
            'review', 'reviews', '' => Blog::TYPE_REVIEW,
            default => array_key_exists($value, Blog::postTypeOptions()) ? $value : Blog::TYPE_REVIEW,
        };
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
            'Domain brand,Loại bài viết,Danh mục bài viết,Nội dung / ý tưởng,Link Affiliate,Coupon code,Deal tiêu đề,Deal mô tả,Deal mã coupon,Deal link shop',
            'nike.com,Review,Shoes,Review giày chạy bộ mới,https://example.com/aff,SAVE10,Free shipping orders $50+,Miễn phí ship đơn từ $50,FREESHIP,https://nike.com/deal-ship',
            ',,,,,,20% Off Running Shoes,Giảm 20% giày chạy,RUN20,https://nike.com/deal-run',
            'amazon.com,Blog,Tech,So sánh laptop 2026,https://example.com/amazon,,Prime Day Laptop Deal,Deal laptop Prime Day,PRIME15,https://amazon.com/deal-laptop',
            ',,,,,,Extra 5% off accessories,Phụ kiện thêm giảm 5%,EXTRA5,https://amazon.com/deal-acc',
        ];

        return "\xEF\xBB\xBF".implode("\r\n", $lines);
    }
}
