<?php

namespace App\Filament\Concerns;

use App\Support\FormDraftService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

trait HasFormDraft
{
    protected bool $formDraftRestored = false;

    abstract protected function formDraftKey(): string;

    protected function formDraftIgnoredFields(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDraftBeforeRestore(array $data): array
    {
        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function formDraftHasContent(array $data): bool
    {
        return collect($data)
            ->except($this->formDraftIgnoredFields())
            ->filter(function (mixed $value): bool {
                if ($value === null || $value === false || $value === '') {
                    return false;
                }

                if (is_array($value)) {
                    return $value !== [];
                }

                return true;
            })
            ->isNotEmpty();
    }

    protected function afterFill(): void
    {
        $this->restoreFormDraft();
    }

    protected function afterCreate(): void
    {
        $this->clearFormDraft();
    }

    protected function afterSave(): void
    {
        $this->clearFormDraft();
    }

    public function dehydrate(): void
    {
        $this->persistFormDraft();
    }

    public function updated(mixed $property): void
    {
        if (! is_string($property) || ! str_starts_with($property, 'data')) {
            return;
        }

        $this->persistFormDraft();
    }

    protected function restoreFormDraft(): void
    {
        $userId = $this->formDraftUserId();

        if ($userId === null) {
            return;
        }

        $data = FormDraftService::get($userId, $this->formDraftKey());

        if ($data === null || ! $this->formDraftHasContent($data)) {
            return;
        }

        $data = $this->mutateFormDraftBeforeRestore($data);
        $this->form->fill($data);
        $this->formDraftRestored = true;

        Notification::make()
            ->title('Đã khôi phục bản nháp')
            ->body('Nội dung chỉnh sửa trước đó đã được tải lại. Bạn có thể tiếp tục chỉnh sửa.')
            ->info()
            ->send();
    }

    protected function persistFormDraft(): void
    {
        $userId = $this->formDraftUserId();

        if ($userId === null) {
            return;
        }

        try {
            $data = $this->formDraftDataForStorage();
        } catch (\Throwable) {
            $data = is_array($this->data ?? null) ? $this->data : [];
        }

        if (! $this->formDraftHasContent($data)) {
            FormDraftService::delete($userId, $this->formDraftKey());

            return;
        }

        FormDraftService::save($userId, $this->formDraftKey(), $data);
    }

    protected function clearFormDraft(): void
    {
        $userId = $this->formDraftUserId();

        if ($userId === null) {
            return;
        }

        FormDraftService::delete($userId, $this->formDraftKey());
    }

    protected function formDraftExists(): bool
    {
        $userId = $this->formDraftUserId();

        if ($userId === null) {
            return false;
        }

        $data = FormDraftService::get($userId, $this->formDraftKey());

        return $data !== null && $this->formDraftHasContent($data);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formDraftDataForStorage(): array
    {
        return $this->form->getState();
    }

    protected function formDraftUserId(): ?int
    {
        $user = Filament::auth()->user();

        return $user?->getKey();
    }

    protected function getFormDraftDiscardAction(): Action
    {
        return Action::make('discardFormDraft')
            ->label('Xóa bản nháp')
            ->icon('heroicon-o-archive-box-x-mark')
            ->color('gray')
            ->visible(fn (): bool => $this->formDraftExists())
            ->requiresConfirmation()
            ->modalHeading('Xóa bản nháp?')
            ->modalDescription('Nội dung lưu tạm sẽ bị xóa và form được tải lại từ đầu.')
            ->modalSubmitActionLabel('Xóa bản nháp')
            ->action(function (): void {
                $this->clearFormDraft();
                $this->resetFormAfterDraftDiscard();

                Notification::make()
                    ->title('Đã xóa bản nháp')
                    ->body('Form đã được tải lại từ đầu.')
                    ->success()
                    ->send();
            });
    }

    protected function resetFormAfterDraftDiscard(): void
    {
        if (method_exists($this, 'fillForm')) {
            $this->fillForm();

            return;
        }

        if (method_exists($this, 'resetPageFormAfterDraftDiscard')) {
            $this->resetPageFormAfterDraftDiscard();

            return;
        }

        $this->redirect(static::getUrl(), navigate: true);
    }

    public function saveFormDraft(): void
    {
        $this->persistFormDraft();
    }
}
