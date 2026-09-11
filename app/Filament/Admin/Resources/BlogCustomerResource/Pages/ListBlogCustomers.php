<?php

namespace App\Filament\Admin\Resources\BlogCustomerResource\Pages;

use App\Filament\Admin\Resources\BlogCustomerResource;
use Filament\Resources\Pages\ListRecords;

class ListBlogCustomers extends ListRecords
{
    protected static string $resource = BlogCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
