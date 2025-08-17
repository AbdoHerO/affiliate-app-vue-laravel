<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Withdrawal extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'withdrawals';

    /**
     * Withdrawal status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_IN_PAYMENT = 'in_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELED = 'canceled';

    /**
     * Withdrawal method constants
     */
    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'amount',
        'status',
        'method',
        'iban_rib',
        'bank_type',
        'notes',
        'admin_reason',
        'payment_ref',
        'evidence_path',
        'approved_at',
        'paid_at',
        'meta',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user (affiliate) for this withdrawal.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the withdrawal items for this withdrawal.
     */
    public function items(): HasMany
    {
        return $this->hasMany(WithdrawalItem::class, 'withdrawal_id');
    }

    /**
     * Get the commissions linked to this withdrawal through items.
     */
    public function commissions()
    {
        return $this->hasManyThrough(
            CommissionAffilie::class,
            WithdrawalItem::class,
            'withdrawal_id',
            'id',
            'id',
            'commission_id'
        );
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, $status)
    {
        if (is_array($status)) {
            return $query->whereIn('status', $status);
        }
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Filter by method
     */
    public function scopeByMethod($query, $method)
    {
        return $query->where('method', $method);
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeByDateRange($query, $from = null, $to = null)
    {
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }
        return $query;
    }

    /**
     * Scope: Search by user name/email or payment ref
     */
    public function scopeSearch($query, $search)
    {
        if (!$search) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('payment_ref', 'like', "%{$search}%")
              ->orWhereHas('user', function ($userQuery) use ($search) {
                  $userQuery->where('nom_complet', 'like', "%{$search}%")
                           ->orWhere('email', 'like', "%{$search}%");
              });
        });
    }

    /**
     * Check if withdrawal can be approved
     */
    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if withdrawal can be rejected
     */
    public function canBeRejected(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    /**
     * Check if withdrawal can be marked as in payment
     */
    public function canBeMarkedInPayment(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if withdrawal can be marked as paid
     */
    public function canBeMarkedPaid(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_IN_PAYMENT]);
    }

    /**
     * Check if withdrawal can be canceled
     */
    public function canBeCanceled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    /**
     * Get total commission amount for this withdrawal
     */
    public function getTotalCommissionAmountAttribute(): float
    {
        return $this->items()->sum('amount');
    }

    /**
     * Get commission count for this withdrawal
     */
    public function getCommissionCountAttribute(): int
    {
        return $this->items()->count();
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPROVED => 'info',
            self::STATUS_IN_PAYMENT => 'primary',
            self::STATUS_PAID => 'success',
            self::STATUS_REJECTED => 'error',
            self::STATUS_CANCELED => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Get all available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_IN_PAYMENT,
            self::STATUS_PAID,
            self::STATUS_REJECTED,
            self::STATUS_CANCELED,
        ];
    }

    /**
     * Get all available methods
     */
    public static function getMethods(): array
    {
        return [
            self::METHOD_BANK_TRANSFER,
        ];
    }
}
