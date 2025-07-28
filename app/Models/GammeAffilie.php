<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GammeAffilie extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'gammes_affilies';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'libelle',
        'actif',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'actif' => 'boolean',
    ];

    /**
     * Get the affiliate profiles for this tier.
     */
    public function profilsAffilies(): HasMany
    {
        return $this->hasMany(ProfilAffilie::class, 'gamme_id');
    }

    /**
     * Get the tier history records.
     */
    public function gammeHistorique(): HasMany
    {
        return $this->hasMany(ProfilAffilieGammeHisto::class, 'gamme_id');
    }

    /**
     * Get the commission rules for this tier.
     */
    public function reglesCommission(): HasMany
    {
        return $this->hasMany(RegleCommission::class, 'gamme_id');
    }

    /**
     * Get the stock reservations for this tier.
     */
    public function reservationsStock(): HasMany
    {
        return $this->hasMany(ReservationStock::class, 'gamme_id');
    }

    /**
     * Get the zero exchange rules for this tier.
     */
    public function reglesEchangeZero(): HasMany
    {
        return $this->hasMany(RegleEchangeZero::class, 'gamme_id');
    }

    /**
     * Get the offer visibility rules for this tier.
     */
    public function offreVisibiliteGammes(): HasMany
    {
        return $this->hasMany(OffreVisibiliteGamme::class, 'gamme_id');
    }
}
