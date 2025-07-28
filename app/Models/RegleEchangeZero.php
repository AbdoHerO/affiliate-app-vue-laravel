<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegleEchangeZero extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'regles_echange_zero';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'produit_id',
        'client_id',
        'gamme_id',
        'nb_max',
        'actif',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'nb_max' => 'integer',
        'actif' => 'boolean',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the product for this zero exchange rule.
     */
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    /**
     * Get the client for this zero exchange rule.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Get the affiliate tier for this zero exchange rule.
     */
    public function gamme(): BelongsTo
    {
        return $this->belongsTo(GammeAffilie::class, 'gamme_id');
    }

    /**
     * Get the zero exchanges for this rule.
     */
    public function echanges(): HasMany
    {
        return $this->hasMany(EchangeZero::class, 'regle_id');
    }
}
