<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateWithdrawalRequest;
use App\Http\Requests\Admin\AttachCommissionsRequest;
use App\Http\Requests\Admin\DetachCommissionsRequest;
use App\Http\Requests\Admin\ApproveWithdrawalRequest;
use App\Http\Requests\Admin\RejectWithdrawalRequest;
use App\Http\Requests\Admin\MarkInPaymentRequest;
use App\Http\Requests\Admin\MarkPaidRequest;
use App\Http\Resources\Admin\WithdrawalResource;
use App\Models\Withdrawal;
use App\Models\User;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WithdrawalsController extends Controller
{
    public function __construct(
        protected WithdrawalService $withdrawalService
    ) {}

    /**
     * Display a listing of withdrawals
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Withdrawal::with(['user:id,nom_complet,email,telephone']);

            // Apply filters
            if ($request->filled('q')) {
                $query->search($request->q);
            }

            if ($request->filled('status')) {
                $statuses = is_array($request->status) ? $request->status : [$request->status];
                $query->byStatus($statuses);
            }

            if ($request->filled('user_id')) {
                $query->byUser($request->user_id);
            }

            if ($request->filled('method')) {
                $query->byMethod($request->method);
            }

            if ($request->filled('date_from') || $request->filled('date_to')) {
                $query->byDateRange($request->date_from, $request->date_to);
            }

            // Sorting
            $sortField = $request->get('sort', 'created_at');
            $sortDirection = $request->get('dir', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // Pagination
            $perPage = min($request->get('per_page', 15), 100);
            $withdrawals = $query->paginate($perPage);

            // Get summary stats for the current filters
            $summaryStats = $this->withdrawalService->getSummaryStats([
                'user_id' => $request->user_id,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
            ]);

            return response()->json([
                'success' => true,
                'data' => WithdrawalResource::collection($withdrawals),
                'pagination' => [
                    'current_page' => $withdrawals->currentPage(),
                    'last_page' => $withdrawals->lastPage(),
                    'per_page' => $withdrawals->perPage(),
                    'total' => $withdrawals->total(),
                    'from' => $withdrawals->firstItem(),
                    'to' => $withdrawals->lastItem(),
                ],
                'summary' => $summaryStats,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching withdrawals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des retraits',
            ], 500);
        }
    }

    /**
     * Display the specified withdrawal
     */
    public function show(string $id): JsonResponse
    {
        try {
            $withdrawal = Withdrawal::with([
                'user:id,nom_complet,email,telephone,rib,bank_type',
                'items.commission:id,amount,status,created_at,commande_id,commande_article_id',
                'items.commission.commande:id,statut,total_ttc,created_at',
                'items.commission.commandeArticle:id,commande_id,produit_id,quantite,prix_unitaire,total_ligne,type_command',
                'items.commission.commandeArticle.produit:id,titre,sku'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new WithdrawalResource($withdrawal),
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching withdrawal', [
                'withdrawal_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Retrait non trouvé',
            ], 404);
        }
    }

    /**
     * Store a newly created withdrawal
     */
    public function store(CreateWithdrawalRequest $request): JsonResponse
    {
        try {
            $user = User::findOrFail($request->user_id);
            
            $withdrawal = $this->withdrawalService->createWithdrawal($user, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Retrait créé avec succès',
                'data' => new WithdrawalResource($withdrawal),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating withdrawal', [
                'error' => $e->getMessage(),
                'data' => $request->validated()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du retrait',
            ], 500);
        }
    }

    /**
     * Attach commissions to a withdrawal
     */
    public function attachCommissions(AttachCommissionsRequest $request, string $id): JsonResponse
    {
        try {
            $withdrawal = Withdrawal::findOrFail($id);

            if ($withdrawal->status !== Withdrawal::STATUS_PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => 'Les commissions ne peuvent être attachées qu\'aux retraits en attente',
                ], 422);
            }

            $result = $this->withdrawalService->attachCommissions($withdrawal, $request->commission_ids);

            return response()->json([
                'success' => true,
                'message' => count($result['attached']) . ' commission(s) attachée(s) avec succès',
                'data' => [
                    'attached' => $result['attached'],
                    'errors' => $result['errors'],
                    'total_amount' => $result['total_amount'],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error attaching commissions', [
                'withdrawal_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'attachement des commissions',
            ], 500);
        }
    }

    /**
     * Detach commissions from a withdrawal
     */
    public function detachCommissions(DetachCommissionsRequest $request, string $id): JsonResponse
    {
        try {
            $withdrawal = Withdrawal::findOrFail($id);

            if (!in_array($withdrawal->status, [Withdrawal::STATUS_PENDING, Withdrawal::STATUS_APPROVED])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Les commissions ne peuvent être détachées que des retraits en attente ou approuvés',
                ], 422);
            }

            $result = $this->withdrawalService->detachCommissions($withdrawal, $request->commission_ids);

            return response()->json([
                'success' => true,
                'message' => count($result['detached']) . ' commission(s) détachée(s) avec succès',
                'data' => [
                    'detached' => $result['detached'],
                    'total_amount' => $result['total_amount'],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error detaching commissions', [
                'withdrawal_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du détachement des commissions',
            ], 500);
        }
    }

    /**
     * Approve a withdrawal
     */
    public function approve(ApproveWithdrawalRequest $request, string $id): JsonResponse
    {
        try {
            $withdrawal = Withdrawal::findOrFail($id);

            if (!$withdrawal->canBeApproved()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce retrait ne peut pas être approuvé dans son état actuel',
                ], 422);
            }

            $this->withdrawalService->reserveCommissions($withdrawal);

            // Add admin note if provided
            if ($request->filled('note')) {
                $withdrawal->update([
                    'notes' => $this->appendNote($withdrawal->notes, $request->note)
                ]);
            }

            Log::info('Withdrawal approved', [
                'withdrawal_id' => $withdrawal->id,
                'admin_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Retrait approuvé avec succès',
                'data' => new WithdrawalResource($withdrawal->fresh()),
            ]);

        } catch (\Exception $e) {
            Log::error('Error approving withdrawal', [
                'withdrawal_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'approbation du retrait',
            ], 500);
        }
    }

    /**
     * Reject a withdrawal
     */
    public function reject(RejectWithdrawalRequest $request, string $id): JsonResponse
    {
        try {
            $withdrawal = Withdrawal::findOrFail($id);

            if (!$withdrawal->canBeRejected()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce retrait ne peut pas être rejeté dans son état actuel',
                ], 422);
            }

            DB::transaction(function () use ($withdrawal, $request) {
                // Release commissions if they were reserved
                if ($withdrawal->status === Withdrawal::STATUS_APPROVED) {
                    $this->withdrawalService->releaseCommissions($withdrawal);
                }

                // Update withdrawal status
                $withdrawal->update([
                    'status' => Withdrawal::STATUS_REJECTED,
                    'admin_reason' => $request->reason,
                    'notes' => $this->appendNote($withdrawal->notes, 'Rejeté: ' . $request->reason)
                ]);
            });

            Log::info('Withdrawal rejected', [
                'withdrawal_id' => $withdrawal->id,
                'reason' => $request->reason,
                'admin_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Retrait rejeté avec succès',
                'data' => new WithdrawalResource($withdrawal->fresh()),
            ]);

        } catch (\Exception $e) {
            Log::error('Error rejecting withdrawal', [
                'withdrawal_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du rejet du retrait',
            ], 500);
        }
    }

    /**
     * Mark withdrawal as in payment
     */
    public function markInPayment(MarkInPaymentRequest $request, string $id): JsonResponse
    {
        try {
            $withdrawal = Withdrawal::findOrFail($id);

            if (!$withdrawal->canBeMarkedInPayment()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce retrait ne peut pas être marqué en cours de paiement',
                ], 422);
            }

            $withdrawal->update([
                'status' => Withdrawal::STATUS_IN_PAYMENT,
                'payment_ref' => $request->payment_ref,
                'notes' => $this->appendNote($withdrawal->notes, 'Marqué en cours de paiement')
            ]);

            Log::info('Withdrawal marked in payment', [
                'withdrawal_id' => $withdrawal->id,
                'admin_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Retrait marqué en cours de paiement',
                'data' => new WithdrawalResource($withdrawal->fresh()),
            ]);

        } catch (\Exception $e) {
            Log::error('Error marking withdrawal in payment', [
                'withdrawal_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du marquage en cours de paiement',
            ], 500);
        }
    }

    /**
     * Mark withdrawal as paid
     */
    public function markPaid(MarkPaidRequest $request, string $id): JsonResponse
    {
        try {
            $withdrawal = Withdrawal::findOrFail($id);

            if (!$withdrawal->canBeMarkedPaid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce retrait ne peut pas être marqué comme payé',
                ], 422);
            }

            $data = [
                'payment_ref' => $request->payment_ref,
                'paid_at' => $request->paid_at ? \Carbon\Carbon::parse($request->paid_at) : now(),
            ];

            // Handle evidence file upload
            if ($request->hasFile('evidence')) {
                $data['evidence_path'] = $this->withdrawalService->uploadEvidence($request->file('evidence'));
            }

            $this->withdrawalService->markAsPaid($withdrawal, $data);

            Log::info('Withdrawal marked as paid', [
                'withdrawal_id' => $withdrawal->id,
                'admin_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Retrait marqué comme payé avec succès',
                'data' => new WithdrawalResource($withdrawal->fresh()),
            ]);

        } catch (\Exception $e) {
            Log::error('Error marking withdrawal as paid', [
                'withdrawal_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du marquage comme payé',
            ], 500);
        }
    }

    /**
     * Get eligible commissions for a user
     */
    public function getEligibleCommissions(Request $request, string $userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);

            $filters = $request->only(['status', 'date_from', 'date_to', 'min_amount']);
            $query = $this->withdrawalService->getEligibleCommissions($user, $filters);

            // Pagination
            $perPage = min($request->get('per_page', 15), 100);
            $commissions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $commissions,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching eligible commissions', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des commissions éligibles',
            ], 500);
        }
    }

    /**
     * Export withdrawals as CSV
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Withdrawal::with(['user:id,nom_complet,email']);

        // Apply same filters as index
        if ($request->filled('q')) {
            $query->search($request->q);
        }

        if ($request->filled('status')) {
            $statuses = is_array($request->status) ? $request->status : [$request->status];
            $query->byStatus($statuses);
        }

        if ($request->filled('user_id')) {
            $query->byUser($request->user_id);
        }

        if ($request->filled('method')) {
            $query->byMethod($request->method);
        }

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $query->byDateRange($request->date_from, $request->date_to);
        }

        $filename = 'withdrawals_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // CSV headers
            fputcsv($handle, [
                'ID',
                'Affiliate Name',
                'Affiliate Email',
                'Amount',
                'Status',
                'Method',
                'Payment Ref',
                'Created At',
                'Approved At',
                'Paid At',
            ]);

            // Export data in chunks
            $query->chunk(1000, function ($withdrawals) use ($handle) {
                foreach ($withdrawals as $withdrawal) {
                    fputcsv($handle, [
                        $withdrawal->id,
                        $withdrawal->user->nom_complet ?? '',
                        $withdrawal->user->email ?? '',
                        $withdrawal->amount,
                        $withdrawal->status,
                        $withdrawal->method,
                        $withdrawal->payment_ref ?? '',
                        $withdrawal->created_at?->format('Y-m-d H:i:s'),
                        $withdrawal->approved_at?->format('Y-m-d H:i:s'),
                        $withdrawal->paid_at?->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Helper method to append notes
     */
    private function appendNote(?string $existingNotes, string $newNote): string
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $formattedNote = "[{$timestamp}] {$newNote}";

        return $existingNotes
            ? $existingNotes . "\n" . $formattedNote
            : $formattedNote;
    }
}
