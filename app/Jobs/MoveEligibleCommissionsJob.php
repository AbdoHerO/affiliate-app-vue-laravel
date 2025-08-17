<?php

namespace App\Jobs;

use App\Services\CommissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MoveEligibleCommissionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(CommissionService $commissionService): void
    {
        Log::info('Starting MoveEligibleCommissionsJob');

        try {
            $count = $commissionService->processEligibleCommissions();
            
            Log::info('MoveEligibleCommissionsJob completed successfully', [
                'processed_count' => $count
            ]);
        } catch (\Exception $e) {
            Log::error('MoveEligibleCommissionsJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}
