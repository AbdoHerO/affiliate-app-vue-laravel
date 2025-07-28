<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entrepot extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'entrepots';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'boutique_id',
        'nom',
        'adresse',
        'actif',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'actif' => 'boolean',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the boutique that owns the warehouse.
     */
    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class, 'boutique_id');
    }

    /**
     * Get the stocks for this warehouse.
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'entrepot_id');
    }

    /**
     * Get the stock movements for this warehouse.
     */
    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class, 'entrepot_id');
    }

    /**
     * Get the stock reservations for this warehouse.
     */
    public function reservationsStock(): HasMany
    {
        return $this->hasMany(ReservationStock::class, 'entrepot_id');
    }
}
