<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateTicketRequest;
use App\Http\Requests\Admin\UpdateTicketRequest;
use App\Http\Requests\Admin\AssignTicketRequest;
use App\Http\Requests\Admin\ChangeTicketStatusRequest;
use App\Http\Resources\Admin\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketsController extends Controller
{
    public function __construct(
        protected TicketService $ticketService
    ) {
        // Middleware is applied in routes/api.php
    }

    /**
     * Display a listing of tickets.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Ticket::class);

        $filters = $request->only([
            'q', 'status', 'priority', 'category',
            'requester_id', 'assignee_id', 'date_from', 'date_to',
            'activity_from', 'activity_to', 'unassigned', 'has_response', 'my_tickets',
            'sort', 'dir', 'per_page'
        ]);

        $perPage = min($request->get('per_page', 15), 100);
        $tickets = $this->ticketService->getFilteredTickets($filters, $perPage);

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
    }

    /**
     * Get ticket statistics.
     */
    public function statistics(): JsonResponse
    {
        Gate::authorize('viewAny', Ticket::class);

        $stats = $this->ticketService->getStatistics();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Store a newly created ticket.
     */
    public function store(CreateTicketRequest $request): JsonResponse
    {
        Gate::authorize('create', Ticket::class);

        try {
            $ticket = $this->ticketService->createTicket(
                $request->validated(),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => __('messages.ticket_created_successfully'),
                'data' => new TicketResource($ticket),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.ticket_creation_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified ticket.
     */
    public function show(Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        $ticket->load([
            'requester', 
            'assignee', 
            'messages.sender', 
            'messages.attachments',
            'relations'
        ]);

        return response()->json([
            'success' => true,
            'data' => new TicketResource($ticket),
        ]);
    }

    /**
     * Update the specified ticket.
     */
    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('update', $ticket);

        try {
            $ticket->update($request->validated());
            $ticket->updateLastActivity();

            return response()->json([
                'success' => true,
                'message' => __('messages.ticket_updated_successfully'),
                'data' => new TicketResource($ticket->load(['requester', 'assignee'])),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.ticket_update_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign ticket to a user.
     */
    public function assign(AssignTicketRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('assign', $ticket);

        try {
            $updatedTicket = $this->ticketService->assignTicket(
                $ticket,
                $request->validated()['assignee_id']
            );

            return response()->json([
                'success' => true,
                'message' => __('messages.ticket_assigned_successfully'),
                'data' => new TicketResource($updatedTicket),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.ticket_assignment_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Change ticket status.
     */
    public function changeStatus(ChangeTicketStatusRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('changeStatus', $ticket);

        try {
            $updatedTicket = $this->ticketService->changeStatus(
                $ticket,
                $request->validated()['status']
            );

            return response()->json([
                'success' => true,
                'message' => __('messages.ticket_status_changed_successfully'),
                'data' => new TicketResource($updatedTicket),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.ticket_status_change_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified ticket.
     */
    public function destroy(Ticket $ticket): JsonResponse
    {
        Gate::authorize('delete', $ticket);

        try {
            $this->ticketService->deleteTicket($ticket);

            return response()->json([
                'success' => true,
                'message' => __('messages.ticket_deleted_successfully'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.ticket_deletion_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk operations on tickets.
     */
    public function bulkAction(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Ticket::class);

        $request->validate([
            'action' => 'required|in:assign,status,delete',
            'ticket_ids' => 'required|array',
            'ticket_ids.*' => 'uuid|exists:tickets,id',
            'assignee_id' => 'required_if:action,assign|nullable|uuid|exists:users,id',
            'status' => 'required_if:action,status|in:open,pending,waiting_user,waiting_third_party,resolved,closed',
        ]);

        try {
            $tickets = Ticket::whereIn('id', $request->ticket_ids)->get();
            $action = $request->action;
            $updated = 0;

            foreach ($tickets as $ticket) {
                switch ($action) {
                    case 'assign':
                        if (Gate::allows('assign', $ticket)) {
                            $this->ticketService->assignTicket($ticket, $request->assignee_id);
                            $updated++;
                        }
                        break;
                    case 'status':
                        if (Gate::allows('changeStatus', $ticket)) {
                            $this->ticketService->changeStatus($ticket, $request->status);
                            $updated++;
                        }
                        break;
                    case 'delete':
                        if (Gate::allows('delete', $ticket)) {
                            $this->ticketService->deleteTicket($ticket);
                            $updated++;
                        }
                        break;
                }
            }

            return response()->json([
                'success' => true,
                'message' => __('messages.bulk_action_completed', ['count' => $updated]),
                'updated_count' => $updated,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.bulk_action_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
