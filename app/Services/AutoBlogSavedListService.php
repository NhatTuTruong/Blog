<?php

namespace App\Services;

use App\Models\AutoBlogSavedList;
use App\Models\User;
use Illuminate\Support\Collection;

class AutoBlogSavedListService
{
    public ?string $lastError = null;

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    public function save(string $name, array $records, ?User $user = null, ?int $existingId = null): ?AutoBlogSavedList
    {
        $this->lastError = null;
        $name = trim($name);
        $normalized = $this->normalizeRecords($records);

        if ($name === '') {
            $this->lastError = 'Vui lòng nhập tên danh sách.';

            return null;
        }

        if ($normalized->isEmpty()) {
            $this->lastError = 'Danh sách trống — cần ít nhất một Domain brand.';

            return null;
        }

        if ($existingId) {
            $list = AutoBlogSavedList::query()->find($existingId);

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

        return AutoBlogSavedList::query()->create([
            'user_id' => $user?->id,
            'name' => $name,
            'records' => $normalized->values()->all(),
            'record_count' => $normalized->count(),
        ]);
    }

    public function delete(int $id): bool
    {
        $this->lastError = null;

        $deleted = AutoBlogSavedList::query()->whereKey($id)->delete();

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
        $list = AutoBlogSavedList::query()->find($id);

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
            ->filter(fn (array $record): bool => filled($record['brand_domain'] ?? null))
            ->map(function (array $record): array {
                $couponCodes = collect($record['coupon_codes'] ?? [])
                    ->map(fn (mixed $code): string => trim((string) $code))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $featuredImage = $record['featured_image'] ?? null;
                if (is_array($featuredImage)) {
                    $featuredImage = $featuredImage[array_key_first($featuredImage)] ?? null;
                }

                return [
                    'featured_image' => filled($featuredImage) ? trim((string) $featuredImage) : null,
                    'brand_domain' => trim((string) $record['brand_domain']),
                    'blog_category_id' => filled($record['blog_category_id'] ?? null)
                        ? (int) $record['blog_category_id']
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
}
