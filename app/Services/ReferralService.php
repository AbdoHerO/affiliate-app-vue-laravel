<?php

namespace App\Services;

use App\Models\ProfilAffilie;
use App\Models\ReferralCode;
use App\Models\ReferralClick;
use App\Models\ReferralAttribution;
use App\Models\ReferralDispensation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

class ReferralService
{
    /**
     * Attribution window in days.
     */
    const ATTRIBUTION_WINDOW_DAYS = 30;

    /**
     * Cookie name for referral tracking.
     */
    const REFERRAL_COOKIE_NAME = 'referral_code';

    /**
     * Get or create a referral code for an affiliate.
     */
    public function getOrCreateReferralCode(ProfilAffilie $affiliate): ReferralCode
    {
        return ReferralCode::getOrCreateForAffiliate($affiliate);
    }

    /**
     * Generate the full referral URL for an affiliate.
     */
    public function generateReferralUrl(ProfilAffilie $affiliate): string
    {
        $referralCode = $this->getOrCreateReferralCode($affiliate);
        $baseUrl = config('app.url');
        
        return "{$baseUrl}/affiliate-signup?ref={$referralCode->code}";
    }

    /**
     * Track a referral click.
     */
    public function trackClick(string $referralCode, Request $request): bool
    {
        try {
            // Verify the referral code exists and is active
            $codeRecord = ReferralCode::where('code', $referralCode)
                ->where('active', true)
                ->first();

            if (!$codeRecord) {
                return false;
            }

            // Record the click
            ReferralClick::recordClick(
                $referralCode,
                $request->ip(),
                $request->userAgent(),
                $request->header('referer'),
                $this->generateDeviceFingerprint($request)
            );

            // Set the referral cookie
            Cookie::queue(
                self::REFERRAL_COOKIE_NAME,
                $referralCode,
                self::ATTRIBUTION_WINDOW_DAYS * 24 * 60 // Convert days to minutes
            );

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to track referral click', [
                'referral_code' => $referralCode,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Attribute a signup to a referrer if applicable.
     */
    public function attributeSignup(User $newUser, Request $request): ?ReferralAttribution
    {
        try {
            // Get referral code from cookie or request parameter
            $referralCode = $request->cookie(self::REFERRAL_COOKIE_NAME) 
                ?? $request->get('ref');

            if (!$referralCode) {
                return null;
            }

            // Find the referral code and affiliate
            $codeRecord = ReferralCode::where('code', $referralCode)
                ->where('active', true)
                ->with('affiliate')
                ->first();

            if (!$codeRecord) {
                return null;
            }

            // Prevent self-referral
            if ($this->isSelfReferral($newUser, $codeRecord->affiliate)) {
                Log::warning('Self-referral attempt blocked', [
                    'user_id' => $newUser->id,
                    'affiliate_id' => $codeRecord->affiliate->id,
                ]);
                return null;
            }

            // Create the attribution
            $attribution = ReferralAttribution::createAttribution(
                $newUser,
                $codeRecord->affiliate,
                $referralCode,
                $request->ip(),
                $this->detectSource($request),
                $this->generateDeviceFingerprint($request)
            );

            // Clear the referral cookie
            Cookie::queue(Cookie::forget(self::REFERRAL_COOKIE_NAME));

            return $attribution;
        } catch (\Exception $e) {
            Log::error('Failed to attribute signup', [
                'user_id' => $newUser->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Mark a user's referral attribution as verified.
     */
    public function markAttributionAsVerified(User $user): void
    {
        $attribution = $user->referralAttribution;
        if ($attribution && !$attribution->verified) {
            $attribution->markAsVerified();
        }
    }

    /**
     * Get referral statistics for an affiliate.
     */
    public function getAffiliateStats(ProfilAffilie $affiliate, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ? $startDate->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $endDate ? $endDate->endOfDay() : now()->endOfDay();

        $referralCode = $this->getOrCreateReferralCode($affiliate);

        // Get click count (unique by IP per day) for this affiliate's referral code
        $clicksCount = ReferralClick::where('referral_code', $referralCode->code)
            ->whereBetween('clicked_at', [$startDate, $endDate])
            ->selectRaw('COUNT(DISTINCT CONCAT(ip_hash, DATE(clicked_at))) as unique_clicks')
            ->value('unique_clicks') ?? 0;

        // Get attribution counts for this affiliate
        $totalSignups = ReferralAttribution::where('referrer_affiliate_id', $affiliate->id)
            ->whereBetween('attributed_at', [$startDate, $endDate])
            ->count();

        $verifiedSignups = ReferralAttribution::where('referrer_affiliate_id', $affiliate->id)
            ->whereBetween('attributed_at', [$startDate, $endDate])
            ->where('verified', true)
            ->count();

        // Get total points from dispensations (all time for this affiliate)
        $totalPoints = ReferralDispensation::where('referrer_affiliate_id', $affiliate->id)
            ->sum('points') ?? 0;

        // Calculate conversion rate
        $conversionRate = $clicksCount > 0 ? ($totalSignups / $clicksCount) * 100 : 0;
        $verifiedConversionRate = $clicksCount > 0 ? ($verifiedSignups / $clicksCount) * 100 : 0;

        return [
            'clicks' => $clicksCount,
            'signups' => $totalSignups,
            'verified_signups' => $verifiedSignups,
            'conversion_rate' => round($conversionRate, 2),
            'verified_conversion_rate' => round($verifiedConversionRate, 2),
            'total_points' => $totalPoints,
            'referral_url' => $this->generateReferralUrl($affiliate),
        ];
    }

    /**
     * Create a manual dispensation.
     */
    public function createDispensation(
        ProfilAffilie $affiliate,
        int $points,
        string $comment,
        User $admin,
        ?string $reference = null
    ): ReferralDispensation {
        return ReferralDispensation::createDispensation(
            $affiliate,
            $points,
            $comment,
            $admin,
            $reference
        );
    }

    /**
     * Check if this is a self-referral attempt.
     */
    private function isSelfReferral(User $newUser, ProfilAffilie $affiliate): bool
    {
        // Check if the new user's email matches the affiliate's user email
        return $newUser->email === $affiliate->utilisateur->email;
    }

    /**
     * Detect the source of the signup.
     */
    private function detectSource(Request $request): string
    {
        $userAgent = $request->userAgent();
        
        if (str_contains($userAgent, 'Mobile') || str_contains($userAgent, 'Android') || str_contains($userAgent, 'iPhone')) {
            return 'mobile';
        }
        
        return 'web';
    }

    /**
     * Generate a device fingerprint for fraud detection.
     */
    private function generateDeviceFingerprint(Request $request): array
    {
        return [
            'user_agent' => $request->userAgent(),
            'accept_language' => $request->header('accept-language'),
            'accept_encoding' => $request->header('accept-encoding'),
            'screen_resolution' => $request->header('x-screen-resolution'), // If provided by frontend
        ];
    }
}
