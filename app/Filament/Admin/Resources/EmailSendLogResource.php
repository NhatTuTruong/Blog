<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EmailSendLogResource\Pages;
use App\Models\EmailSendLog;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmailSendLogResource extends Resource
{
    protected static ?string $model = EmailSendLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Lịch sử gửi mail';

    protected static ?string $modelLabel = 'Lịch sử';

    protected static ?string $pluralModelLabel = 'Lịch sử gửi mail';

    protected static ?string $navigationGroup = 'Email';

    protected static ?int $navigationSort = 4;

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

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Thông tin gửi')
                    ->schema([
                        Infolists\Components\TextEntry::make('template_name')
                            ->label('Mẫu')
                            ->columnSpan(['default' => 2, 'lg' => 1]),
                        Infolists\Components\TextEntry::make('sent_count')
                            ->label('Gửi thành công')
                            ->badge()
                            ->color('success')
                            ->columnSpan(1),
                        Infolists\Components\TextEntry::make('failed_count')
                            ->label('Thất bại')
                            ->badge()
                            ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                            ->columnSpan(1),
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Người gửi (admin)')
                            ->placeholder('—')
                            ->columnSpan(['default' => 2, 'lg' => 1]),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Thời gian gửi')
                            ->dateTime('d/m/Y H:i')
                            ->columnSpan(1),
                        Infolists\Components\TextEntry::make('recipients')
                            ->label('Người nhận')
                            ->getStateUsing(fn (EmailSendLog $record): string => EmailSendLog::formatList($record->recipients))
                            ->placeholder('—')
                            ->columnSpan(['default' => 2, 'lg' => 2]),
                    ])
                    ->columns(['default' => 2, 'lg' => 3])
                    ->compact(),
                Infolists\Components\Section::make('Nội dung đã gửi')
                    ->schema([
                        Infolists\Components\TextEntry::make('subject')
                            ->label('Tiêu đề')
                            ->columnSpan(['default' => 2, 'lg' => 3]),
                        Infolists\Components\TextEntry::make('body')
                            ->label('Nội dung')
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 2, 'lg' => 3])
                    ->compact(),
                Infolists\Components\Section::make('Tệp đính kèm')
                    ->schema([
                        Infolists\Components\ViewEntry::make('attachments_list')
                            ->hiddenLabel()
                            ->view('filament.admin.email-send-logs.attachments')
                            ->columnSpanFull(),
                    ])
                    ->compact()
                    ->visible(fn (EmailSendLog $record): bool => EmailSendLog::normalizeArray($record->attachments) !== []),
                Infolists\Components\Section::make('Lỗi')
                    ->schema([
                        Infolists\Components\TextEntry::make('errors')
                            ->label('')
                            ->getStateUsing(function (EmailSendLog $record): string {
                                $errors = EmailSendLog::normalizeArray($record->errors);

                                return $errors !== [] ? implode("\n", $errors) : '—';
                            })
                            ->columnSpanFull(),
                    ])
                    ->compact()
                    ->visible(fn (EmailSendLog $record): bool => EmailSendLog::normalizeArray($record->errors) !== []),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('template_name')
                    ->label('Mẫu')
                    ->searchable(),
                Tables\Columns\TextColumn::make('recipients')
                    ->label('Số người nhận')
                    ->formatStateUsing(fn (mixed $state): int => count(EmailSendLog::normalizeArray($state)))
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('sent_count')
                    ->label('OK')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('failed_count')
                    ->label('Lỗi')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Tiêu đề')
                    ->limit(35),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Người gửi')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Thời gian')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailSendLogs::route('/'),
            'view' => Pages\ViewEmailSendLog::route('/{record}'),
        ];
    }
}
