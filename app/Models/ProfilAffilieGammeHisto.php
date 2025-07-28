<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfilAffilieGammeHisto extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'profils_affilies_gamme_histo';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'profil_id',
        'gamme_id',
        'date_debut',
        'date_fin',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_debut' => 'datetime',
        'date_fin' => 'datetime',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the affiliate profile.
     */
    public function profil(): BelongsTo
    {
        return $this->belongsTo(ProfilAffilie::class, 'profil_id');
    }

    /**
     * Get the affiliate tier.
     */
    public function gamme(): BelongsTo
    {
        return $this->belongsTo(GammeAffilie::class, 'gamme_id');
    }
}
