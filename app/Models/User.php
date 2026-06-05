<?php

namespace App\Models;

use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    use HasFactory, Notifiable;

    /** Danh mục blog mặc định (dùng cho AI / chọn category khi đăng bài). */
    public static function defaultCategoryNames(): array
    {
        return config('default_categories.names', [
            'Webhosting', 'Travel & Hotel', 'Shoes', 'Sports', 'Stationery', 'Skin Care', 'Pets',
            'Jewelry & Watches', 'Garden', 'Health & Beauty', 'Toys', 'Gifts & Flowers', 'Food & Beverages',
            'Event Planners', 'Electronics', 'Departmental', 'Car', 'Business', 'Books', 'Kids',
            'Accessories', 'Automotive', 'Aviation Assistance', 'Art & Crafts', 'Apparel & Clothing',
            'Tech', 'Home Accessories', 'Fitness',
        ]);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'avatar_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
    ];

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return asset('storage/' . ltrim($this->avatar_path, '/'));
    }
}
