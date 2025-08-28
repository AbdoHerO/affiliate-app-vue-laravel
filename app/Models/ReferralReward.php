<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ReferralReward extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'referral_rewards';

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
     * Get the affiliate who received the reward.
     */
    public function referrerAffiliate(): BelongsTo
    {
        return $this->belongsTo(ProfilAffilie::class, 'referrer_affiliate_id');
    }

    /**
     * Get the admin who created the reward.
     */
    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    /**
     * Create a new reward.
     */
    public static function createReward(
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
}
