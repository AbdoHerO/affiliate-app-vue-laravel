<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProduitVariante extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'produit_variantes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'produit_id',
        'nom',
        'valeur',
        'prix_vente_variante',
        'image_url',
        'actif',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'prix_vente_variante' => 'decimal:2',
        'actif' => 'boolean',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the product that owns the variant.
     */
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    /**
     * Get the offers for this variant.
     */
    public function offres(): HasMany
    {
        return $this->hasMany(Offre::class, 'variante_id');
    }

    /**
     * Get the stocks for this variant.
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'variante_id');
    }

    /**
     * Get the stock movements for this variant.
     */
    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class, 'variante_id');
    }

    /**
     * Get the stock reservations for this variant.
     */
    public function reservationsStock(): HasMany
    {
        return $this->hasMany(ReservationStock::class, 'variante_id');
    }

    /**
     * Get the stock outages for this variant.
     */
    public function ruptures(): HasMany
    {
        return $this->hasMany(ProduitRupture::class, 'variante_id');
    }

    /**
     * Get the order items for this variant.
     */
    public function commandeArticles(): HasMany
    {
        return $this->hasMany(CommandeArticle::class, 'variante_id');
    }
}
