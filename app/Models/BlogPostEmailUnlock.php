<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogPostEmailUnlock extends Model
{
    protected $fillable = [
        'blog_id',
        'email',
        'ip_address',
        'country',
        'user_agent',
    ];

    public function blog(): BelongsTo
    {
        return $this->belongsTo(Blog::class);
    }
}
