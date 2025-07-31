<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionController extends Controller
{
    /**
     * Get all roles with their permissions
     */
    public function getRoles(Request $request)
    {
        try {
            // Check admin permission
            if (!$request->user()->hasRole('admin')) {
                return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            $roles = Role::with('permissions')->get();

            return response()->json([
                'data' => $roles->map(function ($role) {
                    // Get users count safely
                    $usersCount = 0;
                    try {
                        $usersCount = \App\Models\User::role($role->name)->count();
                    } catch (\Exception $e) {
                        // Fallback: count users with this role directly
                        $usersCount = \App\Models\User::whereHas('roles', function($query) use ($role) {
                            $query->where('name', $role->name);
                        })->count();
                    }

                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'permissions' => $role->permissions->pluck('name')->toArray(),
                        'users_count' => $usersCount,
                        'created_at' => $role->created_at?->format('Y-m-d H:i:s'),
                        'updated_at' => $role->updated_at?->format('Y-m-d H:i:s'),
                    ];
                })->toArray(),
                'total' => $roles->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch roles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all permissions
     */
    public function getPermissions(Request $request)
    {
        try {
            // Check admin permission
            if (!$request->user()->hasRole('admin')) {
                return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            $permissions = Permission::orderBy('name')->get(['id', 'name', 'created_at']);

            return response()->json([
                'data' => $permissions->map(function ($permission) {
                    // Get roles count safely
                    $rolesCount = 0;
                    try {
                        $rolesCount = $permission->roles()->count();
                    } catch (\Exception $e) {
                        // Fallback: count roles with this permission directly
                        $rolesCount = \Spatie\Permission\Models\Role::whereHas('permissions', function($query) use ($permission) {
                            $query->where('name', $permission->name);
                        })->count();
                    }

                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'roles_count' => $rolesCount,
                        'created_at' => $permission->created_at?->format('Y-m-d H:i:s'),
                    ];
                })->toArray(),
                'total' => $permissions->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new role
     */
    public function createRole(Request $request)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'string|max:255',
        ]);

        // Validate that permissions exist in database
        if ($request->has('permissions') && !empty($request->permissions)) {
            $existingPermissions = Permission::whereIn('name', $request->permissions)->pluck('name')->toArray();
            $invalidPermissions = array_diff($request->permissions, $existingPermissions);

            if (!empty($invalidPermissions)) {
                return response()->json([
                    'message' => 'Some permissions do not exist',
                    'errors' => [
                        'permissions' => ['The following permissions do not exist: ' . implode(', ', $invalidPermissions)]
                    ],
                    'available_permissions' => Permission::pluck('name')->toArray()
                ], 422);
            }
        }

        $role = Role::create(['name' => $request->name]);

        if ($request->has('permissions') && !empty($request->permissions)) {
            // Get permission objects instead of names to avoid guard issues
            $permissionObjects = Permission::whereIn('name', $request->permissions)->get();
            $role->permissions()->sync($permissionObjects->pluck('id'));
        }

        return response()->json([
            'message' => 'Role created successfully',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Update a role
     */
    public function updateRole(Request $request, $id)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $role = Role::findOrFail($id);

        // Prevent modification of core roles
        if (in_array($role->name, ['admin', 'affiliate'])) {
            return response()->json([
                'message' => 'Cannot modify core system roles'
            ], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'array',
            'permissions.*' => 'string|max:255',
        ]);

        // Validate that permissions exist in database
        if ($request->has('permissions') && !empty($request->permissions)) {
            $existingPermissions = Permission::whereIn('name', $request->permissions)->pluck('name')->toArray();
            $invalidPermissions = array_diff($request->permissions, $existingPermissions);

            if (!empty($invalidPermissions)) {
                return response()->json([
                    'message' => 'Some permissions do not exist',
                    'errors' => [
                        'permissions' => ['The following permissions do not exist: ' . implode(', ', $invalidPermissions)]
                    ],
                    'available_permissions' => Permission::pluck('name')->toArray()
                ], 422);
            }
        }

        if ($request->has('name')) {
            $role->name = $request->name;
            $role->save();
        }

        if ($request->has('permissions')) {
            // Get permission objects instead of names to avoid guard issues
            $permissionObjects = Permission::whereIn('name', $request->permissions)->get();
            $role->permissions()->sync($permissionObjects->pluck('id'));
        }

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
        ]);
    }

    /**
     * Delete a role
     */
    public function deleteRole(Request $request, $id)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $role = Role::findOrFail($id);

        // Prevent deletion of core roles
        if (in_array($role->name, ['admin', 'affiliate'])) {
            return response()->json([
                'message' => 'Cannot delete core system roles'
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if role has users using direct database query
        $usersCount = 0;
        try {
            $usersCount = DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->count();
        } catch (\Exception $e) {
            Log::warning('Failed to count users for role: ' . $e->getMessage());
        }

        if ($usersCount > 0) {
            return response()->json([
                'message' => 'Cannot delete role that has assigned users'
            ], Response::HTTP_FORBIDDEN);
        }

        // Delete role using direct database query to avoid relationship issues
        try {
            // First remove role permissions
            DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
            // Then delete the role
            DB::table('roles')->where('id', $role->id)->delete();
        } catch (\Exception $e) {
            // Fallback to model deletion
            $role->delete();
        }

        return response()->json([
            'message' => 'Role deleted successfully'
        ]);
    }

    /**
     * Create a new permission
     */
    public function createPermission(Request $request)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
        ]);

        $permission = Permission::create(['name' => $request->name]);

        return response()->json([
            'message' => 'Permission created successfully',
            'permission' => [
                'id' => $permission->id,
                'name' => $permission->name,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Delete a permission
     */
    public function deletePermission(Request $request, $id)
    {
        try {
            // Check admin permission
            if (!$request->user()->hasRole('admin')) {
                return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            $permission = Permission::findOrFail($id);

            // Prevent deletion of core permissions
            $corePermissions = [
                'manage users', 'manage affiliates', 'manage products', 'manage orders',
                'manage payments', 'view reports', 'manage settings', 'create orders',
                'view own orders', 'view own commissions', 'view marketing materials', 'update profile'
            ];

            if (in_array($permission->name, $corePermissions)) {
                return response()->json([
                    'message' => 'Cannot delete core system permissions'
                ], Response::HTTP_FORBIDDEN);
            }

            // Check if permission is assigned to any roles using direct database query
            $rolesCount = 0;
            try {
                $rolesCount = DB::table('role_has_permissions')
                    ->where('permission_id', $permission->id)
                    ->count();
            } catch (\Exception $e) {
                // If counting fails, allow deletion but log the error
                Log::warning('Failed to count roles for permission: ' . $e->getMessage());
            }

            if ($rolesCount > 0) {
                return response()->json([
                    'message' => 'Cannot delete permission that is assigned to roles. Please remove it from all roles first.'
                ], Response::HTTP_FORBIDDEN);
            }

            // Delete permission using direct database query to avoid relationship issues
            try {
                DB::table('permissions')->where('id', $permission->id)->delete();
            } catch (\Exception $e) {
                // Fallback to model deletion
                $permission->delete();
            }

            return response()->json([
                'message' => 'Permission deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete permission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign permissions to a role
     */
    public function assignPermissions(Request $request, $roleId)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $role = Role::findOrFail($roleId);

        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->syncPermissions($request->permissions);

        return response()->json([
            'message' => 'Permissions assigned successfully',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
        ]);
    }

    /**
     * Get role statistics
     */
    public function getRoleStats(Request $request)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $stats = [
            'total_roles' => Role::count(),
            'total_permissions' => Permission::count(),
            'roles_with_users' => Role::has('users')->count(),
            'role_distribution' => Role::withCount('users')->get()->map(function ($role) {
                return [
                    'name' => $role->name,
                    'users_count' => $role->users_count,
                ];
            }),
        ];

        return response()->json(['stats' => $stats]);
    }
}
