<?php

namespace App\Filament\Concerns;

use App\Models\EmailTemplate;
use App\Support\FormDraftService;

trait HasEmailTemplateFormDraft
{
    use HasFormDraft;

    protected function formDraftKey(): string
    {
        if (property_exists($this, 'record') && $this->record?->getKey()) {
            return FormDraftService::key('email_template', $this->record->getKey());
        }

        return FormDraftService::key('email_template');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDraftBeforeRestore(array $data): array
    {
        if (filled($data['body'] ?? null)) {
            $data['body'] = EmailTemplate::prepareBodyForEditor((string) $data['body']);
        }

        return $data;
    }

    protected function formDraftIgnoredFields(): array
    {
        return ['is_active'];
    }
}
