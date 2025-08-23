<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfilAffilie;
use App\Models\ReferralDispensation;
use App\Services\ReferralService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class ReferralDispensationController extends Controller
{
    public function __construct(
        protected ReferralService $referralService
    ) {}

    /**
     * Display a listing of dispensations.
     */
    public function index(Request $request): JsonResponse
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => __('messages.access_denied')], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'affiliate_id' => 'nullable|uuid|exists:profils_affilies,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'min_points' => 'nullable|integer|min:0',
            'max_points' => 'nullable|integer|min:0',
        ]);

        $query = ReferralDispensation::with([
            'referrerAffiliate.utilisateur:id,nom_complet,email',
            'createdByAdmin:id,nom_complet,email',
        ]);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhereHas('referrerAffiliate.utilisateur', function ($subQ) use ($search) {
                      $subQ->where('nom_complet', 'like', "%{$search}%")
                           ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('affiliate_id')) {
            $query->where('referrer_affiliate_id', $request->affiliate_id);
        }

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', Carbon::parse($request->start_date));
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', Carbon::parse($request->end_date)->endOfDay());
        }

        if ($request->filled('min_points')) {
            $query->where('points', '>=', $request->min_points);
        }

        if ($request->filled('max_points')) {
            $query->where('points', '<=', $request->max_points);
        }

        // Sort by most recent
        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $dispensations = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $dispensations->items(),
            'pagination' => [
                'current_page' => $dispensations->currentPage(),
                'last_page' => $dispensations->lastPage(),
                'per_page' => $dispensations->perPage(),
                'total' => $dispensations->total(),
            ],
        ]);
    }

    /**
     * Store a newly created dispensation.
     */
    public function store(Request $request): JsonResponse
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => __('messages.access_denied')], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'affiliate_id' => 'required|uuid|exists:profils_affilies,id',
            'points' => 'required|integer|min:1|max:10000',
            'comment' => 'required|string|min:10|max:1000',
            'reference' => 'nullable|string|max:100',
        ]);

        try {
            $affiliate = ProfilAffilie::findOrFail($request->affiliate_id);

            $dispensation = $this->referralService->createDispensation(
                $affiliate,
                $request->points,
                $request->comment,
                $request->user(),
                $request->reference
            );

            // Load relationships for response
            $dispensation->load([
                'referrerAffiliate.utilisateur:id,nom_complet,email',
                'createdByAdmin:id,nom_complet,email',
            ]);

            return response()->json([
                'success' => true,
                'message' => __('messages.dispensation_created_successfully'),
                'data' => $dispensation,
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.dispensation_creation_failed'),
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified dispensation.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => __('messages.access_denied')], Response::HTTP_FORBIDDEN);
        }

        $dispensation = ReferralDispensation::with([
            'referrerAffiliate.utilisateur:id,nom_complet,email,telephone',
            'createdByAdmin:id,nom_complet,email',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $dispensation,
        ]);
    }

    /**
     * Get dispensation statistics for an affiliate.
     */
    public function getAffiliateDispensations(Request $request, string $affiliateId): JsonResponse
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => __('messages.access_denied')], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $affiliate = ProfilAffilie::findOrFail($affiliateId);

        $query = ReferralDispensation::where('referrer_affiliate_id', $affiliateId)
            ->with('createdByAdmin:id,nom_complet,email');

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', Carbon::parse($request->start_date));
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', Carbon::parse($request->end_date)->endOfDay());
        }

        $dispensations = $query->orderBy('created_at', 'desc')->get();

        $totalPoints = $dispensations->sum('points');
        $totalDispensations = $dispensations->count();

        return response()->json([
            'success' => true,
            'data' => [
                'affiliate' => $affiliate->load('utilisateur:id,nom_complet,email'),
                'dispensations' => $dispensations,
                'summary' => [
                    'total_points' => $totalPoints,
                    'total_dispensations' => $totalDispensations,
                    'average_points' => $totalDispensations > 0 ? round($totalPoints / $totalDispensations, 2) : 0,
                ],
            ],
        ]);
    }

    /**
     * Get dispensation summary statistics.
     */
    public function getSummaryStats(Request $request): JsonResponse
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => __('messages.access_denied')], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->subDays(30);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : now();

        $query = ReferralDispensation::withinDateRange($startDate, $endDate);

        $totalPoints = $query->sum('points') ?? 0;
        $totalDispensations = $query->count();
        $uniqueAffiliates = $query->distinct('referrer_affiliate_id')->count('referrer_affiliate_id');
        $averagePoints = $totalDispensations > 0 ? round($totalPoints / $totalDispensations, 2) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_points' => $totalPoints,
                'total_dispensations' => $totalDispensations,
                'unique_affiliates' => $uniqueAffiliates,
                'average_points' => $averagePoints,
                'date_range' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
            ],
        ]);
    }
}
