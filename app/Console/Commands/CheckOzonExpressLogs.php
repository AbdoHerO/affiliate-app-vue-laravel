<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CheckOzonExpressLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ozonexpress:logs {--lines=50} {--follow}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check OzonExpress API logs for debugging';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $logPath = storage_path('logs/laravel.log');
        
        if (!File::exists($logPath)) {
            $this->error('Log file not found: ' . $logPath);
            return 1;
        }

        $lines = $this->option('lines');
        $follow = $this->option('follow');

        if ($follow) {
            $this->info('Following OzonExpress logs (Ctrl+C to stop)...');
            $this->line('');
            
            // Use tail -f to follow the log file
            $command = "tail -f {$logPath} | grep -i ozonexpress";
            passthru($command);
        } else {
            $this->info("Showing last {$lines} OzonExpress log entries:");
            $this->line('');
            
            // Read the log file and filter for OzonExpress entries
            $logContent = File::get($logPath);
            $logLines = explode("\n", $logContent);
            
            $ozonExpressLines = array_filter($logLines, function ($line) {
                return stripos($line, 'ozonexpress') !== false;
            });
            
            $recentLines = array_slice($ozonExpressLines, -$lines);
            
            if (empty($recentLines)) {
                $this->warn('No OzonExpress log entries found.');
                $this->line('');
                $this->line('This could mean:');
                $this->line('1. No API calls have been made yet');
                $this->line('2. OzonExpress is disabled (mock mode)');
                $this->line('3. Logs have been cleared');
                return 0;
            }
            
            foreach ($recentLines as $line) {
                // Color code different log levels
                if (stripos($line, 'ERROR') !== false) {
                    $this->error($line);
                } elseif (stripos($line, 'WARNING') !== false) {
                    $this->warn($line);
                } elseif (stripos($line, 'INFO') !== false) {
                    $this->info($line);
                } else {
                    $this->line($line);
                }
            }
            
            $this->line('');
            $this->line('Total OzonExpress log entries found: ' . count($ozonExpressLines));
            
            // Show summary
            $this->showLogSummary($ozonExpressLines);
        }

        return 0;
    }

    /**
     * Show a summary of the log entries
     */
    private function showLogSummary(array $logLines): void
    {
        $apiCalls = 0;
        $apiResponses = 0;
        $errors = 0;
        $mockCalls = 0;

        foreach ($logLines as $line) {
            if (stripos($line, 'OzonExpress API Call') !== false) {
                $apiCalls++;
            }
            if (stripos($line, 'OzonExpress API Response') !== false) {
                $apiResponses++;
            }
            if (stripos($line, 'ERROR') !== false) {
                $errors++;
            }
            if (stripos($line, 'Mock parcel') !== false) {
                $mockCalls++;
            }
        }

        $this->line('');
        $this->line('=== LOG SUMMARY ===');
        $this->line("API Calls: {$apiCalls}");
        $this->line("API Responses: {$apiResponses}");
        $this->line("Errors: {$errors}");
        $this->line("Mock Calls: {$mockCalls}");
        
        if ($apiCalls > 0 && $apiResponses > 0) {
            $this->info('✅ API calls are being made to OzonExpress');
        } elseif ($mockCalls > 0) {
            $this->warn('🔧 Only mock calls detected - OzonExpress is disabled');
        } else {
            $this->error('❌ No API activity detected');
        }
    }
}
