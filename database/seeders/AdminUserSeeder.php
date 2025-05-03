<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminEmail = 'admin@example.com';

        // Check if the user already exists
        $existingUser = User::where('email', $adminEmail)->first();

        if (!$existingUser) {
            User::create([
                'name' => 'Admin',
                'email' => $adminEmail,
                'phone' => '01700000000',
                'password' => Hash::make('password'), // Change this to a secure password
                'role' => 'admin',
                'is_active' => true,
            ]);

            $this->command->info('✅ Admin user created successfully.');
        } else {
            $this->command->warn('⚠️ Admin user already exists.');
        }
    }
}
