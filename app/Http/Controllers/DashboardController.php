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
                'message' => __('messages.api_access_denied_admin'),
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'message' => __('messages.api_welcome_admin'),
            'user' => $request->user()->nom_complet,
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
                'message' => __('messages.api_access_denied_affiliate'),
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'message' => __('messages.api_welcome_affiliate'),
            'user' => $request->user()->nom_complet,
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
                'message' => __('messages.api_access_denied_permission', ['permission' => 'Manage Users']),
                'user_permissions' => $request->user()->getAllPermissions()->pluck('name'),
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'message' => __('messages.api_user_management_panel'),
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
                'message' => __('messages.api_access_denied_permission', ['permission' => 'Create Orders']),
                'user_permissions' => $request->user()->getAllPermissions()->pluck('name'),
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'message' => __('messages.api_order_creation_form'),
            'user' => $request->user()->nom_complet,
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
            'message' => __('messages.api_access_denied_no_role'),
        ], Response::HTTP_FORBIDDEN);
    }
}
