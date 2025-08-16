<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding app settings...');

        // OzonExpress settings (placeholder values)
        $this->seedOzonExpressSettings();

        // Add other settings here as needed
        // $this->seedEmailSettings();
        // $this->seedPaymentSettings();

        $this->command->info('App settings seeded successfully.');
    }

    /**
     * Seed OzonExpress settings
     */
    protected function seedOzonExpressSettings(): void
    {
        $settings = [
            [
                'key' => 'ozonexpress.customer_id',
                'value' => env('OZONEXPRESS_ID', ''),
                'type' => 'string',
                'description' => 'OzonExpress Customer ID',
                'is_encrypted' => false,
            ],
            [
                'key' => 'ozonexpress.api_key',
                'value' => env('OZONEXPRESS_KEY', ''),
                'type' => 'string',
                'description' => 'OzonExpress API Key',
                'is_encrypted' => true,
            ],
        ];

        foreach ($settings as $setting) {
            // Only create if the setting doesn't exist and has a value
            if (!empty($setting['value'])) {
                AppSetting::updateOrCreate(
                    ['key' => $setting['key']],
                    $setting
                );
                
                $this->command->info("Created setting: {$setting['key']}");
            } else {
                $this->command->warn("Skipped setting {$setting['key']} (no value provided)");
            }
        }
    }

    /**
     * Example: Seed email settings
     */
    protected function seedEmailSettings(): void
    {
        $settings = [
            [
                'key' => 'email.smtp_host',
                'value' => env('MAIL_HOST', 'localhost'),
                'type' => 'string',
                'description' => 'SMTP Host',
                'is_encrypted' => false,
            ],
            [
                'key' => 'email.smtp_port',
                'value' => env('MAIL_PORT', '587'),
                'type' => 'integer',
                'description' => 'SMTP Port',
                'is_encrypted' => false,
            ],
            [
                'key' => 'email.smtp_username',
                'value' => env('MAIL_USERNAME', ''),
                'type' => 'string',
                'description' => 'SMTP Username',
                'is_encrypted' => false,
            ],
            [
                'key' => 'email.smtp_password',
                'value' => env('MAIL_PASSWORD', ''),
                'type' => 'string',
                'description' => 'SMTP Password',
                'is_encrypted' => true,
            ],
        ];

        foreach ($settings as $setting) {
            if (!empty($setting['value'])) {
                AppSetting::updateOrCreate(
                    ['key' => $setting['key']],
                    $setting
                );
            }
        }
    }

    /**
     * Example: Seed payment settings
     */
    protected function seedPaymentSettings(): void
    {
        $settings = [
            [
                'key' => 'payment.stripe_public_key',
                'value' => env('STRIPE_KEY', ''),
                'type' => 'string',
                'description' => 'Stripe Public Key',
                'is_encrypted' => false,
            ],
            [
                'key' => 'payment.stripe_secret_key',
                'value' => env('STRIPE_SECRET', ''),
                'type' => 'string',
                'description' => 'Stripe Secret Key',
                'is_encrypted' => true,
            ],
            [
                'key' => 'payment.paypal_client_id',
                'value' => env('PAYPAL_CLIENT_ID', ''),
                'type' => 'string',
                'description' => 'PayPal Client ID',
                'is_encrypted' => false,
            ],
            [
                'key' => 'payment.paypal_client_secret',
                'value' => env('PAYPAL_CLIENT_SECRET', ''),
                'type' => 'string',
                'description' => 'PayPal Client Secret',
                'is_encrypted' => true,
            ],
        ];

        foreach ($settings as $setting) {
            if (!empty($setting['value'])) {
                AppSetting::updateOrCreate(
                    ['key' => $setting['key']],
                    $setting
                );
            }
        }
    }
}
