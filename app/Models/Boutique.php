<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Boutique extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'boutiques';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nom',
        'slug',
        'proprietaire_id',
        'email_pro',
        'adresse',
        'statut',
        'commission_par_defaut',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'commission_par_defaut' => 'decimal:3',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the owner of the boutique.
     */
    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proprietaire_id');
    }

    /**
     * Get the products for this boutique.
     */
    public function produits(): HasMany
    {
        return $this->hasMany(Produit::class, 'boutique_id');
    }

    /**
     * Get the offers for this boutique.
     */
    public function offres(): HasMany
    {
        return $this->hasMany(Offre::class, 'boutique_id');
    }

    /**
     * Get the warehouses for this boutique.
     */
    public function entrepots(): HasMany
    {
        return $this->hasMany(Entrepot::class, 'boutique_id');
    }

    /**
     * Get the orders for this boutique.
     */
    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class, 'boutique_id');
    }

    /**
     * Get the carriers for this boutique.
     */
    public function transporteurs(): HasMany
    {
        return $this->hasMany(Transporteur::class, 'boutique_id');
    }

    /**
     * Get the marketing assets for this boutique.
     */
    public function assetsMarketing(): HasMany
    {
        return $this->hasMany(AssetMarketing::class, 'boutique_id');
    }
}
