<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionAffilie extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'commissions_affilies';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'commande_article_id',
        'affilie_id',
        'type',
        'montant',
        'statut',
        'motif',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'montant' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the order article for this commission.
     */
    public function commandeArticle(): BelongsTo
    {
        return $this->belongsTo(CommandeArticle::class, 'commande_article_id');
    }

    /**
     * Get the affiliate for this commission.
     */
    public function affilie(): BelongsTo
    {
        return $this->belongsTo(ProfilAffilie::class, 'affilie_id');
    }

    /**
     * Get the settlement lines for this commission.
     */
    public function reglementLignes(): HasMany
    {
        return $this->hasMany(ReglementLigne::class, 'commission_id');
    }
}
