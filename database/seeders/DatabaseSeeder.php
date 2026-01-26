<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call other seeders first
        $this->call([
            RolesTableSeeder::class,
            TermsAndConditionSeeder::class,

            // Add your plan seeders here
            IndividualPlansSeeder::class,
            ProfessionalPlansSeeder::class,
        ]);

        // Create test user
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
            'status' => true,
        ]);

        // Create Admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
                'status' => true,
            ]
        );
        $admin->assignRole('Admin');

        // Create Professional user
        $professional = User::updateOrCreate(
            ['email' => 'pro@example.com'],
            [
                'name' => 'Professional User',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
                'status' => true,
            ]
        );
        $professional->assignRole('Professional');

        // Create Individual user
        $individual = User::updateOrCreate(
            ['email' => 'individual@example.com'],
            [
                'name' => 'Individual User',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
                'status' => true,
            ]
        );
        $individual->assignRole('Individual');
    }
}
