<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'SuperAdmin@suqlive.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Suqlive'),
                'is_admin' => true,
                'email_verified_at' => now(),
                'subscription_plan' => 'free',
                'subscription_status' => 'active',
            ]
        );
    }
}
