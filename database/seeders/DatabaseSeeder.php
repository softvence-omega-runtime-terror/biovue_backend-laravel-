<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles and Terms
        $this->call([
            RolesTableSeeder::class,
            TermsAndConditionSeeder::class,
        ]);

        // 2. Users create
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
                'user_type' => 'professional',        
                'profession_type' => 'trainer_coach',
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
                'user_type' => 'individual',       
                'profession_type' => null,
            ]
        );
        $individual->assignRole('individual');

        // 3. Plans seed using admin
        $this->call([
            PlansSeeder::class,
        ]);
    }
}