<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FacebookTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'Domain brand',
            'Nội dung / ý tưởng',
            'Link Affiliate',
            'Coupon code',
        ];
    }

    public function array(): array
    {
        return [
            ['nike.com', 'Giới thiệu giày chạy bộ mới', 'https://example.com/aff', 'SAVE10'],
            ['', 'Đăng deal cuối tuần cho cửa hàng', 'https://example.com/deal', 'DEAL20;EXTRA5'],
        ];
    }
}
