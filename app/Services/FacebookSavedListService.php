<?php

namespace App\Services;

use App\Models\FacebookSavedList;
use App\Models\User;
use Illuminate\Support\Collection;

class FacebookSavedListService
{
    public ?string $lastError = null;

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    public function save(string $name, array $records, ?User $user = null, ?int $existingId = null): ?FacebookSavedList
    {
        $this->lastError = null;
        $name = trim($name);
        $normalized = $this->normalizeRecords($records);

        if ($name === '') {
            $this->lastError = 'Vui lòng nhập tên danh sách.';

            return null;
        }

        if ($normalized->isEmpty()) {
            $this->lastError = 'Danh sách trống — cần ít nhất một bài hợp lệ.';

            return null;
        }

        if ($existingId) {
            $list = FacebookSavedList::query()->find($existingId);

            if (! $list) {
                $this->lastError = 'Không tìm thấy danh sách đã lưu.';

                return null;
            }

            $list->update([
                'name' => $name,
                'records' => $normalized->values()->all(),
                'record_count' => $normalized->count(),
            ]);

            return $list->fresh();
        }

        return FacebookSavedList::query()->create([
            'user_id' => $user?->id,
            'name' => $name,
            'records' => $normalized->values()->all(),
            'record_count' => $normalized->count(),
        ]);
    }

    public function delete(int $id): bool
    {
        $this->lastError = null;

        $deleted = FacebookSavedList::query()->whereKey($id)->delete();

        if (! $deleted) {
            $this->lastError = 'Không tìm thấy danh sách để xóa.';

            return false;
        }

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recordsForForm(int $id): array
    {
        $list = FacebookSavedList::query()->find($id);

        if (! $list) {
            return [];
        }

        return $this->normalizeRecords($list->records ?? [])->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return Collection<int, array<string, mixed>>
     */
    public function normalizeRecords(array $records): Collection
    {
        return collect($records)
            ->filter(fn (array $record): bool => $this->recordHasContent($record))
            ->map(function (array $record): array {
                $couponCodes = collect($record['coupon_codes'] ?? [])
                    ->map(fn (mixed $code): string => trim((string) $code))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $media = $record['media'] ?? null;
                if (is_array($media)) {
                    $media = $media[0] ?? null;
                }

                return [
                    'media' => is_string($media) && filled($media) ? trim($media) : null,
                    'brand_domain' => filled($record['brand_domain'] ?? null)
                        ? trim((string) $record['brand_domain'])
                        : null,
                    'content_idea' => filled($record['content_idea'] ?? null)
                        ? trim((string) $record['content_idea'])
                        : null,
                    'aff_link' => filled($record['aff_link'] ?? null)
                        ? trim((string) $record['aff_link'])
                        : null,
                    'coupon_codes' => $couponCodes,
                ];
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function recordHasContent(array $record): bool
    {
        $media = $record['media'] ?? null;
        if (is_array($media)) {
            $media = $media[0] ?? null;
        }

        if (is_string($media) && filled($media)) {
            return true;
        }

        if (filled($record['brand_domain'] ?? null)) {
            return true;
        }

        if (filled($record['content_idea'] ?? null)) {
            return true;
        }

        if (filled($record['aff_link'] ?? null)) {
            return true;
        }

        $coupons = $record['coupon_codes'] ?? [];

        return is_array($coupons) && collect($coupons)->filter(fn (mixed $c): bool => filled($c))->isNotEmpty();
    }
}
