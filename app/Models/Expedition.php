<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expedition extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'expeditions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'commande_id',
        'transporteur_id',
        'tracking_no',
        'statut',
        'poids_kg',
        'frais_transport',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'poids_kg' => 'decimal:3',
        'frais_transport' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the order for this shipment.
     */
    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class, 'commande_id');
    }

    /**
     * Get the carrier for this shipment.
     */
    public function transporteur(): BelongsTo
    {
        return $this->belongsTo(Transporteur::class, 'transporteur_id');
    }

    /**
     * Get the tracking events for this shipment.
     */
    public function evenements(): HasMany
    {
        return $this->hasMany(ExpeditionEvenement::class, 'expedition_id');
    }

    /**
     * Get the COD collections for this shipment.
     */
    public function encaissementsCod(): HasMany
    {
        return $this->hasMany(EncaissementCod::class, 'expedition_id');
    }
}
