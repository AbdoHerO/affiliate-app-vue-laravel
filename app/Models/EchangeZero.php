<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EchangeZero extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'echanges_zero';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'regle_id',
        'commande_id',
        'produit_id',
        'applique_par',
        'motif',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the zero exchange rule for this exchange.
     */
    public function regle(): BelongsTo
    {
        return $this->belongsTo(RegleEchangeZero::class, 'regle_id');
    }

    /**
     * Get the order for this zero exchange.
     */
    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class, 'commande_id');
    }

    /**
     * Get the product for this zero exchange.
     */
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    /**
     * Get the user who applied this zero exchange.
     */
    public function appliquePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applique_par');
    }
}
