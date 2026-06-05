<?php

namespace App\Filament\Admin\Pages\Concerns;

use Filament\Support\Enums\MaxWidth;

trait HasFullWidthContent
{
    protected function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }
}

