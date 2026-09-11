<?php

namespace App\Filament\Admin\Resources\BlogResource\Pages;

use App\Filament\Admin\Resources\BlogResource;
use App\Filament\Admin\Resources\BlogResource\Concerns\SyncsBlogCategoryMetadata;
use App\Filament\Admin\Resources\BlogResource\Concerns\SyncsBlogDeals;
use App\Filament\Concerns\HasBlogFormDraft;
use Filament\Resources\Pages\CreateRecord;

class CreateBlog extends CreateRecord
{
    use HasBlogFormDraft;
    use SyncsBlogCategoryMetadata;
    use SyncsBlogDeals;

    protected static string $resource = BlogResource::class;

    protected function afterCreate(): void
    {
        $this->syncBlogDeals();
        $this->syncBlogCategoryMetadata();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getFormDraftDiscardAction(),
        ];
    }
}
