<?php

namespace App\Filament\Admin\Concerns;

use App\Models\FacebookAccount;
use App\Models\InstagramAccount;
use App\Models\PinterestAccount;
use App\Services\FacebookQueueService;
use App\Services\InstagramQueueService;
use App\Services\PinterestQueueService;
use App\Support\FacebookSettings;
use App\Support\InstagramSettings;
use App\Support\PinterestSettings;
use App\Support\PinterestUi;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Notifications\Notification;

trait ManagesCrossPlatformPublish
{
    /**
     * @return array<string, string>
     */
    protected function crossPlatformLabels(): array
    {
        $labels = [
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
        ];

        if (PinterestUi::enabled()) {
            $labels['pinterest'] = 'Pinterest';
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    protected function crossPlatformOptionsFor(string $currentPlatform): array
    {
        return collect($this->crossPlatformLabels())
            ->except($currentPlatform)
            ->all();
    }

    protected function crossPlatformIsSelected(Get $get, string $platform): bool
    {
        return in_array($platform, (array) ($get('also_publish_platforms') ?? []), true);
    }

    /**
     * @param  array<int, string>  $platforms
     */
    protected function crossPlatformHasActiveQueue(array $platforms): bool
    {
        foreach ($platforms as $platform) {
            if ($this->crossPlatformQueueService($platform)->hasActiveQueue()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $platforms
     */
    protected function crossPlatformActiveQueueSummary(array $platforms): string
    {
        return collect($platforms)
            ->map(function (string $platform): ?string {
                $service = $this->crossPlatformQueueService($platform);

                if (! $service->hasActiveQueue()) {
                    return null;
                }

                return ($this->crossPlatformLabels()[$platform] ?? $platform)
                    .': '.$service->activeQueueSummary();
            })
            ->filter()
            ->implode(' · ');
    }

    protected function crossPlatformQueueService(string $platform): InstagramQueueService|FacebookQueueService|PinterestQueueService
    {
        return match ($platform) {
            'facebook' => app(FacebookQueueService::class),
            'pinterest' => app(PinterestQueueService::class),
            default => app(InstagramQueueService::class),
        };
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function crossPlatformPublishFormSchema(string $currentPlatform): array
    {
        $otherPlatforms = array_keys($this->crossPlatformOptionsFor($currentPlatform));

        if ($otherPlatforms === []) {
            return [];
        }

        return [
            Section::make('Đăng đồng thời lên mạng khác')
                ->description('Dùng cùng danh sách bài đang soạn để xếp hàng thêm cho các mạng đã chọn bên dưới.')
                ->schema([
                    CheckboxList::make('also_publish_platforms')
                        ->label('Chọn thêm mạng xã hội')
                        ->options($this->crossPlatformOptionsFor($currentPlatform))
                        ->columns(count($otherPlatforms))
                        ->live(),
                    Placeholder::make('cross_active_queue_warning')
                        ->label('Cảnh báo hàng đợi')
                        ->content(fn (Get $get): string => 'Đang có hàng đợi: '.$this->crossPlatformActiveQueueSummary((array) ($get('also_publish_platforms') ?? [])))
                        ->visible(fn (Get $get): bool => $this->crossPlatformHasActiveQueue((array) ($get('also_publish_platforms') ?? []))),
                    Checkbox::make('cross_confirm_active_queues')
                        ->label('Tôi hiểu và vẫn muốn thêm vào các hàng đợi đang chạy')
                        ->accepted()
                        ->visible(fn (Get $get): bool => $this->crossPlatformHasActiveQueue((array) ($get('also_publish_platforms') ?? [])))
                        ->dehydrated(fn (Get $get): bool => $this->crossPlatformHasActiveQueue((array) ($get('also_publish_platforms') ?? []))),
                    CheckboxList::make('cross_instagram_account_ids')
                        ->label('Tài khoản Instagram')
                        ->options(fn (): array => InstagramAccount::optionsForSelect())
                        ->default(fn (): array => InstagramAccount::enabledConfiguredIds())
                        ->columns(1)
                        ->required()
                        ->visible(fn (Get $get): bool => $this->crossPlatformIsSelected($get, 'instagram')
                            && InstagramAccount::optionsForSelect() !== [])
                        ->helperText('Mặc định chọn tất cả tài khoản Instagram đã bật.'),
                    Placeholder::make('cross_instagram_not_configured')
                        ->label('Tài khoản Instagram')
                        ->content('Instagram chưa cấu hình — vào Cài đặt tùy chỉnh để thêm tài khoản.')
                        ->visible(fn (Get $get): bool => $this->crossPlatformIsSelected($get, 'instagram')
                            && InstagramAccount::optionsForSelect() === []),
                    CheckboxList::make('cross_facebook_account_ids')
                        ->label('Trang Facebook')
                        ->options(fn (): array => FacebookAccount::optionsForSelect())
                        ->default(fn (): array => FacebookAccount::enabledConfiguredIds())
                        ->columns(1)
                        ->required()
                        ->visible(fn (Get $get): bool => $this->crossPlatformIsSelected($get, 'facebook')
                            && FacebookAccount::optionsForSelect() !== [])
                        ->helperText('Mặc định chọn tất cả trang Facebook đã bật.'),
                    Placeholder::make('cross_facebook_not_configured')
                        ->label('Trang Facebook')
                        ->content('Facebook chưa cấu hình — vào Cài đặt tùy chỉnh để thêm Page.')
                        ->visible(fn (Get $get): bool => $this->crossPlatformIsSelected($get, 'facebook')
                            && FacebookAccount::optionsForSelect() === []),
                    Select::make('cross_pinterest_account_id')
                        ->label('Tài khoản Pinterest')
                        ->options(fn (): array => PinterestAccount::optionsForSelect())
                        ->default(fn (): ?string => (string) (PinterestAccount::enabledConfiguredIds()[0] ?? '') ?: null)
                        ->searchable()
                        ->live()
                        ->required()
                        ->visible(fn (Get $get): bool => $this->crossPlatformIsSelected($get, 'pinterest')
                            && PinterestAccount::optionsForSelect() !== [])
                        ->helperText('Dùng token đã lưu để tải danh sách board.'),
                    CheckboxList::make('cross_pinterest_board_ids')
                        ->label('Bảng (Board) Pinterest')
                        ->options(fn (Get $get): array => $this->pinterestBoardOptionsForAccount($get('cross_pinterest_account_id')))
                        ->columns(1)
                        ->required()
                        ->visible(fn (Get $get): bool => $this->crossPlatformIsSelected($get, 'pinterest')
                            && PinterestAccount::optionsForSelect() !== []
                            && $this->pinterestBoardOptionsForAccount($get('cross_pinterest_account_id')) !== [])
                        ->helperText('Chọn một hoặc nhiều board để đăng Pin.'),
                    Placeholder::make('cross_pinterest_no_boards')
                        ->label('Bảng (Board) Pinterest')
                        ->content(fn (Get $get): string => filled($get('cross_pinterest_account_id'))
                            ? 'Không tải được board — kiểm tra token hoặc nhấn «Kiểm tra Pinterest».'
                            : 'Chưa có tài khoản Pinterest — vào Cài đặt tùy chỉnh.')
                        ->visible(fn (Get $get): bool => $this->crossPlatformIsSelected($get, 'pinterest')
                            && (PinterestAccount::optionsForSelect() === []
                                || (filled($get('cross_pinterest_account_id'))
                                    && $this->pinterestBoardOptionsForAccount($get('cross_pinterest_account_id')) === []))),
                ])
                ->collapsible()
                ->collapsed(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<string, array{success: bool, message: string}>
     */
    protected function publishRecordsToCrossPlatforms(string $sourcePlatform, array $records, Carbon $startAt, array $data): array
    {
        $selected = (array) ($data['also_publish_platforms'] ?? []);
        $user = Filament::auth()->user();
        $results = [];

        if (in_array('instagram', $selected, true)) {
            $results['instagram'] = $this->enqueueCrossPlatformInstagram($records, $startAt, $data, $user);
        }

        if (in_array('facebook', $selected, true)) {
            $results['facebook'] = $this->enqueueCrossPlatformFacebook($records, $startAt, $data, $user);
        }

        if (PinterestUi::enabled() && in_array('pinterest', $selected, true)) {
            $results['pinterest'] = $this->enqueueCrossPlatformPinterest($records, $startAt, $data, $user);
        }

        return $results;
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array{success: bool, message: string}
     */
    protected function enqueueCrossPlatformInstagram(array $records, Carbon $startAt, array $data, mixed $user): array
    {
        if (! InstagramSettings::isConfigured()) {
            return ['success' => false, 'message' => 'Instagram chưa cấu hình.'];
        }

        $accountIds = collect($data['cross_instagram_account_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($accountIds === []) {
            return ['success' => false, 'message' => 'Chưa chọn tài khoản Instagram.'];
        }

        $service = app(InstagramQueueService::class);
        $batchId = $service->enqueue($records, $user, $startAt, $accountIds);

        if ($batchId === null) {
            return ['success' => false, 'message' => $service->lastError ?? 'Không thể xếp hàng Instagram.'];
        }

        $postCount = count($records);

        return [
            'success' => true,
            'message' => $postCount * count($accountIds).' lượt Instagram',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array{success: bool, message: string}
     */
    protected function enqueueCrossPlatformFacebook(array $records, Carbon $startAt, array $data, mixed $user): array
    {
        if (! FacebookSettings::isConfigured()) {
            return ['success' => false, 'message' => 'Facebook chưa cấu hình.'];
        }

        $accountIds = collect($data['cross_facebook_account_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($accountIds === []) {
            return ['success' => false, 'message' => 'Chưa chọn trang Facebook.'];
        }

        $service = app(FacebookQueueService::class);
        $batchId = $service->enqueue($records, $user, $startAt, $accountIds);

        if ($batchId === null) {
            return ['success' => false, 'message' => $service->lastError ?? 'Không thể xếp hàng Facebook.'];
        }

        $postCount = count($records);

        return [
            'success' => true,
            'message' => $postCount * count($accountIds).' lượt Facebook',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array{success: bool, message: string}
     */
    protected function enqueueCrossPlatformPinterest(array $records, Carbon $startAt, array $data, mixed $user): array
    {
        if (! PinterestSettings::isConfigured()) {
            return ['success' => false, 'message' => 'Pinterest chưa cấu hình.'];
        }

        $accountId = (int) ($data['cross_pinterest_account_id'] ?? 0);
        $boardIds = collect($data['cross_pinterest_board_ids'] ?? [])
            ->map(fn (mixed $id): string => trim((string) $id))
            ->filter()
            ->values()
            ->all();

        if ($accountId <= 0) {
            return ['success' => false, 'message' => 'Chưa chọn tài khoản Pinterest.'];
        }

        if ($boardIds === []) {
            return ['success' => false, 'message' => 'Chưa chọn board Pinterest.'];
        }

        $boardOptions = $this->pinterestBoardOptionsForAccount($accountId);
        $targets = collect($boardIds)
            ->map(fn (string $boardId): array => [
                'account_id' => $accountId,
                'board_id' => $boardId,
                'board_name' => $boardOptions[$boardId] ?? null,
            ])
            ->all();

        $service = app(PinterestQueueService::class);
        $batchId = $service->enqueue($records, $user, $startAt, $targets);

        if ($batchId === null) {
            return ['success' => false, 'message' => $service->lastError ?? 'Không thể xếp hàng Pinterest.'];
        }

        return [
            'success' => true,
            'message' => count($records) * count($boardIds).' Pin Pinterest',
        ];
    }

    /**
     * @param  array<string, array{success: bool, message: string}>  $crossResults
     */
    protected function notifyCrossPlatformPublishResults(string $primaryLabel, string $primaryMessage, array $crossResults): void
    {
        if ($crossResults === []) {
            return;
        }

        $lines = [$primaryLabel.': '.$primaryMessage];
        $hasFailure = false;

        foreach ($crossResults as $platform => $result) {
            $label = $this->crossPlatformLabels()[$platform] ?? $platform;
            $lines[] = $result['success']
                ? $label.': '.$result['message']
                : $label.' lỗi: '.$result['message'];

            if (! $result['success']) {
                $hasFailure = true;
            }
        }

        $notification = Notification::make()
            ->title($hasFailure ? 'Đăng đa nền tảng — một số mạng lỗi' : 'Đã xếp hàng đa nền tảng')
            ->body(implode("\n", $lines));

        if ($hasFailure) {
            $notification->warning()->send();

            return;
        }

        $notification->success()->send();
    }
}
