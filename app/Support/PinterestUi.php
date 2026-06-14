<?php

namespace App\Support;

class PinterestUi
{
    public static function enabled(): bool
    {
        return (bool) config('features.pinterest_ui', false);
    }
}
