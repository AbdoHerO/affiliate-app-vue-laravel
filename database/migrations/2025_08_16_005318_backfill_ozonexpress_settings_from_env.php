<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\AppSetting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Backfill OzonExpress settings from .env to database
        $settings = [
            'ozonexpress.customer_id' => env('OZONEXPRESS_ID'),
            'ozonexpress.api_key' => env('OZONEXPRESS_KEY'),
            'ozonexpress.base_url' => env('OZONEXPRESS_BASE_URL', 'https://api.ozonexpress.ma'),
            'ozonexpress.enabled' => env('OZONEXPRESS_ENABLED', true),
        ];

        foreach ($settings as $key => $value) {
            if ($value !== null) {
                AppSetting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => (string) $value,
                        'type' => is_bool($value) ? 'boolean' : 'string',
                        'is_encrypted' => in_array($key, ['ozonexpress.api_key']),
                        'description' => $this->getSettingDescription($key),
                    ]
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove OzonExpress settings
        AppSetting::whereIn('key', [
            'ozonexpress.customer_id',
            'ozonexpress.api_key',
            'ozonexpress.base_url',
            'ozonexpress.enabled',
        ])->delete();
    }

    /**
     * Get description for setting key
     */
    private function getSettingDescription(string $key): string
    {
        return match ($key) {
            'ozonexpress.customer_id' => 'OzonExpress Customer ID',
            'ozonexpress.api_key' => 'OzonExpress API Key (encrypted)',
            'ozonexpress.base_url' => 'OzonExpress API Base URL',
            'ozonexpress.enabled' => 'Enable OzonExpress integration',
            default => 'OzonExpress setting',
        };
    }
};
