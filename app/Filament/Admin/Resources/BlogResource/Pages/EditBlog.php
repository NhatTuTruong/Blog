<?php

namespace App\Filament\Admin\Resources\BlogResource\Pages;

use App\Filament\Admin\Resources\BlogResource;
use App\Filament\Admin\Resources\BlogResource\Concerns\SyncsBlogCategoryMetadata;
use App\Filament\Concerns\HasBlogFormDraft;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlog extends EditRecord
{
    use HasBlogFormDraft;
    use SyncsBlogCategoryMetadata;

    protected static string $resource = BlogResource::class;

    protected function afterSave(): void
    {
        $this->syncBlogCategoryMetadata();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getFormDraftDiscardAction(),
            Actions\DeleteAction::make()->label(''),
        ];
    }
}
