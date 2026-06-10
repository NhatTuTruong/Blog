<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Support\IntegrationSettingsForm;
use App\Filament\Concerns\AuthorizesPanelAccess;
use App\Models\User;
use App\Services\IntegrationSettingsPersistence;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class UserIntegrationSettings extends Page implements HasForms
{
    use AuthorizesPanelAccess;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static string $view = 'filament.admin.pages.user-integration-settings';

    protected static ?string $navigationLabel = 'Gemini & MXH & Email';

    protected static ?string $title = 'Cài đặt tùy chỉnh';

    protected static ?string $navigationGroup = 'Cài đặt';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return static::canAccessMemberFeatures();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) Filament::auth()->check();
    }

    public function mount(): void
    {
        $this->form->fill($this->persistence()->loadFormData());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(IntegrationSettingsForm::sections())
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Lưu cài đặt')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->persistence()->saveFormData($data);

        $this->form->fill($this->persistence()->loadFormData());

        Notification::make()
            ->title('Đã lưu cài đặt tích hợp')
            ->success()
            ->send();
    }

    protected function persistence(): IntegrationSettingsPersistence
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            throw new \RuntimeException('Người dùng chưa đăng nhập.');
        }

        return new IntegrationSettingsPersistence($user->id);
    }
}
