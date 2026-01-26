<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IndividualPlan;

class IndividualPlansSeeder extends Seeder
{
    public function run()
    {
        $plans = [
            [
                'name' => 'Free Trial',
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
