<?php

namespace App\Services;

use App\Models\ProfilAffilie;
use App\Models\ReferralDispensation;
use App\Models\ReferralAttribution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class PointsService
{
    /**
     * Points awarded for different referral actions
     */
    const POINTS_PER_CLICK = 1;
    const POINTS_PER_SIGNUP = 10;
    const POINTS_PER_VERIFIED_SIGNUP = 50;

    /**
     * Calculate total points earned by an affiliate
     */
    public function calculateEarnedPoints(ProfilAffilie $affiliate): int
    {
        $attributions = ReferralAttribution::where('referrer_affiliate_id', $affiliate->id)->get();
        
        $totalPoints = 0;
        
        foreach ($attributions as $attribution) {
            // Points for signup
            $totalPoints += self::POINTS_PER_SIGNUP;
            
            // Additional points if verified
            if ($attribution->verified) {
                $totalPoints += self::POINTS_PER_VERIFIED_SIGNUP;
            }
        }
        
        // Add click points (simplified - could be more complex)
        $clicksCount = $affiliate->referralClicks()->count();
        $totalPoints += $clicksCount * self::POINTS_PER_CLICK;
        
        return $totalPoints;
    }

    /**
     * Calculate total points dispensed for an affiliate
     */
    public function calculateDispensedPoints(ProfilAffilie $affiliate): int
    {
        return ReferralDispensation::where('referrer_affiliate_id', $affiliate->id)
            ->sum('points');
    }

    /**
     * Calculate current balance for an affiliate
     */
    public function calculateBalance(ProfilAffilie $affiliate): int
    {
        $earned = $this->calculateEarnedPoints($affiliate);
        $dispensed = $this->calculateDispensedPoints($affiliate);
        
        return max(0, $earned - $dispensed); // Never negative
    }

    /**
     * Get complete points summary for an affiliate
     */
    public function getPointsSummary(ProfilAffilie $affiliate): array
    {
        $earned = $this->calculateEarnedPoints($affiliate);
        $dispensed = $this->calculateDispensedPoints($affiliate);
        $balance = max(0, $earned - $dispensed);

        return [
            'earned' => $earned,
            'dispensed' => $dispensed,
            'balance' => $balance,
        ];
    }

    /**
     * Create a dispensation with balance validation and concurrency safety
     */
    public function createDispensation(
        ProfilAffilie $affiliate,
        int $points,
        string $comment,
        User $admin,
        ?string $reference = null
    ): ReferralDispensation {
        if ($points <= 0) {
            throw new Exception('Points must be greater than 0');
        }

        if (empty(trim($comment))) {
            throw new Exception('Comment is required');
        }

        return DB::transaction(function () use ($affiliate, $points, $comment, $admin, $reference) {
            // Lock the affiliate record to prevent concurrent modifications
            $lockedAffiliate = ProfilAffilie::where('id', $affiliate->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedAffiliate) {
                throw new Exception('Affiliate not found');
            }

            // Re-calculate balance with locked data
            $currentBalance = $this->calculateBalance($lockedAffiliate);

            if ($points > $currentBalance) {
                throw new Exception("Insufficient balance. Current balance: {$currentBalance} points");
            }

            // Create the dispensation
            $dispensation = ReferralDispensation::create([
                'referrer_affiliate_id' => $lockedAffiliate->id,
                'points' => $points,
                'comment' => $comment,
                'reference' => $reference ?: 'REF-' . time(),
                'created_by_admin_id' => $admin->id,
            ]);

            return $dispensation;
        });
    }

    /**
     * Get dispensation history for an affiliate
     */
    public function getDispensationHistory(ProfilAffilie $affiliate, int $perPage = 15): array
    {
        $dispensations = ReferralDispensation::where('referrer_affiliate_id', $affiliate->id)
            ->with(['createdByAdmin:id,nom_complet,email'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return [
            'dispensations' => $dispensations->items(),
            'pagination' => [
                'current_page' => $dispensations->currentPage(),
                'last_page' => $dispensations->lastPage(),
                'per_page' => $dispensations->perPage(),
                'total' => $dispensations->total(),
            ],
            'summary' => $this->getPointsSummary($affiliate),
        ];
    }

    /**
     * Get aggregated data for all affiliates (for admin table)
     */
    public function getAffiliatesWithPointsSummary(): array
    {
        $affiliates = ProfilAffilie::with([
            'utilisateur:id,nom_complet,email',
            'referralAttributions',
            'referralDispensations'
        ])->get();

        $result = [];

        foreach ($affiliates as $affiliate) {
            $summary = $this->getPointsSummary($affiliate);
            $verifiedSignups = $affiliate->referralAttributions->where('verified', true)->count();
            $totalSignups = $affiliate->referralAttributions->count();

            $result[] = [
                'id' => $affiliate->id,
                'user' => $affiliate->utilisateur,
                'verified_signups' => $verifiedSignups,
                'total_signups' => $totalSignups,
                'points_earned' => $summary['earned'],
                'points_dispensed' => $summary['dispensed'],
                'points_balance' => $summary['balance'],
                'last_dispensation' => $affiliate->referralDispensations()
                    ->latest()
                    ->first()?->created_at,
            ];
        }

        // Sort by balance descending
        usort($result, function ($a, $b) {
            return $b['points_balance'] <=> $a['points_balance'];
        });

        return $result;
    }
}
