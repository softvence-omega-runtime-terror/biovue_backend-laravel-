<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles
        $this->call([
            RolesTableSeeder::class,
            TermsAndConditionSeeder::class,
        ]);

        // Create users
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
                'status' => true,
            ]
        );
        $admin->assignRole('admin');

        $professional = User::updateOrCreate(
            ['email' => 'pro@example.com'],
            [
                'name' => 'Professional User',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
                'status' => true,
            ]
        );
        $professional->assignRole('professional');

        $individual = User::updateOrCreate(
            ['email' => 'individual@example.com'],
            [
                'name' => 'Individual User',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
                'status' => true,
            ]
        );
        $individual->assignRole('individual');

        // Now seed plans using user IDs
        $this->call([
            IndividualPlansSeeder::class,
            ProfessionalPlansSeeder::class,
        ]);
    }
}
