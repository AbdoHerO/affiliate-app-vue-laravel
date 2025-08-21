<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CommissionResource;
use App\Http\Resources\Admin\WithdrawalResource;
use App\Models\CommissionAffilie;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentsController extends Controller
{
    public function __construct(
        protected WithdrawalService $withdrawalService
    ) {}

    /**
     * Display commissions for the authenticated affiliate.
     */
    public function commissions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Ensure user is an approved affiliate
            if (!$user->isApprovedAffiliate()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only approved affiliates can view commissions.',
                ], 403);
            }

            $query = CommissionAffilie::with([
                'commande:id,statut,total_ttc,created_at',
                'commandeArticle.produit:id,titre'
            ])
            ->where('user_id', $user->id); // Scope to current affiliate only

            // Apply filters
            if ($request->filled('q')) {
                $search = $request->get('q');
                $query->whereHas('commande', function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%");
                })
                ->orWhereHas('commandeArticle.produit', function ($q) use ($search) {
                    $q->where('titre', 'like', "%{$search}%");
                });
            }

            if ($request->filled('status')) {
                $statuses = is_array($request->status) ? $request->status : [$request->status];
                $query->whereIn('status', $statuses);
            }

            if ($request->filled('date_from') || $request->filled('date_to')) {
                if ($request->filled('date_from')) {
                    $query->whereDate('created_at', '>=', $request->date_from);
                }
                if ($request->filled('date_to')) {
                    $query->whereDate('created_at', '<=', $request->date_to);
                }
            }

            if ($request->filled('amount_min') || $request->filled('amount_max')) {
                if ($request->filled('amount_min')) {
                    $query->where('amount', '>=', $request->amount_min);
                }
                if ($request->filled('amount_max')) {
                    $query->where('amount', '<=', $request->amount_max);
                }
            }

            // Apply sorting
            $sortBy = $request->get('sort', 'created_at');
            $sortDir = $request->get('dir', 'desc');
            $query->orderBy($sortBy, $sortDir);

            // Paginate
            $perPage = min($request->get('per_page', 15), 100);
            $commissions = $query->paginate($perPage);

            // Calculate summary by status
            $summary = CommissionAffilie::where('user_id', $user->id)
                ->selectRaw('status, COUNT(*) as count, SUM(amount) as total')
                ->groupBy('status')
                ->get()
                ->keyBy('status');

            return response()->json([
                'success' => true,
                'data' => CommissionResource::collection($commissions),
                'summary' => $summary,
                'pagination' => [
                    'current_page' => $commissions->currentPage(),
                    'last_page' => $commissions->lastPage(),
                    'per_page' => $commissions->perPage(),
                    'total' => $commissions->total(),
                    'from' => $commissions->firstItem(),
                    'to' => $commissions->lastItem(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching affiliate commissions', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'filters' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des commissions.',
            ], 500);
        }
    }

    /**
     * Display withdrawals for the authenticated affiliate.
     */
    public function withdrawals(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Ensure user is an approved affiliate
            if (!$user->isApprovedAffiliate()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only approved affiliates can view withdrawals.',
                ], 403);
            }

            $query = Withdrawal::where('user_id', $user->id); // Scope to current affiliate only

            // Apply filters
            if ($request->filled('status')) {
                $statuses = is_array($request->status) ? $request->status : [$request->status];
                $query->whereIn('status', $statuses);
            }

            if ($request->filled('date_from') || $request->filled('date_to')) {
                if ($request->filled('date_from')) {
                    $query->whereDate('created_at', '>=', $request->date_from);
                }
                if ($request->filled('date_to')) {
                    $query->whereDate('created_at', '<=', $request->date_to);
                }
            }

            // Apply sorting
            $sortBy = $request->get('sort', 'created_at');
            $sortDir = $request->get('dir', 'desc');
            $query->orderBy($sortBy, $sortDir);

            // Paginate
            $perPage = min($request->get('per_page', 15), 100);
            $withdrawals = $query->paginate($perPage);

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
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching affiliate withdrawals', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'filters' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des retraits.',
            ], 500);
        }
    }

    /**
     * Display the specified withdrawal for the authenticated affiliate.
     */
    public function showWithdrawal(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Ensure user is an approved affiliate
            if (!$user->isApprovedAffiliate()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only approved affiliates can view withdrawals.',
                ], 403);
            }

            $withdrawal = Withdrawal::with(['items.commission'])
                ->where('user_id', $user->id) // Ensure ownership
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new WithdrawalResource($withdrawal),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Retrait non trouvé ou accès non autorisé.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error fetching affiliate withdrawal', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'withdrawal_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération du retrait.',
            ], 500);
        }
    }

    /**
     * Request a payout for eligible commissions.
     */
    public function requestPayout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Ensure user is an approved affiliate
            if (!$user->isApprovedAffiliate()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only approved affiliates can request payouts.',
                ], 403);
            }

            // Validate request
            $request->validate([
                'notes' => 'nullable|string|max:1000',
            ]);

            // Check for eligible commissions
            $eligibleCommissions = CommissionAffilie::where('user_id', $user->id)
                ->where('status', 'eligible')
                ->whereNull('paid_withdrawal_id')
                ->get();

            if ($eligibleCommissions->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune commission éligible trouvée pour le retrait.',
                ], 422);
            }

            // Create withdrawal using the service
            $withdrawal = $this->withdrawalService->createWithdrawal($user, [
                'amount' => $eligibleCommissions->sum('amount'),
                'method' => 'bank_transfer', // Default method
                'notes' => $request->notes,
                'iban_rib' => $user->rib,
                'bank_type' => $user->bank_type,
            ]);

            // Attach eligible commissions to the withdrawal
            foreach ($eligibleCommissions as $commission) {
                $commission->update(['paid_withdrawal_id' => $withdrawal->id]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Demande de retrait créée avec succès.',
                'data' => new WithdrawalResource($withdrawal->load('items')),
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error creating affiliate payout request', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de la demande de retrait.',
            ], 500);
        }
    }

    /**
     * Download PDF invoice for a withdrawal.
     */
    public function downloadPdf(Request $request, string $id)
    {
        try {
            $user = $request->user();

            // Ensure user is an approved affiliate
            if (!$user->isApprovedAffiliate()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only approved affiliates can download invoices.',
                ], 403);
            }

            $withdrawal = Withdrawal::with(['items.commission.commande', 'user'])
                ->where('user_id', $user->id) // Ensure ownership
                ->findOrFail($id);

            // Only allow PDF download for approved/paid withdrawals
            if (!in_array($withdrawal->status, ['approved', 'in_payment', 'paid'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le PDF n\'est disponible que pour les retraits approuvés ou payés.',
                ], 422);
            }

            // Generate PDF using a service or library (e.g., DomPDF, TCPDF)
            $pdf = $this->generateWithdrawalPdf($withdrawal);

            $filename = "facture-retrait-{$withdrawal->id}.pdf";

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Retrait non trouvé ou accès non autorisé.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error downloading affiliate withdrawal PDF', [
                'user_id' => $request->user()->id,
                'withdrawal_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la génération du PDF.',
            ], 500);
        }
    }

    /**
     * Generate PDF for withdrawal invoice.
     */
    private function generateWithdrawalPdf(Withdrawal $withdrawal): string
    {
        // This is a simplified PDF generation
        // In a real application, you would use a proper PDF library like DomPDF or TCPDF

        $html = view('pdfs.withdrawal-invoice', compact('withdrawal'))->render();

        // For now, return a simple PDF content
        // You should implement proper PDF generation here
        try {
            $pdf = app('dompdf.wrapper');
            $pdf->loadHTML($html);
            return $pdf->output();
        } catch (\Exception $e) {
            // Fallback: generate a simple text-based PDF
            return $this->generateSimplePdf($withdrawal);
        }
    }

    /**
     * Generate a simple text-based PDF as fallback.
     */
    private function generateSimplePdf(Withdrawal $withdrawal): string
    {
        $content = "FACTURE DE RETRAIT\n\n";
        $content .= "Référence: {$withdrawal->id}\n";
        $content .= "Montant: {$withdrawal->amount} " . ($withdrawal->currency ?? 'MAD') . "\n";
        $content .= "Statut: {$withdrawal->status}\n";
        $content .= "Date: {$withdrawal->created_at->format('d/m/Y H:i')}\n\n";

        if ($withdrawal->items->count() > 0) {
            $content .= "COMMISSIONS INCLUSES:\n";
            foreach ($withdrawal->items as $item) {
                $content .= "- Commission #{$item->commission->id}: {$item->commission->amount} MAD\n";
            }
        }

        // This is a very basic implementation
        // In production, you should use a proper PDF library
        return $content;
    }
}
