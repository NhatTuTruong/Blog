<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PinterestTemplateExport implements FromArray, WithHeadings
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
            ['nike.com', 'Ý tưởng Pin giày chạy bộ mới', 'https://example.com/aff', 'SAVE10'],
            ['', 'Deal cuối tuần cho cửa hàng', 'https://example.com/deal', 'DEAL20;EXTRA5'],
        ];
    }
}
