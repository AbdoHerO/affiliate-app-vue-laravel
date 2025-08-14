<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AffiliateVerifyMail;
use App\Models\Affilie;
use App\Models\AffiliateEmailVerification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AffiliateApplicationsController extends Controller
{
    /**
     * Get affiliate applications for approval queue.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Affilie::query();

        // Apply filters
        if ($request->filled('q')) {
            $search = $request->get('q');
            $query->where(function ($q) use ($search) {
                $q->where('nom_complet', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->get('approval_status'));
        }

        if ($request->filled('email_verified')) {
            if ($request->get('email_verified') === 'true') {
                $query->emailVerified();
            } else {
                $query->emailNotVerified();
            }
        }

        if ($request->filled('created_from')) {
            $query->where('created_at', '>=', $request->get('created_from'));
        }

        if ($request->filled('created_to')) {
            $query->where('created_at', '<=', $request->get('created_to'));
        }

        // Sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortDirection = $request->get('dir', 'desc');

        $allowedSorts = ['created_at', 'nom_complet', 'email', 'approval_status', 'email_verified_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Pagination
        $perPage = min($request->get('perPage', 15), 100);
        $applications = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $applications->items(),
            'pagination' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
                'from' => $applications->firstItem(),
                'to' => $applications->lastItem(),
            ]
        ]);
    }

    /**
     * Get approval queue statistics.
     */
    public function getStats(): JsonResponse
    {
        $stats = [
            'total_applications' => Affilie::count(),
            'pending_approval' => Affilie::pendingApproval()->count(),
            'email_verified' => Affilie::emailVerified()->count(),
            'email_not_verified' => Affilie::emailNotVerified()->count(),
            'approved_applications' => Affilie::approved()->count(),
            'refused_applications' => Affilie::refused()->count(),
            'recent_signups' => Affilie::where('created_at', '>=', now()->subDays(7))->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Approve an affiliate application and create user account.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        try {
            $affilie = Affilie::findOrFail($id);

            if (!$affilie->isEmailVerified()) {
                return response()->json([
                    'success' => false,
                    'message' => 'L\'email doit être vérifié avant l\'approbation.'
                ], 422);
            }

            if ($affilie->isApproved()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette demande a déjà été approuvée.'
                ], 422);
            }

            DB::transaction(function () use ($affilie) {
                // Create user account
                $user = User::create([
                    'nom_complet' => $affilie->nom_complet,
                    'email' => $affilie->email,
                    'telephone' => $affilie->telephone,
                    'adresse' => $affilie->adresse,
                    'mot_de_passe_hash' => $affilie->mot_de_passe_hash, // Use the same password
                    'statut' => 'actif',
                    'email_verifie' => true,
                    'kyc_statut' => 'non_requis',
                ]);

                // Assign affiliate role
                $affiliateRole = Role::where('name', 'affiliate')->first();
                if ($affiliateRole) {
                    $user->assignRole($affiliateRole);
                }

                // Update affiliate status
                $affilie->update([
                    'approval_status' => 'approved'
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Demande d\'affiliation approuvée avec succès.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'approbation.'
            ], 500);
        }
    }

    /**
     * Refuse an affiliate application.
     */
    public function refuse(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'refusal_reason' => 'required|string|max:1000'
        ]);

        try {
            $affilie = Affilie::findOrFail($id);

            if ($affilie->isRefused()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette demande a déjà été refusée.'
                ], 422);
            }

            $affilie->update([
                'approval_status' => 'refused',
                'refusal_reason' => $request->refusal_reason
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Demande d\'affiliation refusée.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors du refus.'
            ], 500);
        }
    }

    /**
     * Resend verification email for an affiliate application.
     */
    public function resendVerification(Request $request, string $id): JsonResponse
    {
        try {
            $affilie = Affilie::findOrFail($id);

            if ($affilie->isEmailVerified()) {
                return response()->json([
                    'success' => false,
                    'message' => 'L\'email est déjà vérifié.'
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
                'message' => 'Email de vérification renvoyé avec succès.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi de l\'email.'
            ], 500);
        }
    }
}
