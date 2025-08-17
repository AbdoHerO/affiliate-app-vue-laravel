<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateTicketMessageRequest;
use App\Http\Resources\Admin\TicketMessageResource;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketMessagesController extends Controller
{
    public function __construct(
        protected TicketService $ticketService
    ) {
        // Middleware is applied in routes/api.php
    }

    /**
     * Display a listing of messages for a ticket.
     */
    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        $perPage = min($request->get('per_page', 20), 100);
        
        $messages = $ticket->messages()
            ->with(['sender', 'attachments'])
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => TicketMessageResource::collection($messages),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
                'from' => $messages->firstItem(),
                'to' => $messages->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created message.
     */
    public function store(CreateTicketMessageRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('addMessage', $ticket);

        try {
            $messageData = $request->validated();
            $messageData['sender_id'] = $request->user()->id;
            
            // Handle file attachments
            if ($request->hasFile('attachments')) {
                $messageData['attachments'] = $request->file('attachments');
            }

            $message = $this->ticketService->addMessage($ticket, $messageData);

            return response()->json([
                'success' => true,
                'message' => __('messages.message_sent_successfully'),
                'data' => new TicketMessageResource($message),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.message_send_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified message.
     */
    public function show(Ticket $ticket, TicketMessage $message): JsonResponse
    {
        Gate::authorize('view', $ticket);

        // Ensure message belongs to ticket
        if ($message->ticket_id !== $ticket->id) {
            return response()->json([
                'success' => false,
                'message' => __('messages.message_not_found'),
            ], 404);
        }

        $message->load(['sender', 'attachments']);

        return response()->json([
            'success' => true,
            'data' => new TicketMessageResource($message),
        ]);
    }

    /**
     * Remove the specified message.
     */
    public function destroy(Ticket $ticket, TicketMessage $message): JsonResponse
    {
        Gate::authorize('addMessage', $ticket); // Using same permission as adding messages

        // Ensure message belongs to ticket
        if ($message->ticket_id !== $ticket->id) {
            return response()->json([
                'success' => false,
                'message' => __('messages.message_not_found'),
            ], 404);
        }

        try {
            // Delete attachments and their files
            foreach ($message->attachments as $attachment) {
                $attachment->delete(); // This will trigger the model's deleted event
            }

            // Delete the message
            $message->delete();

            // Update ticket's last activity
            $ticket->updateLastActivity();

            return response()->json([
                'success' => true,
                'message' => __('messages.message_deleted_successfully'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.message_deletion_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
