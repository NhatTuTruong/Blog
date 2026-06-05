<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Sử dụng DB query trực tiếp để tránh trigger boot() method
        $users = \Illuminate\Support\Facades\DB::table('users')
            ->whereNull('code')
            ->get();
        
        foreach ($users as $user) {
            do {
                $code = str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
            } while (\Illuminate\Support\Facades\DB::table('users')->where('code', $code)->exists());
            
            \Illuminate\Support\Facades\DB::table('users')
                ->where('id', $user->id)
                ->update(['code' => $code]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('users')->update(['code' => null]);
    }
};
