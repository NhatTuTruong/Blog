<?php

namespace App\Filament\Admin\Resources\BlogDealResource\Pages;

use App\Filament\Admin\Resources\BlogDealResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlogDeal extends EditRecord
{
    protected static string $resource = BlogDealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label(''),
        ];
    }
}
