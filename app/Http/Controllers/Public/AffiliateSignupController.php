<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\AffiliateVerifyMail;
use App\Models\Affilie;
use App\Models\AffiliateEmailVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            'notes' => 'nullable|string|max:1000',
            'accept_terms' => 'required|accepted',
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
            return redirect('/auth/affiliate-signup?error=invalid_link');
        }

        try {
            $verification = AffiliateEmailVerification::where('token', $request->token)
                ->with('affilie')
                ->first();

            if (!$verification) {
                return redirect('/auth/affiliate-signup?error=invalid_token');
            }

            if ($verification->isExpired()) {
                return redirect('/auth/affiliate-signup?error=expired_token&email=' . urlencode($request->email));
            }

            $affilie = $verification->affilie;

            if (!$affilie || $affilie->email !== strtolower(trim($request->email))) {
                return redirect('/auth/affiliate-signup?error=invalid_email');
            }

            if ($affilie->isEmailVerified()) {
                return redirect('/auth/affiliate-verified?already_verified=1');
            }

            // Mark email as verified
            $affilie->update([
                'email_verified_at' => now(),
            ]);

            // Delete verification token
            $verification->delete();

            return redirect('/auth/affiliate-verified');

        } catch (\Exception $e) {
            return redirect('/auth/affiliate-signup?error=server_error');
        }
    }
}
