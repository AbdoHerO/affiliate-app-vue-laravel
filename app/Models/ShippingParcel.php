<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ShippingParcel extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'shipping_parcels';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'commande_id',
        'provider',
        'tracking_number',
        'status',
        'city_id',
        'city_name',
        'receiver',
        'phone',
        'address',
        'price',
        'note',
        'delivered_price',
        'returned_price',
        'refused_price',
        'delivery_note_ref',
        'last_synced_at',
        'meta',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'price' => 'decimal:2',
        'delivered_price' => 'decimal:2',
        'returned_price' => 'decimal:2',
        'refused_price' => 'decimal:2',
        'last_synced_at' => 'datetime',
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the order for this parcel.
     */
    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class, 'commande_id');
    }
}
