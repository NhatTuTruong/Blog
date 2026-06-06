<?php

namespace App\Filament\Admin\Support;

use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

class AdminTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100])
            ->striped()
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->emptyStateIcon('heroicon-o-table-cells');
    }
}
