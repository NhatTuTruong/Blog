<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoBlogSavedList extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'records',
        'record_count',
    ];

    protected $casts = [
        'records' => 'array',
        'record_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<int, string>
     */
    public static function optionsForSelect(): array
    {
        return static::query()
            ->latest('updated_at')
            ->get()
            ->mapWithKeys(fn (self $list): array => [
                $list->id => $list->name.' ('.$list->record_count.' bài)',
            ])
            ->all();
    }
}
