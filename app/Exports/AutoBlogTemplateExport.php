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

            'Loại bài viết',

            'Danh mục bài viết',

            'Nội dung / ý tưởng',

            'Link Affiliate',

            'Coupon code',

            'Deal tiêu đề',

            'Deal mô tả',

            'Deal mã coupon',

            'Deal link shop',

        ];

    }



    public function array(): array

    {

        return [

            [

                'nike.com',

                'Review',

                'Shoes',

                'Review giày chạy bộ mới',

                'https://example.com/aff',

                'SAVE10',

                'Free shipping orders $50+',

                'Miễn phí ship đơn từ $50',

                'FREESHIP',

                'https://nike.com/deal-ship',

            ],

            [

                '',

                '',

                '',

                '',

                '',

                '',

                '20% Off Running Shoes',

                'Giảm 20% giày chạy',

                'RUN20',

                'https://nike.com/deal-run',

            ],

            [

                'amazon.com',

                'Blog',

                'Tech',

                'So sánh laptop 2026',

                'https://example.com/amazon',

                '',

                'Prime Day Laptop Deal',

                'Deal laptop Prime Day',

                'PRIME15',

                'https://amazon.com/deal-laptop',

            ],

            [

                '',

                '',

                '',

                '',

                '',

                '',

                'Extra 5% off accessories',

                'Phụ kiện thêm giảm 5%',

                'EXTRA5',

                'https://amazon.com/deal-acc',

            ],

        ];

    }

}


