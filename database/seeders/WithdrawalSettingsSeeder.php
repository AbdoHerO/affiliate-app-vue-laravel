<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AppSetting;

class WithdrawalSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'withdrawal.currency',
                'value' => 'MAD',
                'type' => 'string',
                'description' => 'Default currency for withdrawals',
                'is_encrypted' => false,
            ],
            [
                'key' => 'withdrawal.proof_required',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Whether payment proof is required for withdrawals',
                'is_encrypted' => false,
            ],
            [
                'key' => 'withdrawal.notify_affiliate',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Whether to notify affiliates about withdrawal status changes',
                'is_encrypted' => false,
            ],
            [
                'key' => 'withdrawal.default_method',
                'value' => 'bank_transfer',
                'type' => 'string',
                'description' => 'Default withdrawal method',
                'is_encrypted' => false,
            ],
            [
                'key' => 'withdrawal.min_amount',
                'value' => '100.00',
                'type' => 'decimal',
                'description' => 'Minimum withdrawal amount',
                'is_encrypted' => false,
            ],
            [
                'key' => 'withdrawal.max_amount',
                'value' => '50000.00',
                'type' => 'decimal',
                'description' => 'Maximum withdrawal amount',
                'is_encrypted' => false,
            ],
        ];

        foreach ($settings as $setting) {
            AppSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
