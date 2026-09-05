<?php

namespace App\Support;

class SocialMediaImageDeliveryError
{
    public static function shouldRetryWithDefaultImage(?string $message): bool
    {
        if (! filled($message)) {
            return false;
        }

        $message = strtolower((string) $message);

        return str_contains($message, '9004')
            || str_contains($message, '2207052')
            || str_contains($message, 'only photo or video')
            || str_contains($message, 'meta không tải được ảnh')
            || str_contains($message, 'content-type không phải jpeg')
            || str_contains($message, 'không truy cập được url ảnh')
            || str_contains($message, 'không kiểm tra được url ảnh')
            || str_contains($message, 'file ảnh')
            || str_contains($message, 'photo upload failed')
            || str_contains($message, 'photo failed')
            || str_contains($message, 'invalid image')
            || str_contains($message, 'could not be downloaded');
    }
}
