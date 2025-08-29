<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\Auth;

class OrderStatusHistoryService
{
    /**
     * Log a status change for an order.
     */
    public function logStatusChange(
        Commande $order,
        ?string $fromStatus,
        string $toStatus,
        string $source,
        ?string $note = null,
        ?string $changedBy = null,
        ?array $meta = null
    ): OrderStatusHistory {
        // If no changedBy is provided, try to get current user
        if (!$changedBy && Auth::check()) {
            $changedBy = Auth::id();
        }

        return OrderStatusHistory::createEntry(
            $order->id,
            $fromStatus,
            $toStatus,
            $source,
            $note,
            $changedBy,
            $meta
        );
    }

    /**
     * Log admin status change.
     */
    public function logAdminChange(
        Commande $order,
        string $fromStatus,
        string $toStatus,
        ?string $note = null,
        ?array $meta = null
    ): OrderStatusHistory {
        return $this->logStatusChange(
            $order,
            $fromStatus,
            $toStatus,
            'admin',
            $note,
            Auth::id(),
            $meta
        );
    }

    /**
     * Log affiliate status change.
     */
    public function logAffiliateChange(
        Commande $order,
        string $fromStatus,
        string $toStatus,
        ?string $note = null,
        ?array $meta = null
    ): OrderStatusHistory {
        return $this->logStatusChange(
            $order,
            $fromStatus,
            $toStatus,
            'affiliate',
            $note,
            Auth::id(),
            $meta
        );
    }

    /**
     * Log OzonExpress webhook status change.
     */
    public function logOzonExpressChange(
        Commande $order,
        string $fromStatus,
        string $toStatus,
        ?string $note = null,
        ?array $meta = null
    ): OrderStatusHistory {
        return $this->logStatusChange(
            $order,
            $fromStatus,
            $toStatus,
            'ozon_express',
            $note,
            null, // No user for webhook changes
            $meta
        );
    }

    /**
     * Log system status change.
     */
    public function logSystemChange(
        Commande $order,
        string $fromStatus,
        string $toStatus,
        ?string $note = null,
        ?array $meta = null
    ): OrderStatusHistory {
        return $this->logStatusChange(
            $order,
            $fromStatus,
            $toStatus,
            'system',
            $note,
            null, // No user for system changes
            $meta
        );
    }

    /**
     * Get timeline for an order.
     */
    public function getOrderTimeline(string $orderId): \Illuminate\Database\Eloquent\Collection
    {
        return OrderStatusHistory::forOrder($orderId)
            ->with('changedBy:id,nom_complet,email')
            ->latest()
            ->get();
    }

    /**
     * Create initial status history entry for new orders.
     */
    public function logInitialStatus(
        Commande $order,
        string $source = 'system',
        ?string $note = null
    ): OrderStatusHistory {
        return $this->logStatusChange(
            $order,
            null, // No previous status for initial entry
            $order->statut,
            $source,
            $note ?? 'Commande créée'
        );
    }
}
