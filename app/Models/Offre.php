<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Offre extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'offres';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'boutique_id',
        'produit_id',
        'variante_id',
        'titre_public',
        'prix_vente',
        'actif',
        'date_debut',
        'date_fin',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'prix_vente' => 'decimal:2',
        'actif' => 'boolean',
        'date_debut' => 'datetime',
        'date_fin' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the boutique that owns the offer.
     */
    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class, 'boutique_id');
    }

    /**
     * Get the product for this offer.
     */
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    /**
     * Get the product variant for this offer.
     */
    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProduitVariante::class, 'variante_id');
    }

    /**
     * Get the countries for this offer.
     */
    public function pays(): HasMany
    {
        return $this->hasMany(OffrePays::class, 'offre_id');
    }

    /**
     * Get the commission rules for this offer.
     */
    public function reglesCommission(): HasMany
    {
        return $this->hasMany(RegleCommission::class, 'offre_id');
    }

    /**
     * Get the visibility settings for this offer.
     */
    public function visibilite(): HasOne
    {
        return $this->hasOne(OffreVisibilite::class, 'offre_id');
    }

    /**
     * Get the affiliate visibility rules for this offer.
     */
    public function visibiliteAffilies(): HasMany
    {
        return $this->hasMany(OffreVisibiliteAffilie::class, 'offre_id');
    }

    /**
     * Get the tier visibility rules for this offer.
     */
    public function visibiliteGammes(): HasMany
    {
        return $this->hasMany(OffreVisibiliteGamme::class, 'offre_id');
    }

    /**
     * Get the orders for this offer.
     */
    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class, 'offre_id');
    }

    /**
     * Get the stock reservations for this offer.
     */
    public function reservationsStock(): HasMany
    {
        return $this->hasMany(ReservationStock::class, 'offre_id');
    }

    /**
     * Get the marketing assets for this offer.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(OffreAsset::class, 'offre_id');
    }
}
