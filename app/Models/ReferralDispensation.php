<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Carbon\Carbon;

class ReferralDispensation extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'referral_dispensations';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'referrer_affiliate_id',
        'points',
        'comment',
        'reference',
        'created_by_admin_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'points' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the affiliate who received the dispensation.
     */
    public function referrerAffiliate(): BelongsTo
    {
        return $this->belongsTo(ProfilAffilie::class, 'referrer_affiliate_id');
    }

    /**
     * Get the admin who created the dispensation.
     */
    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    /**
     * Create a new dispensation.
     */
    public static function createDispensation(
        ProfilAffilie $affiliate,
        int $points,
        string $comment,
        User $admin,
        ?string $reference = null
    ): self {
        return self::create([
            'referrer_affiliate_id' => $affiliate->id,
            'points' => $points,
            'comment' => $comment,
            'reference' => $reference,
            'created_by_admin_id' => $admin->id,
        ]);
    }

    /**
     * Scope to dispensations within a date range.
     */
    public function scopeWithinDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to dispensations for a specific affiliate.
     */
    public function scopeForAffiliate($query, $affiliateId)
    {
        return $query->where('referrer_affiliate_id', $affiliateId);
    }
}
