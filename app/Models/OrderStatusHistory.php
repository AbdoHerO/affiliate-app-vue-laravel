<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OrderStatusHistory extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'order_status_history';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'source',
        'note',
        'changed_by',
        'meta',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($history) {
            if (!$history->created_at) {
                $history->created_at = now();
            }
        });
    }

    /**
     * Get the order this history entry belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Commande::class, 'order_id');
    }

    /**
     * Get the user who made the status change.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Scope to filter by order.
     */
    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * Scope to filter by source.
     */
    public function scopeBySource($query, $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Scope to order by creation date (newest first).
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Get the status label for display.
     */
    public function getStatusLabel(): string
    {
        $statusLabels = [
            'en_attente' => 'En attente',
            'confirmee' => 'Confirmée',
            'expediee' => 'Expédiée',
            'livree' => 'Livrée',
            'annulee' => 'Annulée',
            'retournee' => 'Retournée',
            'returned_to_warehouse' => 'Retournée en entrepôt',
            'refusee' => 'Refusée',
            'injoignable' => 'Injoignable',
            'echec_livraison' => 'Échec livraison',
            // OzonExpress statuses
            'pending' => 'En attente',
            'received' => 'Reçu',
            'in_transit' => 'En transit',
            'shipped' => 'Expédié',
            'at_facility' => 'En centre',
            'ready_for_delivery' => 'Prêt livraison',
            'out_for_delivery' => 'En livraison',
            'delivery_attempted' => 'Tentative livraison',
            'delivered' => 'Livré',
            'returned' => 'Retourné',
            'refused' => 'Refusé',
            'cancelled' => 'Annulé',
            'unknown' => 'Inconnu',
        ];

        return $statusLabels[$this->to_status] ?? $this->to_status;
    }

    /**
     * Get the source label for display.
     */
    public function getSourceLabel(): string
    {
        $sourceLabels = [
            'admin' => 'Administrateur',
            'affiliate' => 'Affilié',
            'ozon_express' => 'OzonExpress',
            'system' => 'Système',
        ];

        return $sourceLabels[$this->source] ?? $this->source;
    }

    /**
     * Create a status history entry.
     */
    public static function createEntry(
        string $orderId,
        ?string $fromStatus,
        string $toStatus,
        string $source,
        ?string $note = null,
        ?string $changedBy = null,
        ?array $meta = null
    ): self {
        return self::create([
            'order_id' => $orderId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'source' => $source,
            'note' => $note,
            'changed_by' => $changedBy,
            'meta' => $meta,
        ]);
    }
}
