<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Carbon\Carbon;

class ReferralAttribution extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'referral_attributions';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'new_user_id',
        'referrer_affiliate_id',
        'referral_code',
        'attributed_at',
        'verified',
        'verified_at',
        'source',
        'ip_hash',
        'device_fingerprint',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'verified' => 'boolean',
        'attributed_at' => 'datetime',
        'verified_at' => 'datetime',
        'device_fingerprint' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the new user that was referred.
     */
    public function newUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_user_id');
    }

    /**
     * Get the affiliate who made the referral.
     */
    public function referrerAffiliate(): BelongsTo
    {
        return $this->belongsTo(ProfilAffilie::class, 'referrer_affiliate_id');
    }

    /**
     * Get the referral code that was used.
     */
    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class, 'referral_code', 'code');
    }

    /**
     * Create an attribution record.
     */
    public static function createAttribution(
        User $newUser,
        ProfilAffilie $referrerAffiliate,
        string $referralCode,
        string $ipAddress,
        string $source = 'web',
        ?array $deviceFingerprint = null
    ): self {
        return self::create([
            'new_user_id' => $newUser->id,
            'referrer_affiliate_id' => $referrerAffiliate->id,
            'referral_code' => $referralCode,
            'attributed_at' => now(),
            'verified' => $newUser->email_verifie ?? false,
            'verified_at' => $newUser->email_verifie ? now() : null,
            'source' => $source,
            'ip_hash' => hash('sha256', $ipAddress . config('app.key')),
            'device_fingerprint' => $deviceFingerprint,
        ]);
    }

    /**
     * Mark the attribution as verified.
     */
    public function markAsVerified(): void
    {
        $this->update([
            'verified' => true,
            'verified_at' => now(),
        ]);
    }

    /**
     * Scope to verified attributions only.
     */
    public function scopeVerified($query)
    {
        return $query->where('verified', true);
    }

    /**
     * Scope to unverified attributions only.
     */
    public function scopeUnverified($query)
    {
        return $query->where('verified', false);
    }

    /**
     * Scope to attributions within a date range.
     */
    public function scopeWithinDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('attributed_at', [$startDate, $endDate]);
    }
}
