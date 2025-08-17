<?php

namespace App\Console\Commands;

use App\Jobs\MoveEligibleCommissionsJob;
use Illuminate\Console\Command;

class ProcessEligibleCommissionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'commissions:process-eligible';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process commissions that are ready to become eligible for payout';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing eligible commissions...');

        try {
            MoveEligibleCommissionsJob::dispatch();
            $this->info('Commission processing job dispatched successfully.');
        } catch (\Exception $e) {
            $this->error('Failed to dispatch commission processing job: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
