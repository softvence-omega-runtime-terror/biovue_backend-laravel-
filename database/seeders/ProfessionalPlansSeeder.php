<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProfessionalPlan;

class ProfessionalPlansSeeder extends Seeder
{
    public function run()
    {
        $plans = [
            [
                'name' => 'Tier 1 Professional',
                'billing_cycle' => 'monthly',
                'price' => 250,
                'status' => true,
                'member_limit' => 8,
                'duration' => 7,
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
                'billing_cycle' => 'monthly',
                'price' => 750,
                'status' => true,
                'member_limit' => 25,
                'duration' => 7,
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
                'billing_cycle' => 'monthly',
                'price' => 3500,
                'status' => true,
                'member_limit' => 150,
                'duration' => 7,
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
                'billing_cycle' => 'custom',
                'price' => null,
                'status' => true,
                'member_limit' => null,
                'duration' => null,
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
            ProfessionalPlan::updateOrCreate(
                ['name' => $plan['name']],
                $plan
            );
        }
    }
}
