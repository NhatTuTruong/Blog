<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Lưu')
                ->formId('form'),
            Actions\Action::make('resetPassword')
                ->label('Reset Password')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reset mật khẩu')
                ->modalDescription('Bạn có chắc muốn reset mật khẩu cho user này? Mật khẩu mới sẽ được hiển thị sau khi reset.')
                ->form([
                    \Filament\Forms\Components\TextInput::make('new_password')
                        ->label('Mật khẩu mới')
                        ->default(fn () => Str::random(12))
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'password' => bcrypt($data['new_password']),
                    ]);
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Đã reset mật khẩu')
                        ->success()
                        ->body('Mật khẩu mới: ' . $data['new_password'])
                        ->persistent()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Lưu'),
            $this->getCancelFormAction(),
        ];
    }
}
