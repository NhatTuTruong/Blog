<?php

namespace App\Filament\Admin\Support;

use App\Models\FacebookQueueItem;
use App\Models\InstagramQueueItem;
use App\Models\PinterestQueueItem;
use App\Services\SocialMediaQueueRepublishService;
use App\Support\PublicStorage;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SocialMediaQueueTable
{
    public const STATUS_NOTE_LIMIT = 30;

    public static function mediaColumn(): ViewColumn
    {
        return ViewColumn::make('image_path')
            ->label('Media')
            ->view('filament.admin.components.social-media-queue-media-thumb');
    }

    public static function queueSourceColumn(): TextColumn
    {
        return TextColumn::make('queue_source')
            ->label('Nguồn')
            ->badge()
            ->formatStateUsing(fn (?string $state, InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $record): string => $record->queueSourceLabel())
            ->color(fn (?string $state, InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $record): string => $record->isAutoQueue() ? 'primary' : 'gray');
    }

    public static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label('Trạng thái')
            ->badge()
            ->width('8rem')
            ->wrap(false)
            ->formatStateUsing(fn (string $state, InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $record): string => static::truncate(
                $record->statusLabel(),
                self::STATUS_NOTE_LIMIT,
            ))
            ->color(fn (string $state): string => match ($state) {
                InstagramQueueItem::STATUS_PENDING, FacebookQueueItem::STATUS_PENDING, PinterestQueueItem::STATUS_PENDING => 'warning',
                InstagramQueueItem::STATUS_PROCESSING, FacebookQueueItem::STATUS_PROCESSING, PinterestQueueItem::STATUS_PROCESSING => 'info',
                InstagramQueueItem::STATUS_COMPLETED, FacebookQueueItem::STATUS_COMPLETED, PinterestQueueItem::STATUS_COMPLETED => 'success',
                InstagramQueueItem::STATUS_FAILED, FacebookQueueItem::STATUS_FAILED, PinterestQueueItem::STATUS_FAILED => 'danger',
                default => 'gray',
            })
            ->description(fn (InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $record): ?string => static::truncatedStatusNote($record))
            ->tooltip(function (InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $record): ?string {
                $note = static::statusNote($record);
                $label = $record->statusLabel();

                if ($note === null) {
                    return Str::length($label) > self::STATUS_NOTE_LIMIT ? $label : null;
                }

                $parts = [$label];
                if (Str::length($note) > self::STATUS_NOTE_LIMIT) {
                    $parts[] = $note;
                }

                return implode("\n", $parts);
            })
            ->extraCellAttributes([
                'class' => 'max-w-[8rem] [&_.fi-ta-text-item-description]:truncate',
            ]);
    }

    public static function republishAction(): Action
    {
        return Action::make('republish')
            ->label('Đăng lại')
            ->icon('heroicon-o-arrow-path')
            ->color('primary')
            ->tooltip('Đăng lại với cùng nội dung, ưu tiên chạy trước')
            ->visible(fn (InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $record): bool => app(SocialMediaQueueRepublishService::class)->canRepublish($record))
            ->requiresConfirmation()
            ->modalHeading('Đăng lại bài này')
            ->modalDescription('Bài sẽ được đưa vào hàng đợi với cùng caption, brand, tài khoản và loại media. Bài này sẽ được ưu tiên chạy trước các bài đang chờ.')
            ->modalSubmitActionLabel('Đăng lại')
            ->action(function (InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $record): void {
                try {
                    app(SocialMediaQueueRepublishService::class)->republish($record);

                    Notification::make()
                        ->title('Đã xếp hàng đăng lại')
                        ->body('Bài #'.$record->id.' sẽ được xử lý ưu tiên trong hàng đợi.')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Không thể đăng lại')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function detailAction(string $platform): Action
    {
        return Action::make('queueDetail')
            ->label('')
            ->icon('heroicon-o-eye')
            ->tooltip('Chi tiết')
            ->color('gray')
            ->modalHeading(fn (InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $record): string => 'Chi tiết hàng đợi #'.$record->id)
            ->modalWidth(MaxWidth::TwoExtraLarge)
            ->modalContent(fn (InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $record) => view(
                'filament.admin.components.social-media-queue-item-detail',
                ['record' => $record, 'platform' => $platform],
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Đóng');
    }

    /**
     * @return array<int, BulkActionGroup>
     */
    public static function bulkActions(): array
    {
        return [
            BulkActionGroup::make([
                DeleteBulkAction::make()
                    ->label('Xóa đã chọn')
                    ->modalHeading('Xóa các mục đã chọn')
                    ->modalDescription('Các bài đã chọn sẽ bị xóa khỏi hàng đợi. File video chưa đăng (nếu có) cũng sẽ bị xóa.')
                    ->before(function (Collection $records): void {
                        static::cleanupMediaBeforeDelete($records);
                    }),
            ]),
        ];
    }

    public static function truncatedStatusNote(InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $record): ?string
    {
        $note = static::statusNote($record);

        return $note === null ? null : static::truncate($note, self::STATUS_NOTE_LIMIT);
    }

    public static function statusNote(InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $record): ?string
    {
        if ($record->status === InstagramQueueItem::STATUS_FAILED && filled($record->error_message)) {
            return trim((string) $record->error_message);
        }

        if ($record->used_default_caption && $record->status === InstagramQueueItem::STATUS_COMPLETED) {
            return filled($record->error_message)
                ? trim((string) $record->error_message)
                : 'Đã đăng với nội dung mặc định.';
        }

        return null;
    }

    public static function truncate(?string $value, int $limit = self::STATUS_NOTE_LIMIT): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Str::limit($value, $limit);
    }

    /**
     * @param  Collection<int, Model>  $records
     */
    protected static function cleanupMediaBeforeDelete(Collection $records): void
    {
        foreach ($records as $record) {
            if (! $record instanceof InstagramQueueItem && ! $record instanceof FacebookQueueItem && ! $record instanceof PinterestQueueItem) {
                continue;
            }

            if (! filled($record->video_path)) {
                continue;
            }

            PublicStorage::delete((string) $record->video_path);

            $coverPath = preg_replace('/\.mp4$/i', '-cover.jpg', str_replace('\\', '/', (string) $record->video_path));
            if (is_string($coverPath) && PublicStorage::exists($coverPath)) {
                PublicStorage::delete($coverPath);
            }
        }
    }
}
