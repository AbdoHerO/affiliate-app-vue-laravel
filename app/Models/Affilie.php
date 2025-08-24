<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Affilie extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'nom_complet',
        'email',
        'telephone',
        'adresse',
        'ville',
        'pays',
        'mot_de_passe_hash',
        'email_verified_at',
        'approval_status',
        'refusal_reason',
        'notes',
        'rib',
        'bank_type',
    ];

    protected $hidden = [
        'mot_de_passe_hash',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'approval_status' => 'string',
    ];

    public function emailVerifications(): HasMany
    {
        return $this->hasMany(AffiliateEmailVerification::class);
    }

    /**
     * Get the user associated with this affiliate.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeEmailVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    public function scopeEmailNotVerified($query)
    {
        return $query->whereNull('email_verified_at');
    }

    public function scopePendingApproval($query)
    {
        return $query->where('approval_status', 'pending_approval');
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    public function scopeRefused($query)
    {
        return $query->where('approval_status', 'refused');
    }

    public function isEmailVerified(): bool
    {
        return !is_null($this->email_verified_at);
    }

    public function isPendingApproval(): bool
    {
        return $this->approval_status === 'pending_approval';
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isRefused(): bool
    {
        return $this->approval_status === 'refused';
    }
}
