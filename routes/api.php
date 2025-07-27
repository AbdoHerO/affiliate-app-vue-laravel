<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public authentication routes
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
});

// Protected authentication routes
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::get('user', [AuthController::class, 'user']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

    // Legacy user route (for compatibility)
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

// Role-based protected routes
Route::middleware(['auth:sanctum'])->group(function () {

    // Admin only routes
    Route::middleware(['role:admin'])->prefix('admin')->group(function () {
        Route::get('dashboard', function () {
            return response()->json(['message' => 'Admin Dashboard']);
        });

        Route::get('users', function () {
            return response()->json(['message' => 'Manage Users - Admin Only']);
        });

        Route::get('affiliates', function () {
            return response()->json(['message' => 'Manage Affiliates - Admin Only']);
        });

        Route::get('reports', function () {
            return response()->json(['message' => 'View Reports - Admin Only']);
        });
    });

    // Affiliate only routes
    Route::middleware(['role:affiliate'])->prefix('affiliate')->group(function () {
        Route::get('dashboard', function () {
            return response()->json(['message' => 'Affiliate Dashboard']);
        });

        Route::get('orders', function () {
            return response()->json(['message' => 'My Orders - Affiliate Only']);
        });

        Route::post('orders', function () {
            return response()->json(['message' => 'Create Order - Affiliate Only']);
        });

        Route::get('commissions', function () {
            return response()->json(['message' => 'My Commissions - Affiliate Only']);
        });
    });

    // Routes accessible by both admin and affiliate
    Route::get('profile', function (Request $request) {
        return response()->json([
            'message' => 'User Profile',
            'user' => $request->user()->only(['id', 'name', 'email']),
            'roles' => $request->user()->getRoleNames(),
        ]);
    });

    // Permission-based routes (more granular than role-based)
    Route::middleware(['permission:manage users'])->get('admin/users/manage', function () {
        return response()->json(['message' => 'Manage Users - Permission Required']);
    });

    Route::middleware(['permission:view reports'])->get('admin/reports/analytics', function () {
        return response()->json(['message' => 'View Analytics Reports - Permission Required']);
    });

    Route::middleware(['permission:create orders'])->post('orders/create', function () {
        return response()->json(['message' => 'Create Order - Permission Required']);
    });

    Route::middleware(['permission:view own orders'])->get('orders/my-orders', function () {
        return response()->json(['message' => 'View My Orders - Permission Required']);
    });

    // Dashboard controller routes (demonstrating role/permission checking in controllers)
    Route::get('dashboard', [DashboardController::class, 'dashboard']);
    Route::get('dashboard/admin', [DashboardController::class, 'adminDashboard']);
    Route::get('dashboard/affiliate', [DashboardController::class, 'affiliateDashboard']);
    Route::get('users/manage', [DashboardController::class, 'manageUsers']);
    Route::get('orders/create-form', [DashboardController::class, 'createOrder']);
});
