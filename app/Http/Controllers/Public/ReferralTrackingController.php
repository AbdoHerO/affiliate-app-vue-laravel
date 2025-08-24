<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ReferralCode;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class ReferralTrackingController extends Controller
{
    public function __construct(
        protected ReferralService $referralService
    ) {}

    /**
     * Track a referral click.
     */
    public function trackClick(Request $request): JsonResponse
    {
        $request->validate([
            'ref' => 'required|string|size:8|alpha_num',
        ]);

        $referralCode = strtoupper($request->ref);
        $clientIp = $request->ip();

        // Rate limiting to prevent abuse
        $key = 'referral_click:' . $clientIp . ':' . $referralCode;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many attempts. Please try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($key, 3600); // 1 hour window

        // Verify referral code exists and is active
        $codeRecord = ReferralCode::where('code', $referralCode)
            ->where('active', true)
            ->first();

        if (!$codeRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid referral code.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Track the click
        $tracked = $this->referralService->trackClick($referralCode, $request);

        if ($tracked) {
            // Award points for click
            $autoPointsService = new \App\Services\AutoPointsDispensationService();
            $autoPointsService->awardClickPoints($referralCode);

            return response()->json([
                'success' => true,
                'message' => 'Click tracked successfully.',
                'redirect_url' => config('app.url') . '/signup',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to track click.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Get referral information for display (without tracking).
     */
    public function getReferralInfo(Request $request): JsonResponse
    {
        $request->validate([
            'ref' => 'required|string|size:8|alpha_num',
        ]);

        $referralCode = strtoupper($request->ref);

        // Verify referral code exists and is active
        $codeRecord = ReferralCode::where('code', $referralCode)
            ->where('active', true)
            ->with('affiliate.utilisateur:id,nom_complet')
            ->first();

        if (!$codeRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid referral code.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'referral_code' => $referralCode,
                'referrer_name' => $codeRecord->affiliate->utilisateur->nom_complet,
                'attribution_window_days' => ReferralService::ATTRIBUTION_WINDOW_DAYS,
                'signup_url' => config('app.url') . '/affiliate-signup?ref=' . $referralCode,
            ],
        ]);
    }

    /**
     * Validate a referral code without tracking.
     */
    public function validateCode(Request $request): JsonResponse
    {
        $request->validate([
            'ref' => 'required|string|size:8|alpha_num',
        ]);

        $referralCode = strtoupper($request->ref);

        $isValid = ReferralCode::where('code', $referralCode)
            ->where('active', true)
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'valid' => $isValid,
                'code' => $referralCode,
            ],
        ]);
    }

    /**
     * Get attribution window information.
     */
    public function getAttributionInfo(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'attribution_window_days' => ReferralService::ATTRIBUTION_WINDOW_DAYS,
                'cookie_name' => ReferralService::REFERRAL_COOKIE_NAME,
                'how_it_works' => [
                    'fr' => [
                        'step1' => 'Cliquez sur le lien de parrainage',
                        'step2' => 'Inscrivez-vous dans les ' . ReferralService::ATTRIBUTION_WINDOW_DAYS . ' jours',
                        'step3' => 'Vérifiez votre email',
                        'step4' => 'Le parrain reçoit des points manuellement',
                    ],
                    'en' => [
                        'step1' => 'Click on the referral link',
                        'step2' => 'Sign up within ' . ReferralService::ATTRIBUTION_WINDOW_DAYS . ' days',
                        'step3' => 'Verify your email',
                        'step4' => 'Referrer receives points manually',
                    ],
                    'ar' => [
                        'step1' => 'انقر على رابط الإحالة',
                        'step2' => 'سجل خلال ' . ReferralService::ATTRIBUTION_WINDOW_DAYS . ' أيام',
                        'step3' => 'تحقق من بريدك الإلكتروني',
                        'step4' => 'يحصل المُحيل على نقاط يدوياً',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Handle referral redirect (for direct link clicks).
     */
    public function handleRedirect(Request $request): JsonResponse
    {
        $request->validate([
            'ref' => 'required|string|size:8|alpha_num',
            'redirect' => 'nullable|string|in:signup,home',
        ]);

        $referralCode = strtoupper($request->ref);
        $redirectTo = $request->get('redirect', 'signup');

        // Track the click
        $tracked = $this->referralService->trackClick($referralCode, $request);

        if (!$tracked) {
            Log::warning('Failed to track referral click for redirect', [
                'referral_code' => $referralCode,
                'ip' => $request->ip(),
            ]);
        }

        // Determine redirect URL
        $redirectUrl = match ($redirectTo) {
            'signup' => config('app.url') . '/affiliate-signup',
            'home' => config('app.url'),
            default => config('app.url') . '/affiliate-signup',
        };

        return response()->json([
            'success' => true,
            'data' => [
                'redirect_url' => $redirectUrl,
                'tracked' => $tracked,
            ],
        ]);
    }

    /**
     * Get anti-abuse information for monitoring.
     */
    public function getAbuseInfo(Request $request): JsonResponse
    {
        // This endpoint is for internal monitoring only
        if (!$request->user() || !$request->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied.',
            ], Response::HTTP_FORBIDDEN);
        }

        $clientIp = $request->ip();
        $ipHash = hash('sha256', $clientIp . config('app.key'));

        // Get recent activity for this IP
        $recentClicks = \App\Models\ReferralClick::where('ip_hash', $ipHash)
            ->where('clicked_at', '>=', now()->subHours(24))
            ->count();

        $recentAttributions = \App\Models\ReferralAttribution::where('ip_hash', $ipHash)
            ->where('attributed_at', '>=', now()->subHours(24))
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'ip_hash' => $ipHash,
                'recent_clicks_24h' => $recentClicks,
                'recent_attributions_24h' => $recentAttributions,
                'risk_level' => $this->calculateRiskLevel($recentClicks, $recentAttributions),
            ],
        ]);
    }

    /**
     * Calculate risk level based on activity.
     */
    private function calculateRiskLevel(int $clicks, int $attributions): string
    {
        if ($clicks > 50 || $attributions > 10) {
            return 'high';
        }

        if ($clicks > 20 || $attributions > 5) {
            return 'medium';
        }

        return 'low';
    }
}
