<?php

namespace App\Filament\Admin\Resources\BlogDealResource\Pages;

use App\Filament\Admin\Resources\BlogDealResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBlogDeals extends ListRecords
{
    protected static string $resource = BlogDealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Thêm deal'),
        ];
    }
}
