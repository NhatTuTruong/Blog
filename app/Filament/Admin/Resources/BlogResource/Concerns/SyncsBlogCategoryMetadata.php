<?php

namespace App\Filament\Admin\Resources\BlogResource\Concerns;

trait SyncsBlogCategoryMetadata
{
    protected function syncBlogCategoryMetadata(): void
    {
        $blog = $this->record->fresh(['blogCategories']);

        if (! $blog) {
            return;
        }

        $ids = $blog->blogCategories->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        if ($ids === []) {
            return;
        }

        $blog->syncBlogCategories($ids);
    }
}
