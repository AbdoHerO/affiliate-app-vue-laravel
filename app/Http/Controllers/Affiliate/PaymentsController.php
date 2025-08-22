<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Http\Resources\Affiliate\CommissionResource;
use App\Http\Resources\Affiliate\WithdrawalResource;
use App\Models\CommissionAffilie;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Barryvdh\DomPDF\Facade\Pdf;
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

            $query = Withdrawal::with(['items'])
                ->where('user_id', $user->id); // Scope to current affiliate only

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

            $withdrawal = Withdrawal::with([
                'items.commission:id,amount,status,type,created_at,commande_id,commande_article_id',
                'items.commission.commande:id,statut,total_ttc,created_at',
                'items.commission.commandeArticle.produit:id,titre'
            ])
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

            // Generate PDF using DomPDF
            $pdf = $this->generateWithdrawalPdf($withdrawal);

            $filename = "facture-retrait-{$withdrawal->id}.pdf";

            // Return the PDF as a direct download response
            return response()->streamDownload(function () use ($pdf) {
                echo $pdf;
            }, $filename, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET',
                'Access-Control-Allow-Headers' => 'Authorization, Content-Type, X-Requested-With',
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
                'trace' => $e->getTraceAsString(),
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
        Log::info('Generating PDF for withdrawal using DomPDF', ['withdrawal_id' => $withdrawal->id]);

        try {
            // Use DomPDF to generate proper PDF
            $pdf = Pdf::loadView('pdfs.withdrawal-invoice', [
                'withdrawal' => $withdrawal,
            ]);

            // Set PDF options for better compatibility
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
            ]);

            return $pdf->output();

        } catch (\Exception $e) {
            Log::error('DomPDF generation failed, using fallback', [
                'withdrawal_id' => $withdrawal->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback to simple PDF if DomPDF fails
            return $this->generateSimplePdf($withdrawal);
        }
    }

    /**
     * Generate a simple text-based PDF as fallback.
     */
    private function generateSimplePdf(Withdrawal $withdrawal): string
    {
        Log::info('Generating simple PDF for withdrawal', ['withdrawal_id' => $withdrawal->id]);

        // Create a basic PDF structure using FPDF-like approach
        $content = "%PDF-1.4\n";
        $content .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $content .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $content .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n";

        // Content stream
        $textContent = "FACTURE DE RETRAIT\\n\\n";
        $textContent .= "Reference: {$withdrawal->id}\\n";
        $textContent .= "Montant: " . number_format($withdrawal->amount, 2) . " " . ($withdrawal->currency ?? 'MAD') . "\\n";
        $textContent .= "Statut: {$withdrawal->status}\\n";
        $textContent .= "Date: {$withdrawal->created_at->format('d/m/Y H:i')}\\n\\n";

        if ($withdrawal->items && $withdrawal->items->count() > 0) {
            $textContent .= "COMMISSIONS INCLUSES:\\n";
            foreach ($withdrawal->items as $item) {
                if ($item->commission) {
                    $textContent .= "- Commission #{$item->commission->id}: " . number_format($item->commission->amount, 2) . " MAD\\n";
                }
            }
        }

        $streamLength = strlen($textContent) + 100; // Approximate
        $content .= "4 0 obj\n<< /Length {$streamLength} >>\nstream\n";
        $content .= "BT\n/F1 12 Tf\n50 750 Td\n({$textContent}) Tj\nET\n";
        $content .= "endstream\nendobj\n";

        $content .= "xref\n0 5\n0000000000 65535 f \n";
        $content .= "0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000207 00000 n \n";
        $content .= "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n" . strlen($content) . "\n%%EOF";

        return $content;
    }
}
