<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

class ReferralCode extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'referral_codes';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'affiliate_id',
        'code',
        'active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the affiliate that owns this referral code.
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(ProfilAffilie::class, 'affiliate_id');
    }

    /**
     * Get the clicks for this referral code.
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(ReferralClick::class, 'referral_code', 'code');
    }

    /**
     * Get the attributions for this referral code.
     */
    public function attributions(): HasMany
    {
        return $this->hasMany(ReferralAttribution::class, 'referral_code', 'code');
    }

    /**
     * Generate a unique referral code.
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Get or create a referral code for an affiliate.
     */
    public static function getOrCreateForAffiliate(ProfilAffilie $affiliate): self
    {
        return self::firstOrCreate(
            ['affiliate_id' => $affiliate->id],
            ['code' => self::generateUniqueCode()]
        );
    }

    /**
     * Scope to only active codes.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
