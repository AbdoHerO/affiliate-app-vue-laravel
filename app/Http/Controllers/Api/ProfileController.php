<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
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
}
