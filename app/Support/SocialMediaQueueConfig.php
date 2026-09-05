<?php

namespace App\Support;

class SocialMediaQueueConfig
{
    public static function staleMinutes(): int
    {
        return max(1, (int) config('social_media.queue_stale_minutes', 10));
    }

    public static function staleMessage(): string
    {
        $minutes = self::staleMinutes();

        return 'Quá '.$minutes.' phút ở trạng thái «Đang đăng» — hủy và chuyển sang bài tiếp theo.';
    }

    public static function phpTimeLimitSeconds(): int
    {
        return (self::staleMinutes() + 1) * 60;
    }

    public static function schedulerOverlapMinutes(): int
    {
        return self::staleMinutes() + 5;
    }
}
