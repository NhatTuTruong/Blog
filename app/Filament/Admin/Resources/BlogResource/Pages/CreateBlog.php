<?php

namespace App\Filament\Admin\Resources\BlogResource\Pages;

use App\Filament\Admin\Resources\BlogResource;
use App\Filament\Concerns\HasBlogFormDraft;
use Filament\Resources\Pages\CreateRecord;

class CreateBlog extends CreateRecord
{
    use HasBlogFormDraft;

    protected static string $resource = BlogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getFormDraftDiscardAction(),
        ];
    }
}
