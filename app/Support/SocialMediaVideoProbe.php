<?php

namespace App\Support;

class SocialMediaVideoProbe
{
    /**
     * @return array{0: int, 1: int}|null
     */
    public function dimensions(string $absolutePath): ?array
    {
        $info = $this->analyze($absolutePath);

        $width = (int) ($info['video']['resolution_x'] ?? 0);
        $height = (int) ($info['video']['resolution_y'] ?? 0);

        if ($width <= 0 || $height <= 0) {
            return null;
        }

        return [$width, $height];
    }

    public function duration(string $absolutePath): ?float
    {
        $info = $this->analyze($absolutePath);
        $duration = (float) ($info['playtime_seconds'] ?? 0);

        return $duration > 0 ? $duration : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function analyze(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            return [];
        }

        $getID3 = new \getID3();
        $getID3->setOption([
            'option_md5_data' => false,
            'option_md5_data_source' => false,
            'option_tags_html' => false,
        ]);

        $info = $getID3->analyze($absolutePath);

        return is_array($info) ? $info : [];
    }
}
