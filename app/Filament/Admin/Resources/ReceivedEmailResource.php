<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ReceivedEmailResource\Pages;
use App\Models\ReceivedEmail;
use App\Models\User;
use App\Services\IncomingMailService;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReceivedEmailResource extends Resource
{
    protected static ?string $model = ReceivedEmail::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox';

    protected static ?string $navigationLabel = 'Nhận mail';

    protected static ?string $modelLabel = 'Email nhận';

    protected static ?string $pluralModelLabel = 'Nhận mail';

    protected static ?string $navigationGroup = 'Email';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Thông tin')
                    ->schema([
                        Infolists\Components\TextEntry::make('from_email')
                            ->label('Người gửi')
                            ->getStateUsing(fn (ReceivedEmail $record): string => $record->fromDisplay())
                            ->columnSpan(['default' => 2, 'lg' => 1]),
                        Infolists\Components\TextEntry::make('to')
                            ->label('Người nhận')
                            ->getStateUsing(fn (ReceivedEmail $record): string => $record->recipientsDisplay())
                            ->columnSpan(['default' => 2, 'lg' => 1]),
                        Infolists\Components\TextEntry::make('subject')
                            ->label('Tiêu đề')
                            ->columnSpan(['default' => 2, 'lg' => 1]),
                        Infolists\Components\TextEntry::make('received_at')
                            ->label('Thời gian nhận')
                            ->dateTime('d/m/Y H:i')
                            ->columnSpan(1),
                        Infolists\Components\TextEntry::make('is_seen')
                            ->label('Trạng thái')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Đã đọc' : 'Chưa đọc')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'warning')
                            ->columnSpan(1),
                    ])
                    ->columns(['default' => 2, 'lg' => 3])
                    ->compact(),
                Infolists\Components\Section::make('Tệp đính kèm')
                    ->schema([
                        Infolists\Components\ViewEntry::make('attachments_list')
                            ->hiddenLabel()
                            ->view('filament.admin.received-emails.attachments')
                            ->columnSpanFull(),
                    ])
                    ->compact()
                    ->visible(fn (ReceivedEmail $record): bool => $record->attachments_count > 0
                        || ReceivedEmail::normalizeAttachments($record->attachments) !== []),
                Infolists\Components\Section::make('Nội dung')
                    ->schema([
                        Infolists\Components\ViewEntry::make('body_preview')
                            ->hiddenLabel()
                            ->view('filament.admin.received-emails.body')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('received_at', 'desc')
            ->emptyStateHeading('Chưa có email')
            ->emptyStateDescription('Email được đồng bộ nền theo IMAP_AUTO_SYNC_SECONDS. Bấm «Đồng bộ hộp thư» để cập nhật ngay. Lần đầu tải tối đa IMAP_SYNC_LIMIT email; các lần sau chỉ email mới.')
            ->columns([
                Tables\Columns\IconColumn::make('is_seen')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-o-envelope-open')
                    ->falseIcon('heroicon-o-envelope')
                    ->trueColor('gray')
                    ->falseColor('primary'),
                Tables\Columns\TextColumn::make('from_email')
                    ->label('Người gửi')
                    ->description(fn (ReceivedEmail $record): ?string => $record->from_name)
                    ->searchable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Tiêu đề')
                    ->searchable()
                    ->limit(50)
                    ->wrap(),
                Tables\Columns\TextColumn::make('received_at')
                    ->label('Nhận lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('attachments_count')
                    ->label('Đính kèm')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? (string) $state : '—')
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_seen')
                    ->label('Đã đọc'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Xóa đã chọn'),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('sync')
                    ->label('Đồng bộ hộp thư')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (): void {
                        $service = app(IncomingMailService::class);
                        cache()->forget('imap_inbox_sync_global');
                        $result = $service->sync((int) config('imap.sync_limit', 50));

                        if (($result['new'] + $result['updated']) === 0 && $result['errors'] !== []) {
                            Notification::make()
                                ->title('Đồng bộ thất bại')
                                ->body($service->lastError ?? implode("\n", array_slice($result['errors'], 0, 3)))
                                ->danger()
                                ->send();

                            return;
                        }

                        $body = match ($result['mode']) {
                            'incremental' => "{$result['new']} email mới".($result['updated'] > 0 ? ", {$result['updated']} cập nhật" : ''),
                            'initial' => "Lần đầu: đã tải {$result['new']} email".($result['updated'] > 0 ? " ({$result['updated']} cập nhật)" : ''),
                            default => 'Không có email mới.',
                        };
                        if ($result['errors'] !== []) {
                            $body .= ' Một số email lỗi: '.count($result['errors']).'.';
                        }

                        Notification::make()
                            ->title('Đồng bộ hoàn tất')
                            ->body($body)
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReceivedEmails::route('/'),
            'view' => Pages\ViewReceivedEmail::route('/{record}'),
        ];
    }
}
