<?php

namespace App\Support;

class SocialMediaRecordNormalizer
{
    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public static function forQueue(array $record): array
    {
        $media = $record['media'] ?? null;
        if (is_array($media)) {
            $media = $media[0] ?? null;
        }

        if (! is_string($media) || ! filled($media)) {
            $media = filled($record['image'] ?? null)
                ? (string) $record['image']
                : (filled($record['video'] ?? null) ? (string) $record['video'] : null);
        }

        $media = is_string($media) && filled($media) ? trim($media) : null;
        $split = static::splitMedia($media);

        $couponCodes = collect($record['coupon_codes'] ?? [])
            ->map(fn (mixed $code): string => trim((string) $code))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'brand_domain' => filled($record['brand_domain'] ?? null) ? trim((string) $record['brand_domain']) : null,
            'content_idea' => filled($record['content_idea'] ?? null) ? trim((string) $record['content_idea']) : null,
            'aff_link' => filled($record['aff_link'] ?? null) ? trim((string) $record['aff_link']) : null,
            'coupon_codes' => $couponCodes,
            'image' => $split['image'],
            'video' => $split['video'],
        ];
    }

    /**
     * @return array{image: ?string, video: ?string}
     */
    public static function splitMedia(?string $path): array
    {
        if ($path === null || $path === '') {
            return ['image' => null, 'video' => null];
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['mp4', 'mov', 'qt', 'm4v', 'webm'], true)) {
            return ['image' => null, 'video' => $path];
        }

        return ['image' => $path, 'video' => null];
    }
}
