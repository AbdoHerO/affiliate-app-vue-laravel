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
use App\Http\Controllers\Admin\TestController;
use App\Http\Controllers\Admin\CommissionsController;
use App\Http\Controllers\Admin\ReferralController as AdminReferralController;
use App\Http\Controllers\Admin\AdminReferralController as AdminReferralStatsController;
use App\Http\Controllers\Admin\ReferralDispensationController;
use App\Http\Controllers\Admin\AffiliateReferralController as AdminAffiliateReferralController;
use App\Http\Controllers\Admin\ReferrersController;
use App\Http\Controllers\Affiliate\ReferralController as AffiliateReferralController;
use App\Http\Controllers\Affiliate\PointsController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\Public\ReferralTrackingController;
use App\Http\Controllers\Public\SettingsController as PublicSettingsController;
use App\Http\Controllers\Admin\CommissionBackfillController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TicketsController;
use App\Http\Controllers\Admin\StockController;
use App\Http\Controllers\Admin\ReservationStockController;
use App\Http\Controllers\Admin\TicketMessagesController;
use App\Http\Controllers\Public\ProduitController as PublicProduitController;
use App\Http\Controllers\Public\AffiliateSignupController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AffiliateDashboardController;
use App\Http\Controllers\Api\SalesReportsController;
use App\Http\Controllers\Api\AffiliatePerformanceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


// Test routes
Route::get('/test', function () {
    return response()->json(['message' => 'API working', 'timestamp' => now()]);
});

// Test 401 endpoint for testing authentication handling
Route::get('/test-401-endpoint', function () {
    return response()->json(['message' => 'Unauthorized'], 401);
});



