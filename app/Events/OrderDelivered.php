<?php

namespace App\Events;

use App\Models\Commande;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderDelivered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The order that was delivered.
     */
    public Commande $order;

    /**
     * The trigger source (carrier_webhook, manual_update, etc.)
     */
    public string $trigger;

    /**
     * Additional metadata about the delivery.
     */
    public array $metadata;

    /**
     * Create a new event instance.
     */
    public function __construct(Commande $order, string $trigger = 'unknown', array $metadata = [])
    {
        $this->order = $order;
        $this->trigger = $trigger;
        $this->metadata = $metadata;
    }
}
