<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Produit extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'produits';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'boutique_id',
        'categorie_id',
        'titre',
        'sku',
        'description',
        'copywriting',
        'prix_achat',
        'prix_vente',
        'prix_affilie',
        'slug',
        'actif',
        'quantite_min',
        'notes_admin',
        'rating_value',
        'rating_max',
        'rating_updated_by',
        'rating_updated_at',
        'stock_total',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'prix_achat' => 'decimal:2',
        'prix_vente' => 'decimal:2',
        'prix_affilie' => 'decimal:2',
        'actif' => 'boolean',
        'quantite_min' => 'integer',
        'stock_total' => 'integer',
        'rating_value' => 'float',
        'rating_max' => 'integer',
        'rating_updated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should have default values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantite_min' => 1,
        'actif' => true,
    ];

    /**
     * Get the boutique that owns the product.
     */
    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class, 'boutique_id');
    }

    /**
     * Get the category of the product.
     */
    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class, 'categorie_id');
    }

    /**
     * Get the images for this product.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProduitImage::class, 'produit_id');
    }

    /**
     * Get the videos for this product.
     */
    public function videos(): HasMany
    {
        return $this->hasMany(ProduitVideo::class, 'produit_id');
    }

    /**
     * Get the variants for this product.
     */
    public function variantes(): HasMany
    {
        return $this->hasMany(ProduitVariante::class, 'produit_id');
    }

    /**
     * Get the propositions for this product.
     */
    public function propositions(): HasMany
    {
        return $this->hasMany(ProduitProposition::class, 'produit_id');
    }

    /**
     * Get the offers for this product.
     */
    public function offres(): HasMany
    {
        return $this->hasMany(Offre::class, 'produit_id');
    }

    /**
     * Get the order items for this product.
     */
    public function commandeArticles(): HasMany
    {
        return $this->hasMany(CommandeArticle::class, 'produit_id');
    }

    /**
     * Get the zero exchange rules for this product.
     */
    public function reglesEchangeZero(): HasMany
    {
        return $this->hasMany(RegleEchangeZero::class, 'produit_id');
    }

    /**
     * Get the zero exchanges for this product.
     */
    public function echangesZero(): HasMany
    {
        return $this->hasMany(EchangeZero::class, 'produit_id');
    }

    /**
     * Get the reviews for this product.
     */
    public function avis(): HasMany
    {
        return $this->hasMany(AvisProduit::class, 'produit_id');
    }

    /**
     * Get the ruptures for this product.
     */
    public function ruptures(): HasMany
    {
        return $this->hasMany(ProduitRupture::class, 'produit_id');
    }

    /**
     * Get the user who last updated the rating.
     */
    public function ratingUpdater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rating_updated_by');
    }
}