// Public routes (no authentication required)
Route::prefix('public')->group(function () {
    Route::get('produits/{slugOrId}', [PublicProduitController::class, 'show']);

    // Settings routes
    Route::get('settings', [PublicSettingsController::class, 'getPublic']);
    Route::get('app-config', [PublicSettingsController::class, 'getAppConfig']);

    // Affiliate signup routes
    Route::prefix('affiliates')->middleware('throttle:5,1')->group(function () {
        Route::post('signup', [AffiliateSignupController::class, 'signup']);
        Route::post('resend-verification', [AffiliateSignupController::class, 'resendVerification']);
    });

    // Email verification route (no throttle for better UX)
    Route::get('affiliates/verify', [AffiliateSignupController::class, 'verify']);

    // Referral tracking routes
    Route::prefix('referrals')->middleware('throttle:10,1')->group(function () {
        Route::post('track-click', [ReferralTrackingController::class, 'trackClick']);
        Route::get('info', [ReferralTrackingController::class, 'getReferralInfo']);
        Route::get('validate', [ReferralTrackingController::class, 'validateCode']);
        Route::get('attribution-info', [ReferralTrackingController::class, 'getAttributionInfo']);
        Route::post('redirect', [ReferralTrackingController::class, 'handleRedirect']);
        Route::get('abuse-info', [ReferralTrackingController::class, 'getAbuseInfo']); // Admin only
    });
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
        // Dashboard & Statistics (New comprehensive dashboard)
        Route::get('dashboard/stats', [AdminDashboardController::class, 'getStats']);
        Route::get('dashboard/charts', [AdminDashboardController::class, 'getChartData']);
        Route::get('dashboard/tables', [AdminDashboardController::class, 'getTableData']);

        // Sales Reports API
        Route::prefix('reports/sales')->group(function () {
            Route::get('summary', [SalesReportsController::class, 'getSummary']);
            Route::get('series', [SalesReportsController::class, 'getSeries']);
            Route::get('status-breakdown', [SalesReportsController::class, 'getStatusBreakdown']);
            Route::get('top-products', [SalesReportsController::class, 'getTopProducts']);
            Route::get('orders', [SalesReportsController::class, 'getOrders']);
            Route::get('top-affiliates', [SalesReportsController::class, 'getTopAffiliates']);
        });

        // Affiliate Performance Reports API
        Route::prefix('reports/affiliates')->group(function () {
            Route::get('summary', [AffiliatePerformanceController::class, 'getSummary']);
            Route::get('series', [AffiliatePerformanceController::class, 'getSeries']);
            Route::get('leaderboard', [AffiliatePerformanceController::class, 'getLeaderboard']);
            Route::get('ledger', [AffiliatePerformanceController::class, 'getLedger']);
            Route::get('segments', [AffiliatePerformanceController::class, 'getSegments']);
        });

        // Legacy dashboard routes (keep for backward compatibility)
        Route::get('dashboard/legacy/stats', [DashboardStatsController::class, 'getDashboardStats']);
        Route::get('dashboard/legacy/charts', [DashboardStatsController::class, 'getChartData']);

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
        Route::post('produits/{produit}/variantes/bulk', [ProduitVarianteController::class, 'storeBulk']);
        Route::get('produits/{produit}/variantes/{variante}', [ProduitVarianteController::class, 'show']);
        Route::put('produits/{produit}/variantes/{variante}', [ProduitVarianteController::class, 'update']);
        Route::post('produits/{produit}/variantes/{variante}/image', [ProduitVarianteController::class, 'uploadImage']);
        Route::delete('produits/{produit}/variantes/{variante}', [ProduitVarianteController::class, 'destroy']);

        // Product Stock Management
        Route::post('produits/{produit}/stock/allocate', [ProduitController::class, 'allocateStock']);
        Route::get('produits/{produit}/stock/matrix', [ProduitController::class, 'getStockMatrix']);
        Route::post('produits/{produit}/stock/generate-combinations', [ProduitController::class, 'generateCombinations']);

        // Warehouse Management
        Route::get('warehouses', [ProduitController::class, 'getWarehouses']);

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
        Route::post('preorders/{id}/move-to-shipping-local', [PreordersController::class, 'moveToShippingLocal']);

        // OzonExpress Shipping Integration
        Route::post('shipping/ozon/parcels', [OzonExpressController::class, 'addParcel']);
        Route::post('shipping/ozon/tracking', [OzonExpressController::class, 'tracking']);
        Route::post('shipping/ozon/parcel-info', [OzonExpressController::class, 'parcelInfo']);
        Route::post('shipping/ozon/delivery-notes', [OzonExpressController::class, 'createDeliveryNote']);
        Route::post('shipping/ozon/delivery-notes/add', [OzonExpressController::class, 'addParcelsToDeliveryNote']);
        Route::post('shipping/ozon/delivery-notes/save', [OzonExpressController::class, 'saveDeliveryNote']);
        Route::get('shipping/ozon/cities', [CitiesController::class, 'index']);

        // OzonExpress Debug API
        Route::prefix('shipping/ozon/debug')->group(function () {
            Route::post('send-parcel', [OzonExpressController::class, 'debugSendParcel']);
            Route::post('track', [OzonExpressController::class, 'debugTrack']);
        });

        // Shipping Orders Management (orders with parcels)
        Route::get('shipping/orders', [ShippingOrdersController::class, 'index']);
        Route::get('shipping/orders/{id}', [ShippingOrdersController::class, 'show']);
        Route::post('shipping/orders/{id}/status', [ShippingOrdersController::class, 'updateShippingStatus']);

        // Tracking refresh endpoints
        Route::post('shipping/orders/refresh-tracking', [ShippingOrdersController::class, 'refreshTracking']);
        Route::post('shipping/orders/refresh-tracking-bulk', [ShippingOrdersController::class, 'refreshTrackingBulk']);

        // OzonExpress Shipping Orders APIs
        Route::post('shipping/ozon/resend', [ShippingOrdersController::class, 'resendToOzon']);
        Route::post('shipping/ozon/track', [ShippingOrdersController::class, 'trackParcel']);
        Route::post('shipping/ozon/parcel-info', [ShippingOrdersController::class, 'getParcelInfo']);
        Route::post('shipping/ozon/dn/create', [ShippingOrdersController::class, 'createDeliveryNote']);
        Route::post('shipping/ozon/dn/add-parcels', [ShippingOrdersController::class, 'addParcelsToDeliveryNote']);
        Route::post('shipping/ozon/dn/save', [ShippingOrdersController::class, 'saveDeliveryNote']);
        Route::get('shipping/ozon/dn/pdf', [ShippingOrdersController::class, 'getDeliveryNotePdf']);



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

        // Commissions Management
        Route::prefix('commissions')->group(function () {
            Route::get('/', [CommissionsController::class, 'index']);
            Route::get('/summary', [CommissionsController::class, 'summary']);
            Route::get('/export', [CommissionsController::class, 'export']);
            Route::get('/{id}', [CommissionsController::class, 'show']);
            Route::post('/{id}/approve', [CommissionsController::class, 'approve']);
            Route::post('/{id}/reject', [CommissionsController::class, 'reject']);
            Route::post('/{id}/adjust', [CommissionsController::class, 'adjust']);
            Route::post('/{id}/mark-paid', [CommissionsController::class, 'markAsPaid']);
            Route::post('/bulk/approve', [CommissionsController::class, 'bulkApprove']);
            Route::post('/bulk/reject', [CommissionsController::class, 'bulkReject']);
            Route::post('/recalc/{commandeId}', [CommissionsController::class, 'recalculate']);
        });

        // Withdrawals Management
        Route::prefix('withdrawals')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\WithdrawalsController::class, 'index']);
            Route::get('/export', [\App\Http\Controllers\Admin\WithdrawalsController::class, 'export']);
            Route::get('/{id}', [\App\Http\Controllers\Admin\WithdrawalsController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Admin\WithdrawalsController::class, 'store']);
            Route::post('/{id}/attach-commissions', [\App\Http\Controllers\Admin\WithdrawalsController::class, 'attachCommissions']);
            Route::post('/{id}/detach-commissions', [\App\Http\Controllers\Admin\WithdrawalsController::class, 'detachCommissions']);
            Route::post('/{id}/approve', [\App\Http\Controllers\Admin\WithdrawalsController::class, 'approve']);
            Route::post('/{id}/reject', [\App\Http\Controllers\Admin\WithdrawalsController::class, 'reject']);
            Route::post('/{id}/mark-in-payment', [\App\Http\Controllers\Admin\WithdrawalsController::class, 'markInPayment']);
            Route::post('/{id}/mark-paid', [\App\Http\Controllers\Admin\WithdrawalsController::class, 'markPaid']);
            Route::get('/users/{user_id}/eligible-commissions', [\App\Http\Controllers\Admin\WithdrawalsController::class, 'getEligibleCommissions']);
        });

        // Commission Backfill
        Route::prefix('commission-backfill')->group(function () {
            Route::get('/', [CommissionBackfillController::class, 'index']);
            Route::get('/statistics', [CommissionBackfillController::class, 'statistics']);
            Route::post('/update-strategy', [CommissionBackfillController::class, 'updateStrategy']);
            Route::get('/validate-sample', [CommissionBackfillController::class, 'validateSample']);
            Route::get('/download-report', [CommissionBackfillController::class, 'downloadReport']);
            Route::post('/dry-run', [CommissionBackfillController::class, 'runDryRun']);
            Route::post('/apply', [CommissionBackfillController::class, 'runApply']);
            Route::get('/reports', [CommissionBackfillController::class, 'getReports']);
        });

        // System Health
        Route::prefix('system-health')->group(function () {
            Route::get('/', [SystemHealthController::class, 'dashboard']);
            Route::post('/ozonexpress-tracking', [SystemHealthController::class, 'runOzonExpressTracking']);
            Route::post('/commission-processing', [SystemHealthController::class, 'runCommissionProcessing']);
        });

        // Settings Management
        Route::prefix('settings')->group(function () {
            Route::get('/', [SettingsController::class, 'index']);

            // New Settings System
            Route::get('/new', [SettingsController::class, 'getNewSettings']);
            Route::get('/{category}', [SettingsController::class, 'getByCategory']);
            Route::put('/{category}', [SettingsController::class, 'updateByCategory']);

            // Legacy Settings
            Route::get('/commission', [SettingsController::class, 'getCommissionSettings']);
            Route::put('/commission', [SettingsController::class, 'updateCommissionSettings']);
            Route::get('/ozonexpress', [SettingsController::class, 'getOzonExpressSettings']);
            Route::put('/ozonexpress', [SettingsController::class, 'updateOzonExpressSettings']);
            Route::get('/system', [SettingsController::class, 'getSystemSettings']);
            Route::put('/system', [SettingsController::class, 'updateSystemSettings']);
            Route::post('/reset', [SettingsController::class, 'resetToDefaults']);
        });

        // Stock Management
        Route::prefix('stock')->group(function () {
            Route::get('/', [StockController::class, 'index']);
            Route::get('/summary', [StockController::class, 'summary']);
            Route::post('/movements', [StockController::class, 'createMovement']);
            Route::get('/{varianteId}/history', [StockController::class, 'history']);
        });

        // Stock Reservations Management
        Route::prefix('reservations')->group(function () {
            Route::get('/', [ReservationStockController::class, 'index']);
            Route::post('/', [ReservationStockController::class, 'store']);
            Route::get('/options', [ReservationStockController::class, 'options']);
            Route::get('/stats', [ReservationStockController::class, 'stats']);
            Route::post('/cleanup', [ReservationStockController::class, 'cleanup']);
            Route::get('/{reservation}', [ReservationStockController::class, 'show']);
            Route::post('/{reservation}/release', [ReservationStockController::class, 'release']);
            Route::post('/{reservation}/use', [ReservationStockController::class, 'use']);
        });

        // Debug route for stock
        Route::get('stock-debug', function () {
            return response()->json([
                'success' => true,
                'message' => 'Stock debug route working',
                'data' => [
                    'products_count' => \App\Models\Produit::count(),
                    'variants_count' => \App\Models\ProduitVariante::count(),
                    'stocks_count' => \App\Models\Stock::count(),
                ],
            ]);
        });

        // Support Tickets Management
        Route::prefix('support/tickets')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\TicketsController::class, 'index']);
            Route::get('/statistics', [\App\Http\Controllers\Admin\TicketsController::class, 'statistics']);
            Route::post('/', [\App\Http\Controllers\Admin\TicketsController::class, 'store']);
            Route::get('/{ticket}', [\App\Http\Controllers\Admin\TicketsController::class, 'show']);
            Route::post('/{ticket}', [\App\Http\Controllers\Admin\TicketsController::class, 'update']);
            Route::post('/{ticket}/assign', [\App\Http\Controllers\Admin\TicketsController::class, 'assign']);
            Route::post('/{ticket}/status', [\App\Http\Controllers\Admin\TicketsController::class, 'changeStatus']);
            Route::delete('/{ticket}', [\App\Http\Controllers\Admin\TicketsController::class, 'destroy']);
            Route::post('/bulk-action', [\App\Http\Controllers\Admin\TicketsController::class, 'bulkAction']);

            // Ticket Messages
            Route::get('/{ticket}/messages', [\App\Http\Controllers\Admin\TicketMessagesController::class, 'index']);
            Route::post('/{ticket}/messages', [\App\Http\Controllers\Admin\TicketMessagesController::class, 'store']);
            Route::get('/{ticket}/messages/{message}', [\App\Http\Controllers\Admin\TicketMessagesController::class, 'show']);
            Route::delete('/{ticket}/messages/{message}', [\App\Http\Controllers\Admin\TicketMessagesController::class, 'destroy']);
        });

        // OzonExpress Integration Management
        Route::prefix('integrations/ozon')->group(function () {
            // Settings
            Route::get('settings', [\App\Http\Controllers\Admin\OzonSettingsController::class, 'show']);
            Route::get('settings/edit', [\App\Http\Controllers\Admin\OzonSettingsController::class, 'edit']);
            Route::put('settings', [\App\Http\Controllers\Admin\OzonSettingsController::class, 'update']);
            Route::post('settings/test', [\App\Http\Controllers\Admin\OzonSettingsController::class, 'testConnection']);

            // Cities
            Route::get('cities', [\App\Http\Controllers\Admin\OzonCitiesController::class, 'index']);
            Route::post('cities', [\App\Http\Controllers\Admin\OzonCitiesController::class, 'store']);
            Route::get('cities/stats', [\App\Http\Controllers\Admin\OzonCitiesController::class, 'stats']);
            Route::get('cities/{id}', [\App\Http\Controllers\Admin\OzonCitiesController::class, 'show']);
            Route::put('cities/{id}', [\App\Http\Controllers\Admin\OzonCitiesController::class, 'update']);
            Route::delete('cities/{id}', [\App\Http\Controllers\Admin\OzonCitiesController::class, 'destroy']);
            Route::post('cities/{id}/restore', [\App\Http\Controllers\Admin\OzonCitiesController::class, 'restore']);
            Route::delete('cities/{id}/force', [\App\Http\Controllers\Admin\OzonCitiesController::class, 'forceDelete']);
            Route::post('cities/import', [\App\Http\Controllers\Admin\OzonCitiesController::class, 'import']);
        });

        // Referral Management
        Route::prefix('referrals')->group(function () {
            // Dashboard and statistics
            Route::get('dashboard/stats', [AdminReferralController::class, 'getDashboardStats']);
            Route::get('referred-users', [AdminReferralController::class, 'getReferredUsers']);

            // Dispensations (manual rewards)
            Route::get('dispensations', [ReferralDispensationController::class, 'index']);
            Route::post('dispensations', [ReferralDispensationController::class, 'store']);
            Route::get('dispensations/{id}', [ReferralDispensationController::class, 'show']);
            Route::get('dispensations/summary/stats', [ReferralDispensationController::class, 'getSummaryStats']);
            Route::get('dispensations/affiliate/{affiliateId}', [ReferralDispensationController::class, 'getAffiliateDispensations']);

            // Affiliate referral details
            Route::get('affiliates/{affiliateId}', [AdminAffiliateReferralController::class, 'show']);
            Route::get('affiliates/{affiliateId}/referred-users', [AdminAffiliateReferralController::class, 'getReferredUsers']);
            Route::get('performance/comparison', [AdminAffiliateReferralController::class, 'getPerformanceComparison']);
        });

        // Points Management (New Dispensation System)
        Route::prefix('referrers')->group(function () {
            Route::get('/', [ReferrersController::class, 'index']);
            Route::get('statistics', [ReferrersController::class, 'getStatistics']);
            Route::post('dispensations', [ReferrersController::class, 'createDispensation']);
            Route::get('{affiliateId}/dispensations', [ReferrersController::class, 'getDispensationHistory']);
            Route::get('{affiliateId}/points', [ReferrersController::class, 'getPointsSummary']);
        });

        // Testing & Debug Routes (only in non-production)
        if (!app()->environment('production')) {
            Route::prefix('test')->group(function () {
                Route::get('ozonexpress', [TestController::class, 'testOzonExpress']);
                Route::get('bulk-operations', [TestController::class, 'testBulkOperations']);
                Route::get('system-status', [TestController::class, 'systemStatus']);
                Route::get('api-connectivity', [TestController::class, 'testApiConnectivity']);
                Route::get('shipping-parcels', [TestController::class, 'getShippingParcels']);
                Route::post('sync-parcels', [TestController::class, 'syncParcelsFromPlatform']);
                Route::get('platform-parcels', [TestController::class, 'getRealParcelsFromPlatform']);
                Route::post('create-parcel', [TestController::class, 'testCreateParcel']);
                Route::post('track-parcel', [TestController::class, 'testTrackParcel']);
                Route::get('basic-connectivity', [TestController::class, 'testBasicConnectivity']);
            });
        }
    });

    // Affiliate only routes (with session support for cart)
    Route::middleware(['auth:sanctum', 'role:affiliate'])->prefix('affiliate')->group(function () {
        // Dashboard & Statistics (New comprehensive dashboard)
        Route::get('dashboard/stats', [AffiliateDashboardController::class, 'getStats']);
        Route::get('dashboard/charts', [AffiliateDashboardController::class, 'getChartData']);
        Route::get('dashboard/tables', [AffiliateDashboardController::class, 'getTableData']);
        Route::get('dashboard/referral-link', [AffiliateDashboardController::class, 'getReferralLink']);

        // Legacy dashboard route (keep for backward compatibility)
        Route::get('dashboard', function () {
            return response()->json(['message' => 'Affiliate Dashboard']);
        });

        // Orders History
        Route::get('orders', [App\Http\Controllers\Affiliate\OrdersController::class, 'index']);
        Route::get('orders/{id}', [App\Http\Controllers\Affiliate\OrdersController::class, 'show']);

        // Payments (Commissions & Withdrawals)
        Route::get('commissions', [App\Http\Controllers\Affiliate\PaymentsController::class, 'commissions']);
        Route::get('withdrawals', [App\Http\Controllers\Affiliate\PaymentsController::class, 'withdrawals']);
        Route::get('withdrawals/{id}', [App\Http\Controllers\Affiliate\PaymentsController::class, 'showWithdrawal']);
        Route::get('withdrawals/{id}/pdf', [App\Http\Controllers\Affiliate\PaymentsController::class, 'downloadPdf'])->name('affiliate.withdrawals.pdf');
        Route::post('withdrawals/request', [App\Http\Controllers\Affiliate\PaymentsController::class, 'requestPayout']);

        // Support Tickets
        Route::get('tickets', [App\Http\Controllers\Affiliate\TicketsController::class, 'index']);
        Route::post('tickets', [App\Http\Controllers\Affiliate\TicketsController::class, 'store']);
        Route::get('tickets/{id}', [App\Http\Controllers\Affiliate\TicketsController::class, 'show']);
        Route::post('tickets/{id}/messages', [App\Http\Controllers\Affiliate\TicketsController::class, 'addMessage']);
        Route::patch('tickets/{id}/status', [App\Http\Controllers\Affiliate\TicketsController::class, 'updateStatus']);
        Route::get('tickets/attachments/{id}/download', [App\Http\Controllers\Affiliate\TicketsController::class, 'downloadAttachment'])->name('affiliate.tickets.attachments.download');

        // Catalogue routes
        Route::get('catalogue', [App\Http\Controllers\Affiliate\CatalogueController::class, 'index']);
        Route::get('catalogue/{id}', [App\Http\Controllers\Affiliate\CatalogueController::class, 'show']);

        // Categories for affiliate (read-only)
        Route::get('categories', [App\Http\Controllers\Admin\CategorieController::class, 'index']);

        // Ozon cities for affiliate (read-only)
        Route::get('ozon/cities', [App\Http\Controllers\Affiliate\OzonCitiesController::class, 'index']);

        // Cart routes
        Route::prefix('cart')->group(function () {
            Route::post('add', [App\Http\Controllers\Affiliate\CartController::class, 'addItem']);
            Route::post('items', [App\Http\Controllers\Affiliate\CartController::class, 'addItem']); // Keep both for compatibility
            Route::patch('items', [App\Http\Controllers\Affiliate\CartController::class, 'updateItem']);
            Route::get('summary', [App\Http\Controllers\Affiliate\CartController::class, 'summary']);
            Route::delete('items', [App\Http\Controllers\Affiliate\CartController::class, 'removeItem']);
            Route::delete('clear', [App\Http\Controllers\Affiliate\CartController::class, 'clear']);
        });

        // Checkout route
        Route::post('checkout', [App\Http\Controllers\Affiliate\CartController::class, 'checkout']);

        // Referral Management
        Route::prefix('referrals')->group(function () {
            Route::get('dashboard', [AffiliateReferralController::class, 'getDashboard']);
            Route::get('link', [AffiliateReferralController::class, 'getReferralLink']);
            Route::get('referred-users', [AffiliateReferralController::class, 'getReferredUsers']);
            Route::get('dispensations', [AffiliateReferralController::class, 'getDispensations']);
        });

        // Points Management (Affiliate View)
        Route::prefix('points')->group(function () {
            Route::get('summary', [PointsController::class, 'getPointsSummary']);
            Route::get('dispensations', [PointsController::class, 'getDispensationHistory']);
            Route::get('earnings-breakdown', [PointsController::class, 'getEarningsBreakdown']);
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
    Route::delete('profile/remove-image', [ProfileController::class, 'removeImage']);

    // Profile KYC documents routes
    Route::get('profile/kyc-documents', [ProfileController::class, 'getKycDocuments']);
    Route::post('profile/kyc-documents', [ProfileController::class, 'uploadKycDocument']);
    Route::get('profile/kyc-documents/{id}/download', [ProfileController::class, 'downloadKycDocument']);
    Route::delete('profile/kyc-documents/{id}', [ProfileController::class, 'deleteKycDocument']);

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

// Public routes (no authentication required)
Route::prefix('public')->group(function () {
    // Referral tracking
    Route::get('referral-info/{code}', [PublicController::class, 'getReferralInfo']);
    Route::post('referral-click', [PublicController::class, 'trackReferralClick']);

    // User signup
    Route::post('signup', [PublicController::class, 'signup']);
    Route::post('verify-email', [PublicController::class, 'verifyEmail']);

    // Public statistics
    Route::get('stats', [PublicController::class, 'getPublicStats']);
});
