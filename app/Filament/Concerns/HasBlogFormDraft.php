<?php

namespace App\Filament\Concerns;

use App\Support\FormDraftService;

trait HasBlogFormDraft
{
    use HasFormDraft;

    protected function formDraftKey(): string
    {
        if (property_exists($this, 'record') && $this->record?->getKey()) {
            return FormDraftService::key('blog', $this->record->getKey());
        }

        return FormDraftService::key('blog');
    }

    protected function formDraftIgnoredFields(): array
    {
        $ignored = ['is_published'];

        if (! property_exists($this, 'record') || ! $this->record?->getKey()) {
            $ignored[] = 'created_at';
        }

        return $ignored;
    }
}
