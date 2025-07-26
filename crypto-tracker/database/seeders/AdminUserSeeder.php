<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment('local')) {
            return;
        }

        if (!User::query()->where('email', 'admin@admin')->exists()) {
            User::factory()
                ->admin()
                ->create();
        }
    }
}
