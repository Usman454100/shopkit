<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Prices are intentionally null — not yet set (see docs/08-OPEN-QUESTIONS.md #1).
     * max_stores/max_products are placeholder quotas, easily changed later since
     * feature gating reads features_json at request time, not at deploy time.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'billing_cycle' => 'monthly',
                'features_json' => [
                    'multi_store' => false,
                    'payment_gateways' => ['cod', 'bank_transfer'],
                    'push_notifications' => false,
                    'wishlist' => false,
                    'reviews' => false,
                    'staff_accounts' => false,
                    'payroll' => false,
                    'promotions' => false,
                    'dedicated_hosting' => false,
                    'max_stores' => 1,
                    'max_products' => null,
                ],
            ],
            [
                'name' => 'Pro',
                'billing_cycle' => 'monthly',
                'features_json' => [
                    'multi_store' => true,
                    'payment_gateways' => ['cod', 'bank_transfer', 'jazzcash', 'easypaisa'],
                    'push_notifications' => true,
                    'wishlist' => true,
                    'reviews' => true,
                    'staff_accounts' => true,
                    'payroll' => false,
                    'promotions' => false,
                    'dedicated_hosting' => false,
                    'max_stores' => 5,
                    'max_products' => null,
                ],
            ],
            [
                'name' => 'Enterprise',
                'billing_cycle' => 'monthly',
                'features_json' => [
                    'multi_store' => true,
                    'payment_gateways' => ['cod', 'bank_transfer', 'jazzcash', 'easypaisa'],
                    'push_notifications' => true,
                    'wishlist' => true,
                    'reviews' => true,
                    'staff_accounts' => true,
                    'payroll' => true,
                    'promotions' => true,
                    'dedicated_hosting' => true,
                    'max_stores' => 5,
                    'max_products' => null,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::firstOrCreate(['name' => $plan['name']], $plan);
        }
    }
}
