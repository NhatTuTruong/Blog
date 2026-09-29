<?php

namespace App\Support;

class CouponDescriptionGenerator
{
    /** @var array<int, string> */
    private const TEMPLATES = [
        'Apply at checkout for instant savings on your order.',
        'Limited-time offer — grab the discount before it expires.',
        'Works on eligible items sitewide when you shop today.',
        'Extra savings for your next purchase online.',
        'Copy the code and use it at the store checkout page.',
        'A quick way to save on brands you already love.',
        'Valid for online orders — see site for full terms.',
        'Stack with seasonal sales where the merchant allows it.',
        'Popular with shoppers this week — worth trying now.',
        'New-customer friendly deal with straightforward checkout savings.',
        'Redeem in one click after you copy and open the shop.',
        'Hand-picked promo to stretch your budget a little further.',
    ];

    public static function forSeed(string $seed): string
    {
        $templates = self::TEMPLATES;
        $index = abs(crc32($seed)) % count($templates);

        return $templates[$index];
    }

    public static function forCouponDeal(int $dealId, string $couponCode, ?string $customDescription = null): string
    {
        $custom = trim((string) $customDescription);
        if ($custom !== '') {
            return $custom;
        }

        return self::forSeed($dealId.'|'.mb_strtoupper(trim($couponCode)));
    }
}
