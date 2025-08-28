<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KycDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Get user profile
     */
    public function show(Request $request)
    {
        $user = $request->user()->load('roles');

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'nom_complet' => $user->nom_complet,
                'email' => $user->email,
                'telephone' => $user->telephone,
                'adresse' => $user->adresse,
                'cin' => $user->cin,
                'rib' => $user->rib,
                'bank_type' => $user->bank_type,
                'photo_profil' => $user->photo_profil,
                'statut' => $user->statut,
                'email_verifie' => $user->email_verifie,
                'kyc_statut' => $user->kyc_statut,
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]
        ]);
    }

    /**
     * Update user profile
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'nom_complet' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'telephone' => 'sometimes|nullable|string|max:20',
            'adresse' => 'sometimes|nullable|string|max:500',
            'cin' => 'sometimes|nullable|string|max:20',
            'photo_profil' => 'sometimes|nullable|string|max:500',
        ]);

        try {
            // Update user fields
            if ($request->has('nom_complet')) {
                $user->nom_complet = $request->nom_complet;
            }
            
            if ($request->has('email')) {
                $user->email = $request->email;
            }
            
            if ($request->has('telephone')) {
                $user->telephone = $request->telephone;
            }
            
            if ($request->has('adresse')) {
                $user->adresse = $request->adresse;
            }

            if ($request->has('cin')) {
                $user->cin = $request->cin;
            }

            if ($request->has('photo_profil')) {
                $user->photo_profil = $request->photo_profil;
            }

            $user->save();

            return response()->json([
                'success' => true,
                'message' => __('messages.profile_updated_successfully'),
                'user' => [
                    'id' => $user->id,
                    'nom_complet' => $user->nom_complet,
                    'email' => $user->email,
                    'telephone' => $user->telephone,
                    'adresse' => $user->adresse,
                    'cin' => $user->cin,
                    'rib' => $user->rib,
                    'bank_type' => $user->bank_type,
                    'photo_profil' => $user->photo_profil,
                    'statut' => $user->statut,
                    'email_verifie' => $user->email_verifie,
                    'kyc_statut' => $user->kyc_statut,
                    'roles' => $user->roles->pluck('name'),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.profile_update_failed'),
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update user password
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        // Check current password
        if (!Hash::check($request->current_password, $user->mot_de_passe_hash)) {
            return response()->json([
                'success' => false,
                'message' => __('messages.current_password_incorrect'),
                'errors' => [
                    'current_password' => [__('messages.current_password_incorrect')]
                ]
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $user->mot_de_passe_hash = Hash::make($request->password);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => __('messages.password_updated_successfully')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.password_update_failed'),
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get user's KYC documents
     */
    public function getKycDocuments(Request $request)
    {
        $user = $request->user();

        $documents = KycDocument::where('utilisateur_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'documents' => $documents
        ]);
    }

    /**
     * Upload a new KYC document for the current user
     */
    public function uploadKycDocument(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'type_doc' => 'required|string|in:cni,passport,rib,contrat',
            'fichier' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
        ]);

        try {
            // Store the file
            $file = $request->file('fichier');
            $filename = time() . '_' . $user->id . '_' . $request->type_doc . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('kyc-documents', $filename, 'public');

            // Create the document record
            $document = KycDocument::create([
                'utilisateur_id' => $user->id,
                'type_doc' => $request->type_doc,
                'url_fichier' => $path,
                'statut' => 'en_attente',
            ]);

            return response()->json([
                'success' => true,
                'message' => __('messages.document_uploaded_successfully'),
                'document' => $document
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.document_upload_failed'),
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Download a KYC document
     */
    public function downloadKycDocument(Request $request, string $id)
    {
        $user = $request->user();

        $document = KycDocument::where('utilisateur_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $filePath = storage_path('app/public/' . $document->url_fichier);

        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => __('messages.file_not_found')
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->download($filePath);
    }

    /**
     * Delete a KYC document (only if not validated)
     */
    public function deleteKycDocument(Request $request, string $id)
    {
        $user = $request->user();

        $document = KycDocument::where('utilisateur_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        // Prevent deletion of validated documents
        if ($document->statut === 'valide') {
            return response()->json([
                'success' => false,
                'message' => __('messages.cannot_delete_validated_document')
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            // Delete the file from storage
            if (Storage::disk('public')->exists($document->url_fichier)) {
                Storage::disk('public')->delete($document->url_fichier);
            }

            // Delete the database record
            $document->delete();

            return response()->json([
                'success' => true,
                'message' => __('messages.document_deleted_successfully')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.document_deletion_failed'),
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
