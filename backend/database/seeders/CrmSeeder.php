<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CrmSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::updateOrCreate(
            ['email' => 'crm@crm.com'],
            [
                'name' => 'CRM Admin',
                'password' => Hash::make('crm@crm.com'),
                'is_admin' => false,
                'crm_role' => 'owner',
                'status' => 'active',
                'messages_balance' => 50000,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("CRM Owner user created/updated with ID: {$owner->id}");
    }
}
