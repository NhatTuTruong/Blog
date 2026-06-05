<?php

namespace App\Support;

use App\Models\FormDraft;

class FormDraftService
{
    public static function key(string $scope, int|string|null $id = null): string
    {
        if ($id === null || $id === 'create') {
            return $scope.'.create';
        }

        return $scope.'.'.$id;
    }

    public static function save(int $userId, string $key, array $data): void
    {
        FormDraft::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'draft_key' => $key,
            ],
            [
                'data' => $data,
            ],
        );
    }

    public static function get(int $userId, string $key): ?array
    {
        $draft = FormDraft::query()
            ->where('user_id', $userId)
            ->where('draft_key', $key)
            ->first();

        $data = $draft?->data;

        return is_array($data) ? $data : null;
    }

    public static function delete(int $userId, string $key): void
    {
        FormDraft::query()
            ->where('user_id', $userId)
            ->where('draft_key', $key)
            ->delete();
    }

    public static function exists(int $userId, string $key): bool
    {
        return FormDraft::query()
            ->where('user_id', $userId)
            ->where('draft_key', $key)
            ->exists();
    }
}
