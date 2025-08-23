<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProfilAffilie extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'profils_affilies';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'utilisateur_id',
        'gamme_id',
        'points',
        'statut',
        'rib',
        'notes_interne',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'points' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the affiliate profile.
     */
    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'utilisateur_id');
    }

    /**
     * Get the affiliate tier.
     */
    public function gamme(): BelongsTo
    {
        return $this->belongsTo(\App\Models\GammeAffilie::class, 'gamme_id');
    }

    /**
     * Get the tier history for this profile.
     */
    public function gammeHistorique(): HasMany
    {
        return $this->hasMany(\App\Models\ProfilAffilieGammeHisto::class, 'profil_id');
    }

    /**
     * Get the orders placed by this affiliate.
     */
    public function commandes(): HasMany
    {
        return $this->hasMany(\App\Models\Commande::class, 'affilie_id');
    }

    /**
     * Get the import lots created by this affiliate.
     */
    public function lotsImport(): HasMany
    {
        return $this->hasMany(\App\Models\LotImport::class, 'affilie_id');
    }

    /**
     * Get the commissions for this affiliate.
     */
    public function commissions(): HasMany
    {
        return $this->hasMany(\App\Models\CommissionAffilie::class, 'affilie_id');
    }

    /**
     * Get the referral code for this affiliate.
     */
    public function referralCode(): HasOne
    {
        return $this->hasOne(ReferralCode::class, 'affiliate_id');
    }

    /**
     * Get the referral attributions made by this affiliate.
     */
    public function referralAttributions(): HasMany
    {
        return $this->hasMany(\App\Models\ReferralAttribution::class, 'referrer_affiliate_id');
    }

    /**
     * Get the referral dispensations received by this affiliate.
     */
    public function referralDispensations(): HasMany
    {
        return $this->hasMany(\App\Models\ReferralDispensation::class, 'referrer_affiliate_id');
    }

    /**
     * Get the payment settlements for this affiliate.
     */
    public function reglements(): HasMany
    {
        return $this->hasMany(\App\Models\ReglementAffilie::class, 'affilie_id');
    }

    /**
     * Get the stock reservations for this affiliate.
     */
    public function reservationsStock(): HasMany
    {
        return $this->hasMany(\App\Models\ReservationStock::class, 'affilie_id');
    }

    /**
     * Get the offer visibility rules for this affiliate.
     */
    public function offreVisibiliteAffilies(): HasMany
    {
        return $this->hasMany(\App\Models\OffreVisibiliteAffilie::class, 'affilie_id');
    }
}
