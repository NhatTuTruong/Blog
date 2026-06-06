<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AutoBlogTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'Domain brand',
            'Danh mục bài viết',
            'Nội dung / ý tưởng',
            'Link AFF',
            'Coupon code',
        ];
    }

    public function array(): array
    {
        return [
            ['nike.com', 'Shoes', 'Review giày chạy bộ mới', 'https://example.com/aff', 'SAVE10'],
            ['amazon.com', 'Tech', 'Top laptop 2026', '', 'DEAL20;EXTRA5'],
        ];
    }
}
