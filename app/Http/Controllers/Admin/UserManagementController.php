<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index(Request $request)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $query = User::with('roles');

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom_complet', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->has('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // Filter by status
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        $users = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'users' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'nom_complet' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|exists:roles,name',
            'statut' => 'required|string|in:actif,inactif,suspendu',
            'kyc_statut' => 'required|string|in:non_requis,en_attente,approuve,refuse',
        ]);

        $user = User::create([
            'nom_complet' => $request->nom_complet,
            'email' => $request->email,
            'mot_de_passe_hash' => Hash::make($request->password),
            'statut' => $request->statut,
            'kyc_statut' => $request->kyc_statut,
        ]);

        // Assign role
        $user->assignRole($request->role);

        return response()->json([
            'message' => 'User created successfully',
            'user' => [
                'id' => $user->id,
                'nom_complet' => $user->nom_complet,
                'email' => $user->email,
                'statut' => $user->statut,
                'kyc_statut' => $user->kyc_statut,
                'roles' => $user->getRoleNames(),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified user
     */
    public function show(Request $request, $id)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $user = User::with(['roles', 'permissions'])->findOrFail($id);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'nom_complet' => $user->nom_complet,
                'email' => $user->email,
                'statut' => $user->statut,
                'kyc_statut' => $user->kyc_statut,
                'email_verifie' => $user->email_verifie,
                'created_at' => $user->created_at,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, $id)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $user = User::findOrFail($id);

        $request->validate([
            'nom_complet' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|nullable|string|min:8',
            'role' => 'sometimes|required|string|exists:roles,name',
            'statut' => 'sometimes|required|string|in:actif,inactif,suspendu',
            'kyc_statut' => 'sometimes|required|string|in:non_requis,en_attente,approuve,refuse',
        ]);

        // Update user fields
        if ($request->has('nom_complet')) {
            $user->nom_complet = $request->nom_complet;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->has('password') && $request->password) {
            $user->mot_de_passe_hash = Hash::make($request->password);
        }
        if ($request->has('statut')) {
            $user->statut = $request->statut;
        }
        if ($request->has('kyc_statut')) {
            $user->kyc_statut = $request->kyc_statut;
        }

        $user->save();

        // Update role if provided
        if ($request->has('role')) {
            $user->syncRoles([$request->role]);
        }

        return response()->json([
            'message' => 'User updated successfully',
            'user' => [
                'id' => $user->id,
                'nom_complet' => $user->nom_complet,
                'email' => $user->email,
                'statut' => $user->statut,
                'kyc_statut' => $user->kyc_statut,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }

    /**
     * Remove the specified user
     */
    public function destroy(Request $request, $id)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $user = User::findOrFail($id);

        // Prevent admin from deleting themselves
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete your own account'
            ], Response::HTTP_FORBIDDEN);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Get available roles for user assignment
     */
    public function getRoles(Request $request)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $roles = Role::all(['id', 'name']);

        return response()->json([
            'roles' => $roles
        ]);
    }

    /**
     * Toggle user status (activate/deactivate)
     */
    public function toggleStatus(Request $request, $id)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $user = User::findOrFail($id);

        // Prevent admin from deactivating themselves
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot change your own status'
            ], Response::HTTP_FORBIDDEN);
        }

        $user->statut = $user->statut === 'actif' ? 'inactif' : 'actif';
        $user->save();

        return response()->json([
            'message' => 'User status updated successfully',
            'user' => [
                'id' => $user->id,
                'nom_complet' => $user->nom_complet,
                'statut' => $user->statut,
            ],
        ]);
    }
}
