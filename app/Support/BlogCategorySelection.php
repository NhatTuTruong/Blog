<?php

namespace App\Support;

use App\Models\BlogCategory;

class BlogCategorySelection
{
    /**
     * @return array<int, int>
     */
    public static function normalizeIds(mixed $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        if (is_numeric($value)) {
            return [(int) $value];
        }

        if (is_string($value)) {
            $value = self::splitList($value);
        }

        if (! is_array($value)) {
            return [];
        }

        $options = BlogCategory::optionsForSelect();
        $byName = array_flip($options);

        return collect($value)
            ->flatMap(function (mixed $item) use ($byName): array {
                if (is_numeric($item)) {
                    return [(int) $item];
                }

                $text = trim((string) $item);
                if ($text === '') {
                    return [];
                }

                if (is_numeric($text)) {
                    return [(int) $text];
                }

                if (isset($byName[$text])) {
                    return [(int) $byName[$text]];
                }

                return [];
            })
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    public static function namesForIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return BlogCategory::query()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (BlogCategory $category): int => array_search($category->id, $ids, true) ?: PHP_INT_MAX)
            ->pluck('name')
            ->values()
            ->all();
    }

    public static function labelForIds(array $ids): ?string
    {
        $names = self::namesForIds($ids);

        return $names !== [] ? implode(', ', $names) : null;
    }

    /**
     * @return array<int, string>
     */
    protected static function splitList(string $value): array
    {
        return preg_split('/[,;|]+/', $value) ?: [];
    }
}
