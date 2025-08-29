<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ReferralCode;
use App\Models\ReferralClick;
use App\Models\ReferralAttribution;
use App\Models\ProfilAffilie;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PublicController extends Controller
{
    /**
     * Get referral information for a given code
     */
    public function getReferralInfo(string $code): JsonResponse
    {
        try {
            $referralCode = ReferralCode::where('code', $code)
                ->where('active', true)
                ->with(['affiliate.utilisateur:id,nom_complet,email'])
                ->first();

            if (!$referralCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Referral code not found or inactive',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'code' => $referralCode->code,
                    'affiliate_name' => $referralCode->affiliate->utilisateur->nom_complet,
                    'affiliate_email' => $referralCode->affiliate->utilisateur->email,
                    'is_active' => $referralCode->active,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch referral info: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Track a referral click
     */
    public function trackReferralClick(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'referral_code' => 'required|string|exists:referral_codes,code',
            'source' => 'string|in:web,mobile,social',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $referralCode = ReferralCode::where('code', $request->referral_code)
                ->where('active', true)
                ->first();

            if (!$referralCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Referral code not found or inactive',
                ], 404);
            }

            // Create click record
            ReferralClick::create([
                'referral_code' => $request->referral_code,
                'ip_hash' => hash('sha256', $request->ip()),
                'user_agent_hash' => hash('sha256', $request->userAgent()),
                'source' => $request->source ?? 'web',
                'clicked_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Referral click tracked successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to track referral click: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle public user signup with referral tracking
     */
    public function signup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nom_complet' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'telephone' => 'required|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'adresse' => 'required|string|max:500',
            'ville' => 'required|string|max:100',
            'pays' => 'required|string|max:100',
            'accept_terms' => 'required|accepted',
            'referral_code' => 'nullable|string|exists:referral_codes,code',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request) {
                // Get referring affiliate if referral code provided
                $referringAffiliateId = null;
                if ($request->referral_code) {
                    $referralCodeRecord = ReferralCode::where('code', $request->referral_code)
                        ->where('active', true)
                        ->first();

                    if ($referralCodeRecord) {
                        $referringAffiliateId = $referralCodeRecord->affiliate_id;
                    }
                }

                // Create the user
                $user = User::create([
                    'nom_complet' => $request->nom_complet,
                    'email' => $request->email,
                    'telephone' => $request->telephone,
                    'mot_de_passe_hash' => Hash::make($request->password),
                    'adresse' => $request->adresse,
                    'ville' => $request->ville,
                    'pays' => $request->pays,
                    'email_verifie' => false,
                    'statut' => 'actif',
                    'kyc_statut' => 'non_requis',
                    'approval_status' => 'pending_approval',
                    'affiliate_parrained_by' => $referringAffiliateId, // Set the referring affiliate
                ]);

                // Assign default role (customer/user)
                $user->assignRole('user');

                // Note: No points are awarded at signup anymore - points will be awarded when user has delivered orders

                return response()->json([
                    'success' => true,
                    'message' => 'Account created successfully',
                    'data' => [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'referral_attributed' => !empty($request->referral_code),
                    ],
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create account: ' . $e->getMessage(),
            ], 500);
        }
    }



    /**
     * Verify email and update referral attribution
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Find user by email
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            // Verify email (simplified - in real app you'd verify the token)
            $user->update([
                'email_verifie' => true,
                'email_verified_at' => now(),
            ]);

            // Update referral attribution to verified
            ReferralAttribution::where('new_user_id', $user->id)
                ->where('verified', false)
                ->update([
                    'verified' => true,
                    'verified_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get public statistics (for landing page, etc.)
     */
    public function getPublicStats(): JsonResponse
    {
        try {
            $stats = [
                'total_affiliates' => ProfilAffilie::where('statut', 'actif')->count(),
                'total_referrals' => ReferralAttribution::count(),
                'total_verified_referrals' => ReferralAttribution::where('verified', true)->count(),
                'total_clicks' => ReferralClick::count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch public stats: ' . $e->getMessage(),
            ], 500);
        }
    }
}
