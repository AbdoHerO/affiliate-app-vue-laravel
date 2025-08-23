<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Carbon\Carbon;

class ReferralClick extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'referral_clicks';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'referral_code',
        'ip_hash',
        'user_agent',
        'referer_url',
        'device_fingerprint',
        'clicked_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'device_fingerprint' => 'array',
        'clicked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the referral code that was clicked.
     */
    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class, 'referral_code', 'code');
    }

    /**
     * Create a click record with hashed IP for privacy.
     */
    public static function recordClick(
        string $referralCode,
        string $ipAddress,
        ?string $userAgent = null,
        ?string $refererUrl = null,
        ?array $deviceFingerprint = null
    ): self {
        return self::create([
            'referral_code' => $referralCode,
            'ip_hash' => hash('sha256', $ipAddress . config('app.key')),
            'user_agent' => $userAgent,
            'referer_url' => $refererUrl,
            'device_fingerprint' => $deviceFingerprint,
            'clicked_at' => now(),
        ]);
    }

    /**
     * Scope to clicks within a date range.
     */
    public function scopeWithinDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('clicked_at', [$startDate, $endDate]);
    }

    /**
     * Scope to unique clicks (one per IP hash per day).
     */
    public function scopeUnique($query)
    {
        return $query->selectRaw('DISTINCT ip_hash, DATE(clicked_at) as click_date, referral_code')
                    ->groupBy('ip_hash', 'click_date', 'referral_code');
    }
}
