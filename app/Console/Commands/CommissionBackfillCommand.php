<?php

namespace App\Console\Commands;

use App\Jobs\CommissionBackfillJob;
use App\Models\AppSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Console command for commission backfill operations
 */
class CommissionBackfillCommand extends Command
{
    protected $signature = 'commission:backfill 
                           {--mode=dry-run : Mode: dry-run or apply}
                           {--chunk-size=100 : Number of records to process per chunk}
                           {--force : Force execution without confirmation}';

    protected $description = 'Backfill historical commissions with margin-based calculation';

    public function handle(): int
    {
        $mode = $this->option('mode');
        $chunkSize = (int) $this->option('chunk-size');
        $force = $this->option('force');

        // Validate mode
        if (!in_array($mode, ['dry-run', 'apply'])) {
            $this->error('Invalid mode. Use "dry-run" or "apply".');
            return 1;
        }

        $isDryRun = $mode === 'dry-run';

        // Check if commission strategy is set to margin
        $strategy = AppSetting::get('commission.strategy', 'legacy');
        if ($strategy !== 'margin') {
            $this->warn('Commission strategy is not set to "margin". Current strategy: ' . $strategy);
            if (!$this->confirm('Do you want to continue anyway?')) {
                return 1;
            }
        }

        // Display configuration
        $this->info('Commission Backfill Configuration:');
        $this->table(['Setting', 'Value'], [
            ['Mode', $mode],
            ['Dry Run', $isDryRun ? 'Yes' : 'No'],
            ['Chunk Size', $chunkSize],
            ['Commission Strategy', $strategy],
        ]);

        // Safety confirmation for apply mode
        if (!$isDryRun && !$force) {
            $this->warn('⚠️  APPLY MODE WILL CREATE ADJUSTMENT RECORDS IN THE DATABASE');
            $this->warn('This operation will:');
            $this->warn('- Create commission adjustment entries');
            $this->warn('- Affect future withdrawal calculations');
            $this->warn('- Generate audit trail records');
            
            if (!$this->confirm('Are you sure you want to proceed with APPLY mode?')) {
                $this->info('Operation cancelled.');
                return 0;
            }

            if (!$this->confirm('Have you backed up the database?')) {
                $this->error('Please backup the database before running in APPLY mode.');
                return 1;
            }
        }

        // Show recent backfill reports
        $this->showRecentReports();

        // Execute backfill
        $this->info('Starting commission backfill...');
        
        try {
            $job = new CommissionBackfillJob($isDryRun, $chunkSize);
            $job->handle();
            
            $this->info('✅ Commission backfill completed successfully!');
            
            // Show results
            $this->showLatestReport();
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Commission backfill failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function showRecentReports(): void
    {
        $files = Storage::disk('local')->files('commission_backfills');
        $reportFiles = array_filter($files, fn($file) => str_contains($file, 'report_'));
        
        if (empty($reportFiles)) {
            $this->info('No previous backfill reports found.');
            return;
        }

        // Sort by filename (which contains timestamp)
        rsort($reportFiles);
        $recentFiles = array_slice($reportFiles, 0, 3);

        $this->info('Recent Backfill Reports:');
        foreach ($recentFiles as $file) {
            $content = Storage::disk('local')->get($file);
            $report = json_decode($content, true);
            
            if ($report) {
                $this->line(sprintf(
                    '- %s: %s mode, %d examined, %d adjustments needed (%.1f%% accuracy)',
                    $report['batch_id'],
                    $report['dry_run'] ? 'DRY-RUN' : 'APPLY',
                    $report['metrics']['examined'],
                    $report['metrics']['adjustments_needed'],
                    $report['summary']['accuracy_rate']
                ));
            }
        }
        $this->line('');
    }

    private function showLatestReport(): void
    {
        $files = Storage::disk('local')->files('commission_backfills');
        $reportFiles = array_filter($files, fn($file) => str_contains($file, 'report_'));
        
        if (empty($reportFiles)) {
            return;
        }

        // Get the latest report
        rsort($reportFiles);
        $latestFile = $reportFiles[0];
        
        $content = Storage::disk('local')->get($latestFile);
        $report = json_decode($content, true);
        
        if (!$report) {
            return;
        }

        $this->info('📊 Backfill Results Summary:');
        $this->table(['Metric', 'Value'], [
            ['Batch ID', $report['batch_id']],
            ['Mode', $report['dry_run'] ? 'DRY-RUN' : 'APPLY'],
            ['Records Examined', number_format($report['metrics']['examined'])],
            ['Adjustments Needed', number_format($report['metrics']['adjustments_needed'])],
            ['Adjustments Created', number_format($report['metrics']['adjustments_created'])],
            ['Total Delta (MAD)', number_format($report['metrics']['total_delta'], 2)],
            ['Accuracy Rate', $report['summary']['accuracy_rate'] . '%'],
            ['Average Delta (MAD)', number_format($report['summary']['average_delta'], 2)],
            ['Errors', number_format($report['metrics']['errors'])],
        ]);

        // Show CSV file location
        $csvFiles = array_filter($files, fn($file) => 
            str_contains($file, 'commission_backfill_') && str_ends_with($file, '.csv')
        );
        rsort($csvFiles);
        
        if (!empty($csvFiles)) {
            $latestCsv = $csvFiles[0];
            $this->info("📄 Detailed CSV report: storage/app/{$latestCsv}");
        }

        // Recommendations
        if ($report['dry_run'] && $report['metrics']['adjustments_needed'] > 0) {
            $this->warn('💡 Recommendations:');
            $this->warn('- Review the CSV report for detailed breakdown');
            $this->warn('- Validate a few calculations manually');
            $this->warn('- Run with --mode=apply when ready to create adjustments');
        } elseif (!$report['dry_run'] && $report['metrics']['adjustments_created'] > 0) {
            $this->info('✅ Next Steps:');
            $this->info('- Monitor affiliate dashboards for updated commission totals');
            $this->info('- Adjustments will be included in future withdrawals');
            $this->info('- Review the audit trail in commission records');
        }
    }
}
