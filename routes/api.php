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
use App\Http\Controllers\Admin\ProduitController;
use App\Http\Controllers\Admin\ProduitImageController;
use App\Http\Controllers\Admin\ProduitVideoController;
use App\Http\Controllers\Admin\ProduitVarianteController;
use App\Http\Controllers\Admin\ProduitPropositionController;
use App\Http\Controllers\Admin\ProduitRuptureController;
use App\Http\Controllers\Admin\PreordersController;
use App\Http\Controllers\Admin\OzonExpressController;
use App\Http\Controllers\Admin\CitiesController;
use App\Http\Controllers\Admin\ShippingOrdersController;
use App\Http\Controllers\Admin\AffiliatesController;
use App\Http\Controllers\Admin\AffiliateApplicationsController;
use App\Http\Controllers\Admin\UsersApprovalController;
use App\Http\Controllers\Admin\VariantAttributController;
use App\Http\Controllers\Admin\VariantValeurController;
use App\Http\Controllers\Public\ProduitController as PublicProduitController;
use App\Http\Controllers\Public\AffiliateSignupController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


// Test route
Route::get('/test', function () {
    return response()->json(['message' => 'API working', 'timestamp' => now()]);
});

// Public routes (no authentication required)
Route::prefix('public')->group(function () {
    Route::get('produits/{slugOrId}', [PublicProduitController::class, 'show']);

    // Affiliate signup routes
    Route::prefix('affiliates')->middleware('throttle:5,1')->group(function () {
        Route::post('signup', [AffiliateSignupController::class, 'signup']);
        Route::post('resend-verification', [AffiliateSignupController::class, 'resendVerification']);
    });

    // Email verification route (no throttle for better UX)
    Route::get('affiliates/verify', [AffiliateSignupController::class, 'verify']);
});

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

        // Users Approval Queue (MUST be before users/{id} routes)
        Route::get('users/approval-queue', [UsersApprovalController::class, 'index']);
        Route::get('users/approval-queue/stats', [UsersApprovalController::class, 'getStats']);
        Route::get('users/roles/list', [UserManagementController::class, 'getRoles']);

        // User Management with ID parameters (MUST be after specific routes)
        Route::get('users/{id}', [UserManagementController::class, 'show']);
        Route::put('users/{id}', [UserManagementController::class, 'update']);
        Route::delete('users/{id}', [UserManagementController::class, 'destroy']);
        Route::post('users/{id}/restore', [UserManagementController::class, 'restore']);
        Route::delete('users/{id}/force', [UserManagementController::class, 'forceDelete']);
        Route::post('users/{id}/toggle-status', [UserManagementController::class, 'toggleStatus']);
        Route::post('users/{id}/approve', [UsersApprovalController::class, 'approve']);
        Route::post('users/{id}/refuse', [UsersApprovalController::class, 'refuse']);
        Route::post('users/{id}/resend-verification', [UsersApprovalController::class, 'resendVerification']);

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
        Route::post('kyc-documents/{id}/restore', [KycDocumentController::class, 'restore']);
        Route::delete('kyc-documents/{id}/force', [KycDocumentController::class, 'forceDelete']);
        Route::get('kyc-documents/{id}/download', [KycDocumentController::class, 'download']);
        Route::get('kyc-documents/{id}/view', [KycDocumentController::class, 'view']);


        Route::get('users/{userId}/kyc-documents', [KycDocumentController::class, 'getUserDocuments']);

        // Boutique Management
        Route::get('boutiques', [BoutiqueController::class, 'index']);
        Route::post('boutiques', [BoutiqueController::class, 'store']);
        Route::get('boutiques/{id}', [BoutiqueController::class, 'show']);
        Route::put('boutiques/{id}', [BoutiqueController::class, 'update']);
        Route::delete('boutiques/{id}', [BoutiqueController::class, 'destroy']);
        Route::post('boutiques/{id}/restore', [BoutiqueController::class, 'restore']);
        Route::delete('boutiques/{id}/force', [BoutiqueController::class, 'forceDelete']);

        // Category Management
        Route::get('categories', [CategorieController::class, 'index']);
        Route::post('categories', [CategorieController::class, 'store']);
        Route::get('categories/{categorie}', [CategorieController::class, 'show']);
        Route::put('categories/{categorie}', [CategorieController::class, 'update']);
        Route::delete('categories/{categorie}', [CategorieController::class, 'destroy']);
        Route::post('categories/{id}/restore', [CategorieController::class, 'restore']);
        Route::delete('categories/{id}/force', [CategorieController::class, 'forceDelete']);
        Route::post('categories/{categorie}/toggle-status', [CategorieController::class, 'toggleStatus']);

        // Product Management
        Route::get('produits', [ProduitController::class, 'index']);
        Route::post('produits', [ProduitController::class, 'store']);
        Route::get('produits/{produit}', [ProduitController::class, 'show']);
        Route::put('produits/{produit}', [ProduitController::class, 'update']);
        Route::delete('produits/{produit}', [ProduitController::class, 'destroy']);
        Route::post('produits/{id}/restore', [ProduitController::class, 'restore']);
        Route::delete('produits/{id}/force', [ProduitController::class, 'forceDelete']);
        Route::post('produits/{produit}/share', [ProduitController::class, 'share']);

        // Product Images Management
        Route::get('produits/{produit}/images', [ProduitImageController::class, 'index']);
        Route::post('produits/{produit}/images', [ProduitImageController::class, 'store']);
        Route::post('produits/{produit}/images/upload', [ProduitImageController::class, 'upload']);
        Route::put('produits/{produit}/images/sort', [ProduitImageController::class, 'bulkSort']);
        Route::delete('produits/{produit}/images/{image}', [ProduitImageController::class, 'destroy']);

        // Product Videos Management
        Route::get('produits/{produit}/videos', [ProduitVideoController::class, 'index']);
        Route::post('produits/{produit}/videos', [ProduitVideoController::class, 'store']);
        Route::post('produits/{produit}/videos/upload', [ProduitVideoController::class, 'upload']);
        Route::put('produits/{produit}/videos/{video}', [ProduitVideoController::class, 'update']);
        Route::put('produits/{produit}/videos/sort', [ProduitVideoController::class, 'bulkSort']);
        Route::delete('produits/{produit}/videos/{video}', [ProduitVideoController::class, 'destroy']);

        // Product Variants Management
        Route::get('produits/{produit}/variantes', [ProduitVarianteController::class, 'index']);
        Route::post('produits/{produit}/variantes', [ProduitVarianteController::class, 'store']);
        Route::get('produits/{produit}/variantes/{variante}', [ProduitVarianteController::class, 'show']);
        Route::put('produits/{produit}/variantes/{variante}', [ProduitVarianteController::class, 'update']);
        Route::post('produits/{produit}/variantes/{variante}/image', [ProduitVarianteController::class, 'uploadImage']);
        Route::delete('produits/{produit}/variantes/{variante}', [ProduitVarianteController::class, 'destroy']);

        // Product Propositions Management
        Route::get('produits/{produit}/propositions', [ProduitPropositionController::class, 'index']);
        Route::post('produits/{produit}/propositions', [ProduitPropositionController::class, 'store']);
        Route::put('produits/{produit}/propositions/{proposition}', [ProduitPropositionController::class, 'update']);
        Route::post('produits/{produit}/propositions/{proposition}/image', [ProduitPropositionController::class, 'uploadImage']);
        Route::delete('produits/{produit}/propositions/{proposition}', [ProduitPropositionController::class, 'destroy']);

        // Product Ruptures (Stock Alerts) Management
        Route::get('produits/{produit}/ruptures', [ProduitRuptureController::class, 'index']);
        Route::post('produits/{produit}/ruptures', [ProduitRuptureController::class, 'store']);
        Route::get('produits/{produit}/ruptures/{rupture}', [ProduitRuptureController::class, 'show']);
        Route::put('produits/{produit}/ruptures/{rupture}', [ProduitRuptureController::class, 'update']);
        Route::delete('produits/{produit}/ruptures/{rupture}', [ProduitRuptureController::class, 'destroy']);
        Route::post('produits/{produit}/ruptures/{rupture}/resolve', [ProduitRuptureController::class, 'resolve']);
        
        // Global ruptures (all products)
        Route::get('ruptures/active', [ProduitRuptureController::class, 'getAllActive']);

        // Variant Catalog Management
        Route::get('variant-attributs', [VariantAttributController::class, 'index']);
        Route::post('variant-attributs', [VariantAttributController::class, 'store']);
        Route::get('variant-attributs/{variantAttribut}', [VariantAttributController::class, 'show']);
        Route::put('variant-attributs/{variantAttribut}', [VariantAttributController::class, 'update']);
        Route::delete('variant-attributs/{variantAttribut}', [VariantAttributController::class, 'destroy']);
        Route::post('variant-attributs/{id}/restore', [VariantAttributController::class, 'restore']);
        Route::delete('variant-attributs/{id}/force', [VariantAttributController::class, 'forceDelete']);
        Route::post('variant-attributs/{variantAttribut}/toggle-status', [VariantAttributController::class, 'toggleStatus']);

        // Variant Values Management (nested under attributes)
        Route::get('variant-attributs/{variantAttribut}/valeurs', [VariantValeurController::class, 'index']);
        Route::post('variant-attributs/{variantAttribut}/valeurs', [VariantValeurController::class, 'store']);
        Route::put('variant-attributs/{variantAttribut}/valeurs/{variantValeur}', [VariantValeurController::class, 'update']);
        Route::delete('variant-attributs/{variantAttribut}/valeurs/{variantValeur}', [VariantValeurController::class, 'destroy']);
        Route::post('variant-attributs/{variantAttribut}/valeurs/reorder', [VariantValeurController::class, 'reorder']);

        // Pre-Orders Management (internal orders not yet shipped)
        Route::get('preorders', [PreordersController::class, 'index']);
        Route::get('preorders/{id}', [PreordersController::class, 'show']);
        Route::put('preorders/{id}', [PreordersController::class, 'update']);
        Route::post('preorders/{id}/confirm', [PreordersController::class, 'confirm']);

        // Bulk actions for pre-orders
        Route::post('preorders/bulk/status', [PreordersController::class, 'bulkChangeStatus']);
        Route::post('preorders/bulk/send-to-shipping', [PreordersController::class, 'bulkSendToShipping']);

        // Single order actions
        Route::post('preorders/{id}/status', [PreordersController::class, 'changeStatus']);
        Route::post('preorders/{id}/no-answer', [PreordersController::class, 'incrementNoAnswer']);
        Route::post('preorders/{id}/send-to-shipping', [PreordersController::class, 'sendToShipping']);

        // OzonExpress Shipping Integration
        Route::post('shipping/ozon/parcels', [OzonExpressController::class, 'addParcel']);
        Route::post('shipping/ozon/tracking', [OzonExpressController::class, 'tracking']);
        Route::post('shipping/ozon/parcel-info', [OzonExpressController::class, 'parcelInfo']);
        Route::post('shipping/ozon/delivery-notes', [OzonExpressController::class, 'createDeliveryNote']);
        Route::post('shipping/ozon/delivery-notes/add', [OzonExpressController::class, 'addParcelsToDeliveryNote']);
        Route::post('shipping/ozon/delivery-notes/save', [OzonExpressController::class, 'saveDeliveryNote']);
        Route::get('shipping/ozon/cities', [CitiesController::class, 'index']);

        // Shipping Orders Management (orders with parcels)
        Route::get('shipping/orders', [ShippingOrdersController::class, 'index']);
        Route::get('shipping/orders/{id}', [ShippingOrdersController::class, 'show']);



        // Legacy Affiliates Management (keep for now)
        Route::get('affiliates', [AffiliatesController::class, 'index']);
        Route::get('affiliates/{id}', [AffiliatesController::class, 'show']);
        Route::put('affiliates/{id}', [AffiliatesController::class, 'update']);
        Route::post('affiliates/{id}/toggle-block', [AffiliatesController::class, 'toggleBlock']);
        Route::post('affiliates/{id}/change-tier', [AffiliatesController::class, 'changeTier']);
        Route::get('affiliates/{id}/performance', [AffiliatesController::class, 'getPerformance']);
        Route::get('affiliate-tiers', [AffiliatesController::class, 'getTiers']);

        // Affiliate Applications Management (new signup flow)
        Route::get('affiliate-applications', [AffiliateApplicationsController::class, 'index']);
        Route::get('affiliate-applications/stats', [AffiliateApplicationsController::class, 'getStats']);
        Route::post('affiliate-applications/{id}/approve', [AffiliateApplicationsController::class, 'approve']);
        Route::post('affiliate-applications/{id}/refuse', [AffiliateApplicationsController::class, 'refuse']);
        Route::post('affiliate-applications/{id}/resend-verification', [AffiliateApplicationsController::class, 'resendVerification']);
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
    Route::post('upload/category-image', [FileUploadController::class, 'uploadCategoryImage']);
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
