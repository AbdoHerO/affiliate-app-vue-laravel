<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DashboardController extends Controller
{
    /**
     * Admin Dashboard - Demonstrates role checking in controller
     */
    public function adminDashboard(Request $request)
    {
        // Check if user has admin role
        if (!$request->user()->hasRole('admin')) {
            return response()->json([
                'message' => 'Access denied. Admin role required.',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'message' => 'Welcome to Admin Dashboard',
            'user' => $request->user()->name,
            'role' => 'admin',
            'stats' => [
                'total_affiliates' => 150,
                'total_orders' => 1250,
                'total_revenue' => 45000,
                'pending_payments' => 12,
            ],
        ]);
    }

    /**
     * Affiliate Dashboard - Demonstrates role checking in controller
     */
    public function affiliateDashboard(Request $request)
    {
        // Check if user has affiliate role
        if (!$request->user()->hasRole('affiliate')) {
            return response()->json([
                'message' => 'Access denied. Affiliate role required.',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'message' => 'Welcome to Affiliate Dashboard',
            'user' => $request->user()->name,
            'role' => 'affiliate',
            'stats' => [
                'my_orders' => 25,
                'pending_orders' => 3,
                'total_commissions' => 1250.50,
                'this_month_earnings' => 350.75,
            ],
        ]);
    }

    /**
     * User Management - Demonstrates permission checking in controller
     */
    public function manageUsers(Request $request)
    {
        // Check if user has specific permission
        if (!$request->user()->can('manage users')) {
            return response()->json([
                'message' => 'Access denied. "Manage Users" permission required.',
                'user_permissions' => $request->user()->getAllPermissions()->pluck('name'),
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'message' => 'User Management Panel',
            'users' => [
                ['id' => 1, 'name' => 'Admin User', 'role' => 'admin'],
                ['id' => 2, 'name' => 'Test Affiliate', 'role' => 'affiliate'],
            ],
        ]);
    }

    /**
     * Create Order - Demonstrates permission checking in controller
     */
    public function createOrder(Request $request)
    {
        // Check if user has specific permission
        if (!$request->user()->can('create orders')) {
            return response()->json([
                'message' => 'Access denied. "Create Orders" permission required.',
                'user_permissions' => $request->user()->getAllPermissions()->pluck('name'),
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'message' => 'Order creation form',
            'user' => $request->user()->name,
            'available_products' => [
                ['id' => 1, 'name' => 'Product A', 'commission_rate' => '10%'],
                ['id' => 2, 'name' => 'Product B', 'commission_rate' => '15%'],
            ],
        ]);
    }

    /**
     * Universal Dashboard - Shows different content based on user role
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return $this->adminDashboard($request);
        } elseif ($user->hasRole('affiliate')) {
            return $this->affiliateDashboard($request);
        }

        return response()->json([
            'message' => 'Access denied. No valid role assigned.',
        ], Response::HTTP_FORBIDDEN);
    }
}
