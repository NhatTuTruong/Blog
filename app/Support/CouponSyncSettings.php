<?php

namespace App\Support;

class CouponSyncSettings
{
    public static function dedupeDomainEnabled(): bool
    {
        return (bool) AdminSettings::get(
            'coupon_sync_dedupe_domain',
            config('coupon_sync.dedupe_domain', true),
        );
    }
}
