<?php

namespace App\Services;

use App\Models\ReferralAttribution;
use App\Models\ReferralClick;
use App\Models\ReferralDispensation;
use App\Models\ProfilAffilie;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AutoPointsDispensationService
{
    // Points configuration
    const POINTS_PER_CLICK = 1;
    const POINTS_PER_SIGNUP = 10;
    const POINTS_PER_VERIFICATION = 50;

    /**
     * Award points for a referral click
     */
    public function awardClickPoints(string $referralCode): void
    {
        try {
            // Find the affiliate for this referral code
            $referralCodeRecord = \App\Models\ReferralCode::where('code', $referralCode)
                ->where('active', true)
                ->with('affiliate')
                ->first();

            if (!$referralCodeRecord) {
                return;
            }

            // Create dispensation for click
            ReferralDispensation::create([
                'referrer_affiliate_id' => $referralCodeRecord->affiliate_id,
                'points' => self::POINTS_PER_CLICK,
                'comment' => "Points pour clic sur lien de parrainage {$referralCode}",
                'reference' => 'CLICK-' . time() . '-' . substr($referralCode, -4),
                'created_by_admin_id' => $this->getSystemAdminId(),
            ]);

            // Update affiliate points
            $referralCodeRecord->affiliate->increment('points', self::POINTS_PER_CLICK);

            Log::info('Click points awarded', [
                'referral_code' => $referralCode,
                'affiliate_id' => $referralCodeRecord->affiliate_id,
                'points' => self::POINTS_PER_CLICK,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to award click points', [
                'referral_code' => $referralCode,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Award points for a referral signup
     */
    public function awardSignupPoints(ReferralAttribution $attribution): void
    {
        try {
            // Check if signup points already awarded
            $existingDispensation = ReferralDispensation::where('referrer_affiliate_id', $attribution->referrer_affiliate_id)
                ->where('reference', 'SIGNUP-' . $attribution->id)
                ->first();

            if ($existingDispensation) {
                return; // Already awarded
            }

            // Create dispensation for signup
            ReferralDispensation::create([
                'referrer_affiliate_id' => $attribution->referrer_affiliate_id,
                'points' => self::POINTS_PER_SIGNUP,
                'comment' => "Points pour inscription via parrainage {$attribution->referral_code}",
                'reference' => 'SIGNUP-' . $attribution->id,
                'created_by_admin_id' => $this->getSystemAdminId(),
            ]);

            // Update affiliate points
            $attribution->referrerAffiliate->increment('points', self::POINTS_PER_SIGNUP);

            Log::info('Signup points awarded', [
                'attribution_id' => $attribution->id,
                'affiliate_id' => $attribution->referrer_affiliate_id,
                'points' => self::POINTS_PER_SIGNUP,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to award signup points', [
                'attribution_id' => $attribution->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Award points for a referral verification
     */
    public function awardVerificationPoints(ReferralAttribution $attribution): void
    {
        try {
            // Check if verification points already awarded
            $existingDispensation = ReferralDispensation::where('referrer_affiliate_id', $attribution->referrer_affiliate_id)
                ->where('reference', 'VERIFY-' . $attribution->id)
                ->first();

            if ($existingDispensation) {
                return; // Already awarded
            }

            // Create dispensation for verification
            ReferralDispensation::create([
                'referrer_affiliate_id' => $attribution->referrer_affiliate_id,
                'points' => self::POINTS_PER_VERIFICATION,
                'comment' => "Points pour vérification via parrainage {$attribution->referral_code}",
                'reference' => 'VERIFY-' . $attribution->id,
                'created_by_admin_id' => $this->getSystemAdminId(),
            ]);

            // Update affiliate points
            $attribution->referrerAffiliate->increment('points', self::POINTS_PER_VERIFICATION);

            Log::info('Verification points awarded', [
                'attribution_id' => $attribution->id,
                'affiliate_id' => $attribution->referrer_affiliate_id,
                'points' => self::POINTS_PER_VERIFICATION,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to award verification points', [
                'attribution_id' => $attribution->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Award all pending points for an attribution (signup + verification if applicable)
     */
    public function awardAllPointsForAttribution(ReferralAttribution $attribution): void
    {
        // Award signup points
        $this->awardSignupPoints($attribution);

        // Award verification points if verified
        if ($attribution->verified) {
            $this->awardVerificationPoints($attribution);
        }
    }

    /**
     * Retroactively award points for existing attributions
     */
    public function awardRetroactivePoints(): array
    {
        $results = [
            'clicks_processed' => 0,
            'signups_processed' => 0,
            'verifications_processed' => 0,
            'total_points_awarded' => 0,
        ];

        try {
            // Award points for all existing clicks
            $clicks = ReferralClick::all();
            foreach ($clicks as $click) {
                $this->awardClickPoints($click->referral_code);
                $results['clicks_processed']++;
                $results['total_points_awarded'] += self::POINTS_PER_CLICK;
            }

            // Award points for all existing attributions
            $attributions = ReferralAttribution::all();
            foreach ($attributions as $attribution) {
                $this->awardAllPointsForAttribution($attribution);
                $results['signups_processed']++;
                $results['total_points_awarded'] += self::POINTS_PER_SIGNUP;

                if ($attribution->verified) {
                    $results['verifications_processed']++;
                    $results['total_points_awarded'] += self::POINTS_PER_VERIFICATION;
                }
            }

            Log::info('Retroactive points awarded', $results);

        } catch (\Exception $e) {
            Log::error('Failed to award retroactive points', [
                'error' => $e->getMessage(),
                'results' => $results,
            ]);
        }

        return $results;
    }

    /**
     * Get system admin ID for auto-generated dispensations
     */
    private function getSystemAdminId(): string
    {
        // Get the first admin user or create a system admin
        $admin = \App\Models\User::role('admin')->first();

        if (!$admin) {
            // Fallback: get any user with admin role or the first user
            $admin = \App\Models\User::first();
        }

        return $admin->id;
    }
}
