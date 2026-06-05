<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Lưu ý: User model có cast 'password' => 'hashed', nên truyền plain password.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Administrator',
                'password' => '12345678', // Sẽ tự hash qua cast (tránh double hash)
                'email_verified_at' => now(),
                'is_admin' => 1,
            ]
        );
    }
}
