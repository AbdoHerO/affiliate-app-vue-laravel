<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AppSetting;

class CommissionSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            'commission.trigger_status' => [
                'value' => 'livree',
                'type' => 'string',
                'description' => 'Order status that triggers commission calculation (confirmee, livree, etc.)',
            ],
            'commission.cooldown_days' => [
                'value' => '7',
                'type' => 'integer', 
                'description' => 'Days to wait before commission becomes eligible for payout',
            ],
            'commission.return_policy' => [
                'value' => 'zero_on_return',
                'type' => 'string',
                'description' => 'How to handle commissions on returns: zero_on_return, keep_if_partial',
            ],
            'commission.default_rate' => [
                'value' => '10.0000',
                'type' => 'decimal',
                'description' => 'Default commission rate percentage when no product-specific rate is set',
            ],
            'commission.notify_affiliate' => [
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Send notifications to affiliates on commission status changes',
            ],
            'commission.auto_approve_threshold' => [
                'value' => '0',
                'type' => 'decimal',
                'description' => 'Auto-approve commissions below this amount (0 = manual approval required)',
            ],
        ];

        foreach ($settings as $key => $config) {
            AppSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $config['value'],
                    'type' => $config['type'],
                    'description' => $config['description'],
                    'is_encrypted' => false,
                ]
            );
        }
    }
}
