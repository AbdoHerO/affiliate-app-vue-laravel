<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionController extends Controller
{
    /**
     * Get all roles with their permissions
     */
    public function getRoles(Request $request)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $roles = Role::with('permissions')->get();

        return response()->json([
            'roles' => $roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name'),
                    'users_count' => $role->users()->count(),
                ];
            })
        ]);
    }

    /**
     * Get all permissions
     */
    public function getPermissions(Request $request)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $permissions = Permission::all(['id', 'name']);

        return response()->json([
            'permissions' => $permissions
        ]);
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
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role = Role::create(['name' => $request->name]);

        if ($request->has('permissions')) {
            $role->givePermissionTo($request->permissions);
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
            'permissions.*' => 'exists:permissions,name',
        ]);

        if ($request->has('name')) {
            $role->name = $request->name;
            $role->save();
        }

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
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

        // Check if role has users
        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete role that has assigned users'
            ], Response::HTTP_FORBIDDEN);
        }

        $role->delete();

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

        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted successfully'
        ]);
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
