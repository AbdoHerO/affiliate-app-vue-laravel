<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Commande extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'commandes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'boutique_id',
        'user_id', // Changed from affilie_id to user_id
        'affilie_id', // Keep temporarily for rollback safety
        'client_id',
        'adresse_id',
        'client_final_id',
        'adresse_livraison_id',
        'client_final_snapshot',
        'offre_id',
        'statut',
        'confirmation_cc',
        'mode_paiement',
        'type_command',
        'total_ht',
        'total_ttc',
        'devise',
        'notes',
        'no_answer_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_ht' => 'decimal:2',
        'total_ttc' => 'decimal:2',
        'no_answer_count' => 'integer',
        'client_final_snapshot' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the boutique for this order.
     */
    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class, 'boutique_id');
    }

    /**
     * Get the affiliate user for this order.
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user (affiliate) for this order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the affiliate profile for this order (legacy).
     * @deprecated Use affiliate() instead
     */
    public function affilie(): BelongsTo
    {
        return $this->belongsTo(ProfilAffilie::class, 'affilie_id');
    }

    /**
     * Get the client for this order.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Get the client final for this order.
     */
    public function clientFinal(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_final_id');
    }

    /**
     * Get the delivery address for this order.
     */
    public function adresseLivraison(): BelongsTo
    {
        return $this->belongsTo(Adresse::class, 'adresse_livraison_id');
    }

    /**
     * Get the address for this order.
     */
    public function adresse(): BelongsTo
    {
        return $this->belongsTo(Adresse::class, 'adresse_id');
    }

    /**
     * Get the offer for this order.
     */
    public function offre(): BelongsTo
    {
        return $this->belongsTo(Offre::class, 'offre_id');
    }

    /**
     * Get the order items for this order.
     */
    public function articles(): HasMany
    {
        return $this->hasMany(CommandeArticle::class, 'commande_id');
    }

    /**
     * Alias for articles relationship (for dashboard compatibility)
     */
    public function commandeArticles(): HasMany
    {
        return $this->articles();
    }

    /**
     * Get the shipments for this order.
     */
    public function expeditions(): HasMany
    {
        return $this->hasMany(Expedition::class, 'commande_id');
    }

    /**
     * Get the conflicts for this order.
     */
    public function conflits(): HasMany
    {
        return $this->hasMany(ConflitCommande::class, 'commande_id');
    }

    /**
     * Get the returns for this order.
     */
    public function retours(): HasMany
    {
        return $this->hasMany(Retour::class, 'commande_id');
    }

    /**
     * Get the zero exchanges for this order.
     */
    public function echangesZero(): HasMany
    {
        return $this->hasMany(EchangeZero::class, 'commande_id');
    }

    /**
     * Get the import commands for this order.
     */
    public function importCommandes(): HasMany
    {
        return $this->hasMany(ImportCommande::class, 'commande_id');
    }

    /**
     * Get the shipping parcel for this order.
     */
    public function shippingParcel(): HasOne
    {
        return $this->hasOne(ShippingParcel::class, 'commande_id');
    }

    /**
     * Get the commissions for this order.
     */
    public function commissions(): HasMany
    {
        return $this->hasMany(CommissionAffilie::class, 'commande_id');
    }

    /**
     * Get the status history for this order.
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id')->latest();
    }
}
