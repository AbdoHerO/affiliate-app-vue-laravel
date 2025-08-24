<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\AffiliateVerifyMail;
use App\Models\Affilie;
use App\Models\AffiliateEmailVerification;
use App\Models\ReferralCode;
use App\Models\ReferralAttribution;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AffiliateSignupController extends Controller
{
    /**
     * Handle affiliate signup
     */
    public function signup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom_complet' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('affilies', 'email'),
                Rule::unique('users', 'email'),
            ],
            'telephone' => 'required|string|max:20|regex:/^[+]?[0-9\s\-\(\)]+$/',
            'password' => 'required|string|min:8|confirmed',
            'adresse' => 'required|string|max:500',
            'ville' => 'required|string|max:100',
            'pays' => 'required|string|max:100',
            'rib' => 'required|string|max:34',
            'bank_type' => 'required|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'accept_terms' => 'required|accepted',
            'referral_code' => 'nullable|string|exists:referral_codes,code',
        ], [
            'nom_complet.required' => 'Le nom complet est requis.',
            'email.required' => 'L\'adresse email est requise.',
            'email.email' => 'L\'adresse email doit être valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'telephone.required' => 'Le numéro de téléphone est requis.',
            'telephone.regex' => 'Le format du numéro de téléphone n\'est pas valide.',
            'password.required' => 'Le mot de passe est requis.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'adresse.required' => 'L\'adresse est requise.',
            'ville.required' => 'La ville est requise.',
            'pays.required' => 'Le pays est requis.',
            'rib.required' => 'Le RIB est requis.',
            'rib.max' => 'Le RIB ne peut pas dépasser 34 caractères.',
            'bank_type.required' => 'Le type de banque est requis.',
            'bank_type.max' => 'Le type de banque ne peut pas dépasser 50 caractères.',
            'accept_terms.required' => 'Vous devez accepter les conditions d\'utilisation.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create affiliate application
            $affilie = Affilie::create([
                'nom_complet' => $request->nom_complet,
                'email' => strtolower(trim($request->email)),
                'telephone' => $request->telephone,
                'adresse' => $request->adresse,
                'ville' => $request->ville,
                'pays' => $request->pays,
                'mot_de_passe_hash' => Hash::make($request->password),
                'rib' => $request->rib,
                'bank_type' => $request->bank_type,
                'notes' => $request->notes,
                'approval_status' => 'pending_approval',
                'email_verified_at' => null,
            ]);

            // Create verification token
            $verification = AffiliateEmailVerification::create([
                'affilie_id' => $affilie->id,
                'token' => Str::random(64),
                'expires_at' => now()->addHours(48),
            ]);

            // Send verification email
            Mail::to($affilie->email)->send(new AffiliateVerifyMail($affilie, $verification));

            // Handle referral attribution if referral code provided
            if ($request->referral_code) {
                $this->createReferralAttribution($request->referral_code, $affilie, $request);
            }

            return response()->json([
                'success' => true,
                'message' => 'Inscription réussie ! Vérifiez votre email pour continuer.',
                'data' => [
                    'email' => $affilie->email,
                    'expires_at' => $verification->expires_at,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer.',
            ], 500);
        }
    }

    /**
     * Resend verification email
     */
    public function resendVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:affilies,email',
        ], [
            'email.required' => 'L\'adresse email est requise.',
            'email.email' => 'L\'adresse email doit être valide.',
            'email.exists' => 'Aucune demande d\'affiliation trouvée pour cette adresse email.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $affilie = Affilie::where('email', strtolower(trim($request->email)))->first();

            if ($affilie->isEmailVerified()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette adresse email est déjà vérifiée.',
                ], 409);
            }

            // Invalidate old tokens
            AffiliateEmailVerification::where('affilie_id', $affilie->id)->delete();

            // Create new verification token
            $verification = AffiliateEmailVerification::create([
                'affilie_id' => $affilie->id,
                'token' => Str::random(64),
                'expires_at' => now()->addHours(48),
            ]);

            // Send verification email
            Mail::to($affilie->email)->send(new AffiliateVerifyMail($affilie, $verification));

            return response()->json([
                'success' => true,
                'message' => 'Email de vérification renvoyé avec succès.',
                'data' => [
                    'email' => $affilie->email,
                    'expires_at' => $verification->expires_at,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi de l\'email.',
            ], 500);
        }
    }

    /**
     * Verify email address
     */
    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return redirect('/affiliate-signup?error=invalid_link');
        }

        try {
            $verification = AffiliateEmailVerification::where('token', $request->token)
                ->with('affilie')
                ->first();

            if (!$verification) {
                return redirect('/affiliate-signup?error=invalid_token');
            }

            if ($verification->isExpired()) {
                return redirect('/affiliate-signup?error=expired_token&email=' . urlencode($request->email));
            }

            $affilie = $verification->affilie;

            if (!$affilie || $affilie->email !== strtolower(trim($request->email))) {
                return redirect('/affiliate-signup?error=invalid_email');
            }

            if ($affilie->isEmailVerified()) {
                return redirect('/affiliate-verified?already_verified=1');
            }

            // Mark email as verified
            $affilie->update([
                'email_verified_at' => now(),
            ]);

            // Update referral attribution to verified if exists
            ReferralAttribution::where('device_fingerprint', 'affiliate_' . $affilie->id)
                ->where('verified', false)
                ->update([
                    'verified' => true,
                    'verified_at' => now(),
                ]);

            // Delete verification token
            $verification->delete();

            return redirect('/affiliate-verified');

        } catch (\Exception $e) {
            return redirect('/affiliate-signup?error=server_error');
        }
    }

    /**
     * Create referral attribution for affiliate signup
     */
    private function createReferralAttribution(string $referralCode, Affilie $newAffiliate, Request $request): void
    {
        try {
            $referralCodeRecord = ReferralCode::where('code', $referralCode)
                ->where('active', true)
                ->first();

            if (!$referralCodeRecord) {
                return; // Silently fail if referral code is invalid
            }

            // Create a dummy user record for affiliate signup tracking
            // Since new_user_id cannot be null, we'll create a special user record
            $dummyUser = \App\Models\User::create([
                'nom_complet' => $newAffiliate->nom_complet . ' (Affiliate)',
                'email' => 'affiliate_' . $newAffiliate->id . '@internal.system',
                'telephone' => $newAffiliate->telephone,
                'mot_de_passe_hash' => Hash::make('dummy_password_' . time()),
                'adresse' => $newAffiliate->adresse,
                'statut' => 'actif',
                'email_verifie' => false,
                'kyc_statut' => 'non_requis',
                'approval_status' => 'approved', // Use valid enum value
            ]);

            // Create attribution record for affiliate signup
            ReferralAttribution::create([
                'referral_code' => $referralCode,
                'referrer_affiliate_id' => $referralCodeRecord->affiliate_id,
                'new_user_id' => $dummyUser->id, // Use dummy user ID
                'ip_hash' => hash('sha256', $request->ip()),
                'source' => 'affiliate_signup',
                'device_fingerprint' => 'affiliate_' . $newAffiliate->id, // Track the new affiliate
                'verified' => false, // Will be verified when email is verified
                'attributed_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the signup process
            Log::error('Failed to create referral attribution: ' . $e->getMessage(), [
                'referral_code' => $referralCode,
                'affiliate_id' => $newAffiliate->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
