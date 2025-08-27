<?php

namespace App\Services;

use App\Models\Withdrawal;
use App\Models\WithdrawalItem;
use App\Models\CommissionAffilie;
use App\Models\User;
use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class WithdrawalService
{
    /**
     * Get eligible commissions for a user
     */
    public function getEligibleCommissions(User $user, array $filters = [])
    {
        $query = CommissionAffilie::where('user_id', $user->id)
            ->whereIn('status', [CommissionAffilie::STATUS_APPROVED, CommissionAffilie::STATUS_ELIGIBLE])
            ->whereNull('paid_withdrawal_id') // Not already reserved/paid
            ->with(['commande:id,statut,total_ttc,created_at', 'commandeArticle.produit:id,titre,sku']);

        // Apply filters
        if (!empty($filters['status'])) {
            $query->whereIn('status', (array) $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['min_amount'])) {
            $query->where('amount', '>=', $filters['min_amount']);
        }

        return $query->orderBy('created_at', 'asc');
    }

    /**
     * Auto-select commissions for a given amount
     */
    public function autoSelectCommissionsForAmount(User $user, float $targetAmount): array
    {
        $commissions = $this->getEligibleCommissions($user)->get();
        $selected = [];
        $totalAmount = 0;

        foreach ($commissions as $commission) {
            if ($totalAmount >= $targetAmount) {
                break;
            }

            $selected[] = $commission;
            $totalAmount += $commission->amount;
        }

        return [
            'commissions' => $selected,
            'total_amount' => $totalAmount,
            'target_amount' => $targetAmount,
            'is_exact' => $totalAmount == $targetAmount,
            'is_sufficient' => $totalAmount >= $targetAmount,
        ];
    }

    /**
     * Create a new withdrawal
     */
    public function createWithdrawal(User $user, array $data): Withdrawal
    {
        return DB::transaction(function () use ($user, $data) {
            // Snapshot user's bank info
            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'amount' => $data['amount'] ?? 0,
                'status' => Withdrawal::STATUS_PENDING,
                'method' => $data['method'] ?? Withdrawal::METHOD_BANK_TRANSFER,
                'iban_rib' => $user->rib,
                'bank_type' => $user->bank_type,
                'notes' => $data['notes'] ?? null,
                'meta' => [
                    'created_by_admin' => true,
                    'user_snapshot' => [
                        'nom_complet' => $user->nom_complet,
                        'email' => $user->email,
                        'rib' => $user->rib,
                        'bank_type' => $user->bank_type,
                    ]
                ]
            ]);

            // Attach commissions if provided
            if (!empty($data['commission_ids'])) {
                $this->attachCommissions($withdrawal, $data['commission_ids']);
            } elseif (!empty($data['amount']) && $data['amount'] > 0) {
                // Auto-select commissions for the amount
                $result = $this->autoSelectCommissionsForAmount($user, $data['amount']);
                if (!empty($result['commissions'])) {
                    $commissionIds = collect($result['commissions'])->pluck('id')->toArray();
                    $this->attachCommissions($withdrawal, $commissionIds);
                }
            }

            Log::info('Withdrawal created', [
                'withdrawal_id' => $withdrawal->id,
                'user_id' => $user->id,
                'amount' => $withdrawal->amount
            ]);

            return $withdrawal->fresh(['user', 'items.commission']);
        });
    }

    /**
     * Attach commissions to a withdrawal
     */
    public function attachCommissions(Withdrawal $withdrawal, array $commissionIds): array
    {
        $attached = [];
        $errors = [];

        foreach ($commissionIds as $commissionId) {
            try {
                $commission = CommissionAffilie::findOrFail($commissionId);

                // Validate commission eligibility
                if (!$this->isCommissionEligible($commission, $withdrawal->user_id)) {
                    $errors[] = "Commission {$commissionId} is not eligible";
                    continue;
                }

                // Check if already attached
                if (WithdrawalItem::where('commission_id', $commissionId)->exists()) {
                    $errors[] = "Commission {$commissionId} is already attached to another withdrawal";
                    continue;
                }

                // Create withdrawal item
                WithdrawalItem::create([
                    'withdrawal_id' => $withdrawal->id,
                    'commission_id' => $commission->id,
                    'amount' => $commission->amount,
                ]);

                $attached[] = $commission->id;

            } catch (\Exception $e) {
                $errors[] = "Error attaching commission {$commissionId}: " . $e->getMessage();
            }
        }

        // Update withdrawal total amount
        $totalAmount = WithdrawalItem::where('withdrawal_id', $withdrawal->id)->sum('amount');
        $withdrawal->update(['amount' => $totalAmount]);

        return [
            'attached' => $attached,
            'errors' => $errors,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Detach commissions from a withdrawal
     */
    public function detachCommissions(Withdrawal $withdrawal, array $commissionIds): array
    {
        $detached = [];

        foreach ($commissionIds as $commissionId) {
            $deleted = WithdrawalItem::where('withdrawal_id', $withdrawal->id)
                ->where('commission_id', $commissionId)
                ->delete();

            if ($deleted > 0) {
                $detached[] = $commissionId;
            }
        }

        // Update withdrawal total amount
        $totalAmount = WithdrawalItem::where('withdrawal_id', $withdrawal->id)->sum('amount');
        $withdrawal->update(['amount' => $totalAmount]);

        return [
            'detached' => $detached,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Reserve commissions (approve withdrawal)
     */
    public function reserveCommissions(Withdrawal $withdrawal): void
    {
        DB::transaction(function () use ($withdrawal) {
            // Update all linked commissions
            $commissionIds = $withdrawal->items()->pluck('commission_id');
            
            CommissionAffilie::whereIn('id', $commissionIds)
                ->update(['paid_withdrawal_id' => $withdrawal->id]);

            // Update withdrawal status
            $withdrawal->update([
                'status' => Withdrawal::STATUS_APPROVED,
                'approved_at' => now(),
            ]);

            Log::info('Commissions reserved for withdrawal', [
                'withdrawal_id' => $withdrawal->id,
                'commission_count' => $commissionIds->count()
            ]);
        });
    }

    /**
     * Release commissions (reject/cancel withdrawal)
     */
    public function releaseCommissions(Withdrawal $withdrawal): void
    {
        DB::transaction(function () use ($withdrawal) {
            // Release all linked commissions
            $commissionIds = $withdrawal->items()->pluck('commission_id');
            
            CommissionAffilie::whereIn('id', $commissionIds)
                ->update(['paid_withdrawal_id' => null]);

            Log::info('Commissions released from withdrawal', [
                'withdrawal_id' => $withdrawal->id,
                'commission_count' => $commissionIds->count()
            ]);
        });
    }

    /**
     * Mark withdrawal as paid
     */
    public function markAsPaid(Withdrawal $withdrawal, array $data = []): void
    {
        DB::transaction(function () use ($withdrawal, $data) {
            // Update withdrawal
            $withdrawal->update([
                'status' => Withdrawal::STATUS_PAID,
                'paid_at' => $data['paid_at'] ?? now(),
                'payment_ref' => $data['payment_ref'] ?? null,
                'evidence_path' => $data['evidence_path'] ?? null,
            ]);

            // Mark all linked commissions as paid
            $commissionIds = $withdrawal->items()->pluck('commission_id');
            
            CommissionAffilie::whereIn('id', $commissionIds)
                ->update([
                    'status' => CommissionAffilie::STATUS_PAID,
                    'paid_at' => $data['paid_at'] ?? now(),
                ]);

            Log::info('Withdrawal marked as paid', [
                'withdrawal_id' => $withdrawal->id,
                'commission_count' => $commissionIds->count(),
                'amount' => $withdrawal->amount
            ]);
        });
    }

    /**
     * Handle evidence file upload
     */
    public function uploadEvidence(UploadedFile $file): string
    {
        // Ensure the withdrawals directory exists
        if (!Storage::disk('public')->exists('withdrawals')) {
            Storage::disk('public')->makeDirectory('withdrawals');
        }

        $filename = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('withdrawals', $filename, 'public');

        return $path;
    }

    /**
     * Check if commission is eligible for withdrawal
     */
    private function isCommissionEligible(CommissionAffilie $commission, string $userId): bool
    {
        return $commission->user_id === $userId
            && in_array($commission->status, [CommissionAffilie::STATUS_APPROVED, CommissionAffilie::STATUS_ELIGIBLE])
            && is_null($commission->paid_withdrawal_id);
    }

    /**
     * Get withdrawal summary statistics
     */
    public function getSummaryStats(array $filters = []): array
    {
        $query = Withdrawal::query();

        // Apply filters
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $stats = [];
        foreach (Withdrawal::getStatuses() as $status) {
            $stats[$status] = [
                'count' => (clone $query)->where('status', $status)->count(),
                'amount' => (clone $query)->where('status', $status)->sum('amount'),
            ];
        }

        $stats['total'] = [
            'count' => (clone $query)->count(),
            'amount' => (clone $query)->sum('amount'),
        ];

        return $stats;
    }
}
