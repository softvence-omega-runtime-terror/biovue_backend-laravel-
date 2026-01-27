<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IndividualPlan;
use App\Models\User;

class IndividualPlansSeeder extends Seeder
{
    public function run()
    {
        // safe: admin user fetch
        $admin = User::where('email', 'admin@example.com')->first();

        if (!$admin) {
            return; // admin user nai, seeding skip korbe
        }

        $userId = $admin->id;

        $plans = [
            [
                'name' => 'Free Trial',
                'user_id' => $userId,
                'billing_cycle' => 'days',
                'duration' => 7,
                'price' => 0,
                'status' => true,
                'features' => [
                    'BetaCore AI core projections',
                    'Limited tracking',
                    'Limited insights',
                    'Limited projections',
                    'Limited reports',
                    'No customer support',
                    'No business tools',
                    'Assessment consult & 3 free reports'
                ],
            ],
            [
                'name' => 'Plus',
                'user_id' => $userId,
                'billing_cycle' => 'monthly',
                'price' => 29,
                'status' => true,
                'features' => [
                    'Full AI & BetaCore AI projections',
                    'Progress tracking',
                    'Insights',
                    'Projections',
                    'Reports',
                    'Assessment business tools',
                    'Customer support',
                    'Assessment consult & 5 free reports'
                ],
            ],
            [
                'name' => 'Premium',
                'user_id' => $userId,
                'billing_cycle' => 'monthly',
                'price' => 35,
                'status' => true,
                'features' => [
                    'EVERYTHING IN PLUS',
                    'Advanced tracking',
                    'Advanced insights',
                    'Advanced projections',
                    'Advanced reports',
                    'Assessment consult & 8 free reports'
                ],
            ],
        ];

        foreach ($plans as $plan) {
            IndividualPlan::updateOrCreate(
                ['name' => $plan['name']],
                $plan
            );
        }
    }
}
