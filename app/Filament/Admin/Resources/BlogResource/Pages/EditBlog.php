<?php

namespace App\Filament\Admin\Resources\BlogResource\Pages;

use App\Filament\Admin\Resources\BlogResource;
use App\Filament\Concerns\HasBlogFormDraft;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlog extends EditRecord
{
    use HasBlogFormDraft;

    protected static string $resource = BlogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getFormDraftDiscardAction(),
            Actions\DeleteAction::make(),
        ];
    }
}
