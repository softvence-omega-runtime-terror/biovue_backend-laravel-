<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;
use App\Models\User;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user fetch (safe)
        $admin = User::where('email', 'admin@example.com')->first();

        if (!$admin) {
            return; // admin na thakle skip
        }

        $userId = $admin->id;

        $plans = [

            // ================= INDIVIDUAL PLANS =================
            [
                'name' => 'Free Trial',
                'plan_type' => 'individual',
                'user_id' => $userId,
                'billing_cycle' => 'days',
                'duration' => 7,
                'price' => 0,
                'member_limit' => null,
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
                'plan_type' => 'individual',
                'user_id' => $userId,
                'billing_cycle' => 'monthly',
                'duration' => null,
                'price' => 29,
                'member_limit' => null,
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
                'plan_type' => 'individual',
                'user_id' => $userId,
                'billing_cycle' => 'monthly',
                'duration' => null,
                'price' => 35,
                'member_limit' => null,
                'status' => true,
                'features' => [
                    'Everything in Plus',
                    'Advanced tracking',
                    'Advanced insights',
                    'Advanced projections',
                    'Advanced reports',
                    'Assessment consult & 8 free reports'
                ],
            ],

            // ================= PROFESSIONAL PLANS =================
            [
                'name' => 'Tier 1 Professional',
                'plan_type' => 'professional',
                'user_id' => $userId,
                'billing_cycle' => 'monthly',
                'duration' => 7,
                'price' => 250,
                'member_limit' => 8,
                'status' => true,
                'features' => [
                    'Up to 8 client accounts',
                    '2 Projections per client/month',
                    'Client progress tracking',
                    'White-label reports',
                    'Email support',
                    'Basic analytics dashboard',
                    'Core body visualization & health insights',
                    'Limited customization'
                ],
            ],
            [
                'name' => 'Tier 2 Professional',
                'plan_type' => 'professional',
                'user_id' => $userId,
                'billing_cycle' => 'monthly',
                'duration' => 7,
                'price' => 750,
                'member_limit' => 25,
                'status' => true,
                'features' => [
                    'Up to 25 client accounts',
                    'Everything in Tier 1',
                    'Advanced analytics dashboard',
                    'API access',
                    'Priority email support',
                    'Custom branding',
                    'Team collaboration (2 seats)',
                    'Custom onboarding (via email)'
                ],
            ],
            [
                'name' => 'Tier 3 Professional',
                'plan_type' => 'professional',
                'user_id' => $userId,
                'billing_cycle' => 'monthly',
                'duration' => 7,
                'price' => 3500,
                'member_limit' => 150,
                'status' => true,
                'features' => [
                    'Up to 150 client accounts',
                    'Everything in Tier 2',
                    '4 Projections per client/month',
                    'Priority phone & email support',
                    'Team collaboration (5 seats)',
                    'Custom integrations',
                    'Quarterly business reviews'
                ],
            ],
            [
                'name' => 'Enterprise',
                'plan_type' => 'professional',
                'user_id' => $userId,
                'billing_cycle' => 'custom',
                'duration' => null,
                'price' => 0,
                'member_limit' => null,
                'status' => true,
                'features' => [
                    'Unlimited client accounts',
                    'Everything in Tier 3',
                    'Dedicated account manager',
                    'Custom SLA agreements',
                    'On-premise deployment option',
                    'Unlimited team seats',
                    'White-glove onboarding',
                    'Custom feature development',
                    'Quarterly business reviews'
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                [
                    'name' => $plan['name'],
                    'plan_type' => $plan['plan_type'],
                ],
                $plan
            );
        }
    }




}