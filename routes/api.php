<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\DashboardStatsController;
use App\Http\Controllers\Admin\KycDocumentController;
use App\Http\Controllers\Admin\BoutiqueController;
use App\Http\Controllers\Admin\CategorieController;
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
        // Dashboard & Statistics
        Route::get('dashboard/stats', [DashboardStatsController::class, 'getDashboardStats']);
        Route::get('dashboard/charts', [DashboardStatsController::class, 'getChartData']);

        // User Management
        Route::get('users', [UserManagementController::class, 'index']);
        Route::post('users', [UserManagementController::class, 'store']);
        Route::get('users/{id}', [UserManagementController::class, 'show']);
        Route::put('users/{id}', [UserManagementController::class, 'update']);
        Route::delete('users/{id}', [UserManagementController::class, 'destroy']);
        Route::post('users/{id}/toggle-status', [UserManagementController::class, 'toggleStatus']);
        Route::get('users/roles/list', [UserManagementController::class, 'getRoles']);

        // Role & Permission Management
        Route::get('roles', [RolePermissionController::class, 'getRoles']);
        Route::post('roles', [RolePermissionController::class, 'createRole']);
        Route::put('roles/{id}', [RolePermissionController::class, 'updateRole']);
        Route::delete('roles/{id}', [RolePermissionController::class, 'deleteRole']);
        Route::get('permissions', [RolePermissionController::class, 'getPermissions']);
        Route::post('permissions', [RolePermissionController::class, 'createPermission']);
        Route::delete('permissions/{id}', [RolePermissionController::class, 'deletePermission']);
        Route::post('roles/{id}/permissions', [RolePermissionController::class, 'assignPermissions']);
        Route::get('roles/stats', [RolePermissionController::class, 'getRoleStats']);

        // KYC Documents Management
        Route::get('kyc-documents', [KycDocumentController::class, 'index']);
        Route::post('kyc-documents', [KycDocumentController::class, 'store']);
        Route::get('kyc-documents/{id}', [KycDocumentController::class, 'show']);
        Route::put('kyc-documents/{id}', [KycDocumentController::class, 'update']);
        Route::delete('kyc-documents/{id}', [KycDocumentController::class, 'destroy']);
        Route::get('kyc-documents/{id}/download', [KycDocumentController::class, 'download']);
        Route::get('kyc-documents/{id}/view', [KycDocumentController::class, 'view']);


        Route::get('users/{userId}/kyc-documents', [KycDocumentController::class, 'getUserDocuments']);

        // Boutique Management
        Route::get('boutiques', [BoutiqueController::class, 'index']);
        Route::post('boutiques', [BoutiqueController::class, 'store']);
        Route::get('boutiques/{id}', [BoutiqueController::class, 'show']);
        Route::put('boutiques/{id}', [BoutiqueController::class, 'update']);
        Route::delete('boutiques/{id}', [BoutiqueController::class, 'destroy']);

        // Category Management
        Route::get('categories', [CategorieController::class, 'index']);
        Route::post('categories', [CategorieController::class, 'store']);
        Route::get('categories/{categorie}', [CategorieController::class, 'show']);
        Route::put('categories/{categorie}', [CategorieController::class, 'update']);
        Route::delete('categories/{categorie}', [CategorieController::class, 'destroy']);
        Route::post('categories/{categorie}/toggle-status', [CategorieController::class, 'toggleStatus']);
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

    // File upload routes
    Route::post('upload/profile-image', [FileUploadController::class, 'uploadProfileImage']);
    Route::delete('upload/file', [FileUploadController::class, 'deleteFile']);


    // Profile management routes
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::put('profile/password', [ProfileController::class, 'updatePassword']);

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

// Public file access route for KYC documents (no auth required for file viewing)
Route::get('admin/kyc-documents/{id}/file', function($id) {
    $document = \App\Models\KycDocument::findOrFail($id);
    $filePath = storage_path('app/public/' . $document->url_fichier);

    if (!file_exists($filePath)) {
        abort(404, 'File not found');
    }

    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
    $mimeType = match(strtolower($extension)) {
        'pdf' => 'application/pdf',
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        default => 'application/octet-stream'
    };

    return response()->file($filePath, [
        'Content-Type' => $mimeType,
        'Content-Disposition' => 'inline'
    ]);
});
