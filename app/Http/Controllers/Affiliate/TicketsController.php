<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TicketResource;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TicketsController extends Controller
{
    public function __construct(
        protected TicketService $ticketService
    ) {}

    /**
     * Display a listing of tickets for the authenticated affiliate.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Ensure user is an approved affiliate
            if (!$user->isApprovedAffiliate()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only approved affiliates can view tickets.',
                ], 403);
            }

            $query = Ticket::with(['requester:id,nom_complet,email'])
                ->where('requester_id', $user->id); // Scope to current affiliate only

            // Apply filters
            if ($request->filled('q')) {
                $search = $request->get('q');
                $query->where(function ($q) use ($search) {
                    $q->where('subject', 'like', "%{$search}%")
                      ->orWhere('id', 'like', "%{$search}%");
                });
            }

            if ($request->filled('status')) {
                $statuses = is_array($request->status) ? $request->status : [$request->status];
                $query->whereIn('status', $statuses);
            }

            if ($request->filled('priority')) {
                $priorities = is_array($request->priority) ? $request->priority : [$request->priority];
                $query->whereIn('priority', $priorities);
            }

            if ($request->filled('category')) {
                $categories = is_array($request->category) ? $request->category : [$request->category];
                $query->whereIn('category', $categories);
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
            $sortBy = $request->get('sort', 'last_activity_at');
            $sortDir = $request->get('dir', 'desc');
            $query->orderBy($sortBy, $sortDir);

            // Paginate
            $perPage = min($request->get('per_page', 15), 100);
            $tickets = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => TicketResource::collection($tickets),
                'pagination' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'total' => $tickets->total(),
                    'from' => $tickets->firstItem(),
                    'to' => $tickets->lastItem(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching affiliate tickets', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'filters' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des tickets.',
            ], 500);
        }
    }

    /**
     * Store a newly created ticket.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Ensure user is an approved affiliate
            if (!$user->isApprovedAffiliate()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only approved affiliates can create tickets.',
                ], 403);
            }

            // Validate request
            $validated = $request->validate([
                'subject' => 'required|string|max:255',
                'category' => 'required|string|in:general,orders,payments,commissions,kyc,technical,other',
                'priority' => 'required|string|in:low,normal,high,urgent',
                'message' => 'required|string|max:5000',
                'attachments' => 'nullable|array|max:5',
                'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,txt',
            ]);

            DB::beginTransaction();

            // Create ticket
            $ticket = Ticket::create([
                'subject' => $validated['subject'],
                'category' => $validated['category'],
                'priority' => $validated['priority'],
                'status' => 'open',
                'requester_id' => $user->id,
                'last_activity_at' => now(),
            ]);

            // Create initial message
            $message = TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'type' => 'public',
                'body' => $validated['message'],
            ]);

            // Handle attachments if any
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('ticket-attachments', 'public');
                    $message->attachments()->create([
                        'filename' => $file->getClientOriginalName(),
                        'path' => $path,
                        'size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket créé avec succès.',
                'data' => new TicketResource($ticket->load(['requester', 'messages.user', 'messages.attachments'])),
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Données invalides.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating affiliate ticket', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création du ticket.',
            ], 500);
        }
    }

    /**
     * Display the specified ticket.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Ensure user is an approved affiliate
            if (!$user->isApprovedAffiliate()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only approved affiliates can view tickets.',
                ], 403);
            }

            $ticket = Ticket::with([
                'requester:id,nom_complet,email',
                'assignee:id,nom_complet,email',
                'messages' => function ($query) {
                    $query->where('type', 'public') // Hide internal admin notes
                          ->with(['sender:id,nom_complet,email', 'attachments'])
                          ->orderBy('created_at', 'asc');
                }
            ])
            ->where('requester_id', $user->id) // Ensure ownership
            ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new TicketResource($ticket),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket non trouvé ou accès non autorisé.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error fetching affiliate ticket', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'ticket_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération du ticket.',
            ], 500);
        }
    }

    /**
     * Add a message to the specified ticket.
     */
    public function addMessage(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Ensure user is an approved affiliate
            if (!$user->isApprovedAffiliate()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only approved affiliates can add messages.',
                ], 403);
            }

            // Validate request
            $validated = $request->validate([
                'message' => 'required|string|max:5000',
                'attachments' => 'nullable|array|max:5',
                'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,txt',
            ]);

            $ticket = Ticket::where('requester_id', $user->id) // Ensure ownership
                           ->findOrFail($id);

            // Check if ticket is closed
            if ($ticket->status === 'closed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible d\'ajouter un message à un ticket fermé.',
                ], 422);
            }

            DB::beginTransaction();

            // Create message
            $message = TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'type' => 'public',
                'body' => $validated['message'],
            ]);

            // Handle attachments if any
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('ticket-attachments', 'public');
                    $message->attachments()->create([
                        'filename' => $file->getClientOriginalName(),
                        'path' => $path,
                        'size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                    ]);
                }
            }

            // Update ticket activity
            $ticket->update([
                'last_activity_at' => now(),
                'status' => $ticket->status === 'waiting_user' ? 'pending' : $ticket->status,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Message ajouté avec succès.',
                'data' => $message->load(['sender:id,nom_complet,email', 'attachments']),
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Données invalides.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket non trouvé ou accès non autorisé.',
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error adding message to affiliate ticket', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'ticket_id' => $id,
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'ajout du message.',
            ], 500);
        }
    }

    /**
     * Update the status of the specified ticket.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Ensure user is an approved affiliate
            if (!$user->isApprovedAffiliate()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only approved affiliates can update ticket status.',
                ], 403);
            }

            // Validate request
            $validated = $request->validate([
                'status' => 'required|string|in:open,closed',
            ]);

            $ticket = Ticket::where('requester_id', $user->id) // Ensure ownership
                           ->findOrFail($id);

            $oldStatus = $ticket->status;
            $newStatus = $validated['status'];

            // Update ticket status
            $ticket->update([
                'status' => $newStatus,
                'last_activity_at' => now(),
                'resolved_at' => $newStatus === 'closed' ? now() : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => $newStatus === 'closed' ? 'Ticket fermé avec succès.' : 'Ticket rouvert avec succès.',
                'data' => new TicketResource($ticket->load(['requester', 'assignee'])),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket non trouvé ou accès non autorisé.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error updating affiliate ticket status', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'ticket_id' => $id,
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour du statut.',
            ], 500);
        }
    }
}
