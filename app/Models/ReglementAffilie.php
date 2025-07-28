<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReglementAffilie extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'reglements_affilies';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'affilie_id',
        'montant_total',
        'statut',
        'mode_versement',
        'reference_ext',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'montant_total' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the affiliate for this settlement.
     */
    public function affilie(): BelongsTo
    {
        return $this->belongsTo(ProfilAffilie::class, 'affilie_id');
    }

    /**
     * Get the settlement lines for this settlement.
     */
    public function lignes(): HasMany
    {
        return $this->hasMany(ReglementLigne::class, 'reglement_id');
    }
}
