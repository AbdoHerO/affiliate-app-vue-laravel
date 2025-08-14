<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ToggleOzonExpress extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ozonexpress:toggle {--enable} {--disable}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Toggle OzonExpress integration on/off for testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            $this->error('.env file not found');
            return 1;
        }

        $envContent = File::get($envPath);
        
        if ($this->option('enable')) {
            $newContent = $this->updateEnvValue($envContent, 'OZONEXPRESS_ENABLED', 'true');
            File::put($envPath, $newContent);
            $this->info('✅ OzonExpress integration ENABLED');
            $this->line('   Real API calls will be made to OzonExpress');
        } elseif ($this->option('disable')) {
            $newContent = $this->updateEnvValue($envContent, 'OZONEXPRESS_ENABLED', 'false');
            File::put($envPath, $newContent);
            $this->info('🔧 OzonExpress integration DISABLED');
            $this->line('   Mock parcels will be created for testing');
        } else {
            // Show current status
            $currentValue = $this->getEnvValue($envContent, 'OZONEXPRESS_ENABLED');
            $status = $currentValue === 'true' ? 'ENABLED' : 'DISABLED';
            $icon = $currentValue === 'true' ? '✅' : '🔧';
            
            $this->info("Current OzonExpress status: {$icon} {$status}");
            $this->line('');
            $this->line('Usage:');
            $this->line('  php artisan ozonexpress:toggle --enable   # Enable real API calls');
            $this->line('  php artisan ozonexpress:toggle --disable  # Use mock responses');
        }

        return 0;
    }

    /**
     * Update an environment variable value
     */
    private function updateEnvValue(string $envContent, string $key, string $value): string
    {
        $pattern = "/^{$key}=.*$/m";
        $replacement = "{$key}={$value}";
        
        if (preg_match($pattern, $envContent)) {
            return preg_replace($pattern, $replacement, $envContent);
        } else {
            // Add the key if it doesn't exist
            return $envContent . "\n{$replacement}";
        }
    }

    /**
     * Get an environment variable value
     */
    private function getEnvValue(string $envContent, string $key): ?string
    {
        $pattern = "/^{$key}=(.*)$/m";
        
        if (preg_match($pattern, $envContent, $matches)) {
            return trim($matches[1]);
        }
        
        return null;
    }
}
