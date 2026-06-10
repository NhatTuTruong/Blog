<?php

namespace App\Filament\Admin\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ProfileSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'filament.admin.pages.profile-settings';

    protected static ?string $navigationLabel = 'Hồ sơ cá nhân';

    protected static ?string $title = 'Hồ sơ cá nhân';

    protected static ?string $navigationGroup = 'Cài đặt';

    protected static ?int $navigationSort = 999;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->check();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $user = Filament::auth()->user();
        $this->form->fill([
            'avatar_path' => $user?->avatar_path,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Ảnh đại diện')
                    ->schema([
                        FileUpload::make('avatar_path')
                            ->label('Ảnh đại diện')
                            ->image()
                            ->imageEditor()
                            ->circleCropper()
                            ->directory('avatars')
                            ->disk('public')
                            ->maxSize(2048)
                            ->helperText('Kích thước tối đa 2MB.'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Lưu ảnh đại diện')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $user = Filament::auth()->user();
        if (! $user instanceof User) {
            return;
        }

        $state = $this->form->getState();
        $user->avatar_path = $state['avatar_path'] ?? null;
        $user->save();

        Notification::make()
            ->title('Đã cập nhật ảnh đại diện')
            ->success()
            ->send();
    }
}
