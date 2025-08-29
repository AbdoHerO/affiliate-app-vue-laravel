<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionAffilie extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'commissions_affilies';

    /**
     * Commission status constants
     */
    public const STATUS_PENDING_CALC = 'pending_calc';
    public const STATUS_CALCULATED = 'calculated';
    public const STATUS_ELIGIBLE = 'eligible';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAID = 'paid';
    public const STATUS_ADJUSTED = 'adjusted';
    public const STATUS_CANCELED = 'canceled';

    /**
     * Commission types
     */
    public const TYPE_SALE = 'vente';
    public const TYPE_REFERRAL = 'parrainage';
    public const TYPE_BONUS = 'bonus';
    public const TYPE_ADJUSTMENT = 'ajustement';
    public const TYPE_RETURN = 'retour';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'commande_article_id',
        'commande_id',
        'user_id',
        'affilie_id', // Keep temporarily for rollback safety
        'type',
        'base_amount',
        'rate',
        'qty',
        'amount',
        'currency',
        'status',
        'rule_code',
        'notes',
        'eligible_at',
        'approved_at',
        'paid_at',
        'paid_withdrawal_id',
        'meta',
        // Legacy fields
        'montant',
        'statut',
        'motif',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'base_amount' => 'decimal:2',
        'rate' => 'decimal:4',
        'qty' => 'integer',
        'amount' => 'decimal:2',
        'eligible_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        // Legacy fields
        'montant' => 'decimal:2',
    ];

    /**
     * Get the order article for this commission.
     */
    public function commandeArticle(): BelongsTo
    {
        return $this->belongsTo(CommandeArticle::class, 'commande_article_id');
    }

    /**
     * Get the order for this commission.
     */
    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class, 'commande_id');
    }

    /**
     * Get the affiliate user for this commission.
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user for this commission (alias for affiliate).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the affiliate profile for this commission (legacy).
     * @deprecated Use affiliate() instead
     */
    public function affilie(): BelongsTo
    {
        return $this->belongsTo(ProfilAffilie::class, 'affilie_id');
    }

    /**
     * Get the settlement lines for this commission.
     */
    public function reglementLignes(): HasMany
    {
        return $this->hasMany(ReglementLigne::class, 'commission_id');
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by eligible commissions
     */
    public function scopeEligible($query)
    {
        return $query->where('status', self::STATUS_ELIGIBLE);
    }

    /**
     * Scope: Filter by pending approval
     */
    public function scopePendingApproval($query)
    {
        return $query->whereIn('status', [self::STATUS_CALCULATED, self::STATUS_ELIGIBLE]);
    }

    /**
     * Scope: Filter by approved commissions
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope: Filter by paid commissions
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * Scope: Filter commissions ready to be eligible
     */
    public function scopeReadyForEligibility($query)
    {
        return $query->where('status', self::STATUS_CALCULATED)
                    ->where('eligible_at', '<=', now())
                    ->whereHas('commande', function ($q) {
                        $q->whereNotIn('statut', ['retournee', 'refusee', 'annulee']);
                    });
    }

    /**
     * Check if commission can be approved
     */
    public function canBeApproved(): bool
    {
        return in_array($this->status, [self::STATUS_CALCULATED, self::STATUS_ELIGIBLE]);
    }

    /**
     * Check if commission can be rejected
     */
    public function canBeRejected(): bool
    {
        return in_array($this->status, [self::STATUS_CALCULATED, self::STATUS_ELIGIBLE, self::STATUS_APPROVED]);
    }

    /**
     * Check if commission can be adjusted
     */
    public function canBeAdjusted(): bool
    {
        return in_array($this->status, [self::STATUS_CALCULATED, self::STATUS_ELIGIBLE, self::STATUS_APPROVED]);
    }

    /**
     * Get the withdrawal this commission is paid through
     */
    public function paidWithdrawal(): BelongsTo
    {
        return $this->belongsTo(Withdrawal::class, 'paid_withdrawal_id');
    }

    /**
     * Get status badge information for UI display.
     */
    public function getStatusBadge(): array
    {
        $statusMap = [
            'calculated' => ['color' => 'info', 'text' => 'Calculée'],
            'eligible' => ['color' => 'warning', 'text' => 'Éligible'],
            'approved' => ['color' => 'success', 'text' => 'Approuvée'],
            'paid' => ['color' => 'primary', 'text' => 'Payée'],
            'cancelled' => ['color' => 'error', 'text' => 'Annulée'],
            'adjusted' => ['color' => 'secondary', 'text' => 'Ajustée'],
            // Legacy statuses
            'en_attente' => ['color' => 'warning', 'text' => 'En attente'],
            'valide' => ['color' => 'success', 'text' => 'Validée'],
            'paye' => ['color' => 'primary', 'text' => 'Payée'],
            'annule' => ['color' => 'error', 'text' => 'Annulée'],
        ];

        $status = $this->status ?? 'calculated';

        return $statusMap[$status] ?? ['color' => 'secondary', 'text' => ucfirst($status)];
    }
}
