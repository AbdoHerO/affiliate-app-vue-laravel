<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommandeArticle extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'commande_articles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'commande_id',
        'produit_id',
        'variante_id',
        'quantite',
        'prix_unitaire',
        'remise',
        'total_ligne',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantite' => 'integer',
        'prix_unitaire' => 'decimal:2',
        'remise' => 'decimal:2',
        'total_ligne' => 'decimal:2',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the order for this article.
     */
    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class, 'commande_id');
    }

    /**
     * Get the product for this article.
     */
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    /**
     * Get the product variant for this article.
     */
    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProduitVariante::class, 'variante_id');
    }

    /**
     * Get the commissions for this article.
     */
    public function commissions(): HasMany
    {
        return $this->hasMany(CommissionAffilie::class, 'commande_article_id');
    }

    /**
     * Get the return articles for this article.
     */
    public function retourArticles(): HasMany
    {
        return $this->hasMany(RetourArticle::class, 'commande_article_id');
    }
}
