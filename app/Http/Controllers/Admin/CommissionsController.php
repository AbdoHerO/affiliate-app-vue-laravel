<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveCommissionRequest;
use App\Http\Requests\Admin\RejectCommissionRequest;
use App\Http\Requests\Admin\AdjustCommissionRequest;
use App\Http\Requests\Admin\BulkCommissionRequest;
use App\Http\Resources\Admin\CommissionResource;
use App\Models\CommissionAffilie;
use App\Models\Commande;
use App\Services\CommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommissionsController extends Controller
{
    public function __construct(
        protected CommissionService $commissionService
    ) {}

    /**
     * Display a listing of commissions
     */
    public function index(Request $request): JsonResponse
    {
        $query = CommissionAffilie::with([
            'affiliate:id,nom_complet,email',
            'commande:id,statut,total_ttc,created_at',
            'commandeArticle.produit:id,titre'
        ]);

        // Apply filters
        if ($request->filled('q')) {
            $search = $request->get('q');
            $query->whereHas('affiliate', function ($q) use ($search) {
                $q->where('nom_complet', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $statuses = is_array($request->status) ? $request->status : [$request->status];
            $query->whereIn('status', $statuses);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('commande_id')) {
            $query->where('commande_id', $request->commande_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->boolean('eligible_only')) {
            $query->where('status', CommissionAffilie::STATUS_ELIGIBLE);
        }

        // Sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $commissions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => CommissionResource::collection($commissions->items()),
            'pagination' => [
                'current_page' => $commissions->currentPage(),
                'last_page' => $commissions->lastPage(),
                'per_page' => $commissions->perPage(),
                'total' => $commissions->total(),
                'from' => $commissions->firstItem(),
                'to' => $commissions->lastItem(),
            ]
        ]);
    }

    /**
     * Display the specified commission
     */
    public function show(string $id): JsonResponse
    {
        try {
            \Log::info('CommissionsController::show called with ID: ' . $id);

            $commission = CommissionAffilie::with([
                'affiliate:id,nom_complet,email,telephone',
                'commande:id,statut,total_ttc,created_at,notes',
                'commandeArticle.produit:id,titre,prix_vente'
            ])->findOrFail($id);

            \Log::info('Commission found: ' . $commission->id);

            $resource = new CommissionResource($commission);
            \Log::info('Commission resource created successfully');

            return response()->json([
                'success' => true,
                'data' => $resource
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in CommissionsController::show: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement de la commission: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve a commission
     */
    public function approve(ApproveCommissionRequest $request, string $id): JsonResponse
    {
        $commission = CommissionAffilie::findOrFail($id);

        if (!$commission->canBeApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Commission cannot be approved in current status'
            ], 422);
        }

        DB::transaction(function () use ($commission, $request) {
            $commission->update([
                'status' => CommissionAffilie::STATUS_APPROVED,
                'approved_at' => now(),
                'notes' => $this->appendNote($commission->notes, $request->note ?? 'Approved by admin')
            ]);
        });

        Log::info('Commission approved', [
            'commission_id' => $commission->id,
            'admin_id' => $request->user()->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commission approved successfully',
            'data' => new CommissionResource($commission->fresh())
        ]);
    }

    /**
     * Reject a commission
     */
    public function reject(RejectCommissionRequest $request, string $id): JsonResponse
    {
        $commission = CommissionAffilie::findOrFail($id);

        if (!$commission->canBeRejected()) {
            return response()->json([
                'success' => false,
                'message' => 'Commission cannot be rejected in current status'
            ], 422);
        }

        DB::transaction(function () use ($commission, $request) {
            $commission->update([
                'status' => CommissionAffilie::STATUS_REJECTED,
                'notes' => $this->appendNote($commission->notes, 'Rejected: ' . $request->reason)
            ]);
        });

        Log::info('Commission rejected', [
            'commission_id' => $commission->id,
            'reason' => $request->reason,
            'admin_id' => $request->user()->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commission rejected successfully',
            'data' => new CommissionResource($commission->fresh())
        ]);
    }

    /**
     * Adjust a commission amount
     */
    public function adjust(AdjustCommissionRequest $request, string $id): JsonResponse
    {
        $commission = CommissionAffilie::findOrFail($id);

        if (!$commission->canBeAdjusted()) {
            return response()->json([
                'success' => false,
                'message' => 'Commission cannot be adjusted in current status'
            ], 422);
        }

        $originalAmount = $commission->amount;
        $newAmount = $request->amount;
        $difference = $newAmount - $originalAmount;

        DB::transaction(function () use ($commission, $request, $originalAmount, $newAmount, $difference) {
            $meta = $commission->meta ?? [];
            $meta['adjustments'] = $meta['adjustments'] ?? [];
            $meta['adjustments'][] = [
                'original_amount' => $originalAmount,
                'new_amount' => $newAmount,
                'difference' => $difference,
                'reason' => $request->note,
                'adjusted_at' => now()->toISOString(),
                'adjusted_by' => $request->user()->id
            ];

            $commission->update([
                'amount' => $newAmount,
                'status' => CommissionAffilie::STATUS_ADJUSTED,
                'meta' => $meta,
                'notes' => $this->appendNote($commission->notes, 'Adjusted: ' . $request->note)
            ]);
        });

        Log::info('Commission adjusted', [
            'commission_id' => $commission->id,
            'original_amount' => $originalAmount,
            'new_amount' => $newAmount,
            'admin_id' => $request->user()->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commission adjusted successfully',
            'data' => new CommissionResource($commission->fresh())
        ]);
    }

    /**
     * Bulk approve commissions
     */
    public function bulkApprove(BulkCommissionRequest $request): JsonResponse
    {
        $ids = $request->ids;
        $note = $request->note ?? 'Bulk approved by admin';

        $commissions = CommissionAffilie::whereIn('id', $ids)->get();
        $updated = [];

        DB::transaction(function () use ($commissions, $note, &$updated) {
            foreach ($commissions as $commission) {
                if ($commission->canBeApproved()) {
                    $commission->update([
                        'status' => CommissionAffilie::STATUS_APPROVED,
                        'approved_at' => now(),
                        'notes' => $this->appendNote($commission->notes, $note)
                    ]);
                    $updated[] = $commission;
                }
            }
        });

        Log::info('Bulk commission approval', [
            'requested_count' => count($ids),
            'updated_count' => count($updated),
            'admin_id' => $request->user()->id
        ]);

        return response()->json([
            'success' => true,
            'message' => count($updated) . ' commission(s) approved successfully',
            'data' => [
                'updated_count' => count($updated),
                'requested_count' => count($ids)
            ]
        ]);
    }

    /**
     * Bulk reject commissions
     */
    public function bulkReject(BulkCommissionRequest $request): JsonResponse
    {
        $ids = $request->ids;
        $reason = $request->reason;

        $commissions = CommissionAffilie::whereIn('id', $ids)->get();
        $updated = [];

        DB::transaction(function () use ($commissions, $reason, &$updated) {
            foreach ($commissions as $commission) {
                if ($commission->canBeRejected()) {
                    $commission->update([
                        'status' => CommissionAffilie::STATUS_REJECTED,
                        'notes' => $this->appendNote($commission->notes, 'Bulk rejected: ' . $reason)
                    ]);
                    $updated[] = $commission;
                }
            }
        });

        Log::info('Bulk commission rejection', [
            'requested_count' => count($ids),
            'updated_count' => count($updated),
            'reason' => $reason,
            'admin_id' => $request->user()->id
        ]);

        return response()->json([
            'success' => true,
            'message' => count($updated) . ' commission(s) rejected successfully',
            'data' => [
                'updated_count' => count($updated),
                'requested_count' => count($ids)
            ]
        ]);
    }

    /**
     * Recalculate commissions for an order
     */
    public function recalculate(string $commandeId): JsonResponse
    {
        $order = Commande::findOrFail($commandeId);
        
        $result = $this->commissionService->recalculate($order);

        return response()->json([
            'success' => $result['success'],
            'message' => 'Commission recalculation completed',
            'data' => $result
        ]);
    }

    /**
     * Export commissions as CSV
     */
    public function export(Request $request): StreamedResponse
    {
        $query = CommissionAffilie::with([
            'affiliate:id,nom_complet,email',
            'commande:id,statut,total_ttc,created_at'
        ]);

        // Apply same filters as index
        // ... (same filter logic as index method)

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($handle, [
                'ID', 'Affiliate Name', 'Affiliate Email', 'Order ID', 'Order Status',
                'Base Amount', 'Rate', 'Amount', 'Currency', 'Status', 'Rule Code',
                'Eligible At', 'Approved At', 'Paid At', 'Created At'
            ]);

            $query->chunk(1000, function ($commissions) use ($handle) {
                foreach ($commissions as $commission) {
                    fputcsv($handle, [
                        $commission->id,
                        $commission->affiliate?->nom_complet,
                        $commission->affiliate?->email,
                        $commission->commande_id,
                        $commission->commande?->statut,
                        $commission->base_amount,
                        $commission->rate,
                        $commission->amount,
                        $commission->currency,
                        $commission->status,
                        $commission->rule_code,
                        $commission->eligible_at?->format('Y-m-d H:i:s'),
                        $commission->approved_at?->format('Y-m-d H:i:s'),
                        $commission->paid_at?->format('Y-m-d H:i:s'),
                        $commission->created_at->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($handle);
        }, 'commissions-' . now()->format('Y-m-d-H-i-s') . '.csv');
    }

    /**
     * Get commission summary statistics
     */
    public function summary(Request $request): JsonResponse
    {
        $query = CommissionAffilie::query();

        // Apply same filters as index method
        // ... (filter logic)

        $summary = [
            'total_calculated' => $query->clone()->where('status', CommissionAffilie::STATUS_CALCULATED)->sum('amount'),
            'total_eligible' => $query->clone()->where('status', CommissionAffilie::STATUS_ELIGIBLE)->sum('amount'),
            'total_approved' => $query->clone()->where('status', CommissionAffilie::STATUS_APPROVED)->sum('amount'),
            'total_paid' => $query->clone()->where('status', CommissionAffilie::STATUS_PAID)->sum('amount'),
            'count_calculated' => $query->clone()->where('status', CommissionAffilie::STATUS_CALCULATED)->count(),
            'count_eligible' => $query->clone()->where('status', CommissionAffilie::STATUS_ELIGIBLE)->count(),
            'count_approved' => $query->clone()->where('status', CommissionAffilie::STATUS_APPROVED)->count(),
            'count_paid' => $query->clone()->where('status', CommissionAffilie::STATUS_PAID)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    /**
     * Helper method to append notes
     */
    protected function appendNote(?string $existingNotes, string $newNote): string
    {
        if (empty($existingNotes)) {
            return $newNote;
        }

        return $existingNotes . "\n" . now()->format('Y-m-d H:i:s') . ': ' . $newNote;
    }
}
