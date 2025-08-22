<?php

namespace App\Listeners;

use App\Events\OrderDelivered;
use App\Services\CommissionService;
use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CreateCommissionOnDelivery implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The commission service instance.
     */
    protected CommissionService $commissionService;

    /**
     * Create the event listener.
     */
    public function __construct(CommissionService $commissionService)
    {
        $this->commissionService = $commissionService;
    }

    /**
     * Handle the event.
     */
    public function handle(OrderDelivered $event): void
    {
        try {
            $order = $event->order;

            Log::info('OrderDelivered event received', [
                'order_id' => $order->id,
                'trigger' => $event->trigger,
                'metadata' => $event->metadata
            ]);

            // Check if commissions already exist for this order (idempotency)
            $existingCommissions = $order->commissions()->count();
            
            if ($existingCommissions > 0) {
                Log::info('Commission already exists for delivered order', [
                    'order_id' => $order->id,
                    'trigger' => $event->trigger,
                    'existing_commissions' => $existingCommissions
                ]);
                return;
            }

            // Calculate and create commissions
            $this->commissionService->calculateForOrder($order);

            // Create audit log entry
            AuditLog::create([
                'auteur_id' => request()->user()?->id ?? $event->metadata['updated_by'] ?? null,
                'action' => 'commission_created',
                'table_name' => 'commandes',
                'record_id' => $order->id,
                'new_values' => [
                    'trigger' => $event->trigger,
                    'metadata' => $event->metadata,
                    'commission_created_at' => now()->toISOString(),
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            Log::info('Commission created automatically on delivery', [
                'order_id' => $order->id,
                'trigger' => $event->trigger,
                'metadata' => $event->metadata
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create commission on delivery', [
                'order_id' => $event->order->id,
                'trigger' => $event->trigger,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to ensure the job fails and can be retried
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(OrderDelivered $event, \Throwable $exception): void
    {
        Log::error('Commission creation job failed permanently', [
            'order_id' => $event->order->id,
            'trigger' => $event->trigger,
            'error' => $exception->getMessage()
        ]);
    }
}
