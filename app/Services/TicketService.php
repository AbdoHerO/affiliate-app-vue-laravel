<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketAttachment;
use App\Models\TicketRelation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TicketService
{
    /**
     * Create a new ticket with optional relations and first message.
     */
    public function createTicket(array $data, ?User $creator = null): Ticket
    {
        return DB::transaction(function () use ($data, $creator) {
            // Create the ticket
            $ticket = Ticket::create([
                'subject' => $data['subject'],
                'category' => $data['category'],
                'priority' => $data['priority'],
                'requester_id' => $data['requester_id'],
                'assignee_id' => $data['assignee_id'] ?? null,
                'last_activity_at' => now(),
            ]);

            // Add relations if provided
            if (!empty($data['relations'])) {
                $this->addRelations($ticket, $data['relations']);
            }

            // Add first message if provided
            if (!empty($data['first_message'])) {
                $messageData = $data['first_message'];
                $messageData['sender_id'] = $creator ? $creator->id : $data['requester_id'];
                $this->addMessage($ticket, $messageData);
            }

            return $ticket->load(['requester', 'assignee', 'messages', 'relations']);
        });
    }

    /**
     * Add a message to a ticket with optional attachments.
     */
    public function addMessage(Ticket $ticket, array $data): TicketMessage
    {
        return DB::transaction(function () use ($ticket, $data) {
            // Create the message
            $message = $ticket->messages()->create([
                'sender_id' => $data['sender_id'],
                'type' => $data['type'] ?? 'public',
                'body' => $data['body'],
            ]);

            // Handle attachments if provided
            if (!empty($data['attachments'])) {
                $this->addAttachments($message, $data['attachments']);
            }

            return $message->load(['sender', 'attachments']);
        });
    }

    /**
     * Add attachments to a message.
     */
    public function addAttachments(TicketMessage $message, array $files): void
    {
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $this->storeAttachment($message, $file);
            }
        }
    }

    /**
     * Store a single attachment.
     */
    protected function storeAttachment(TicketMessage $message, UploadedFile $file): TicketAttachment
    {
        // Generate unique filename
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        
        // Store file in tickets directory
        $path = $file->storeAs(
            "tickets/{$message->ticket_id}",
            $filename,
            'public'
        );

        // Create attachment record
        return $message->attachments()->create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    /**
     * Add relations to a ticket.
     */
    public function addRelations(Ticket $ticket, array $relations): void
    {
        foreach ($relations as $relation) {
            $ticket->relations()->create([
                'related_type' => $relation['related_type'],
                'related_id' => $relation['related_id'],
            ]);
        }
    }

    /**
     * Assign a ticket to a user.
     */
    public function assignTicket(Ticket $ticket, ?string $assigneeId): Ticket
    {
        $ticket->update([
            'assignee_id' => $assigneeId,
            'last_activity_at' => now(),
        ]);

        return $ticket->load(['assignee']);
    }

    /**
     * Change ticket status.
     */
    public function changeStatus(Ticket $ticket, string $status): Ticket
    {
        $updateData = [
            'status' => $status,
            'last_activity_at' => now(),
        ];

        // Set resolved_at when status becomes resolved
        if ($status === 'resolved' && !$ticket->resolved_at) {
            $updateData['resolved_at'] = now();
        }

        $ticket->update($updateData);

        return $ticket;
    }

    /**
     * Get ticket statistics.
     */
    public function getStatistics(): array
    {
        return [
            'open' => Ticket::status(['open', 'pending', 'waiting_user', 'waiting_third_party'])->count(),
            'resolved' => Ticket::status('resolved')->count(),
            'closed' => Ticket::status('closed')->count(),
            'total' => Ticket::count(),
            'unassigned' => Ticket::whereNull('assignee_id')->count(),
            'high_priority' => Ticket::priority(['high', 'urgent'])->count(),
        ];
    }

    /**
     * Get tickets with filters.
     */
    public function getFilteredTickets(array $filters = [], int $perPage = 15)
    {
        $query = Ticket::with(['requester', 'assignee'])
            ->withCount('messages');

        // Apply filters
        if (!empty($filters['q'])) {
            $query->search($filters['q']);
        }

        if (!empty($filters['status'])) {
            $query->status($filters['status']);
        }

        if (!empty($filters['priority'])) {
            $query->priority($filters['priority']);
        }

        if (!empty($filters['category'])) {
            $query->category($filters['category']);
        }

        if (!empty($filters['requester_id'])) {
            $query->requester($filters['requester_id']);
        }

        if (!empty($filters['assignee_id'])) {
            $query->assignee($filters['assignee_id']);
        }

        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $query->dateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);
        }

        if (!empty($filters['activity_from']) || !empty($filters['activity_to'])) {
            $query->activityDateRange($filters['activity_from'] ?? null, $filters['activity_to'] ?? null);
        }

        if (!empty($filters['unassigned']) && $filters['unassigned'] === 'true') {
            $query->unassigned();
        }

        if (!empty($filters['has_response'])) {
            if ($filters['has_response'] === 'true') {
                $query->hasResponse();
            } elseif ($filters['has_response'] === 'false') {
                $query->noResponse();
            }
        }

        if (!empty($filters['my_tickets']) && $filters['my_tickets'] === 'true') {
            $query->assignedTo(Auth::id());
        }

        // Apply sorting
        $sortField = $filters['sort'] ?? 'last_activity_at';
        $sortDirection = $filters['dir'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        return $query->paginate($perPage);
    }

    /**
     * Delete a ticket and its related data.
     */
    public function deleteTicket(Ticket $ticket): bool
    {
        return DB::transaction(function () use ($ticket) {
            // Delete attachments and their files
            foreach ($ticket->messages as $message) {
                foreach ($message->attachments as $attachment) {
                    if (Storage::disk($attachment->disk)->exists($attachment->path)) {
                        Storage::disk($attachment->disk)->delete($attachment->path);
                    }
                }
            }

            // Soft delete the ticket (cascades to messages, attachments, relations)
            return $ticket->delete();
        });
    }
}
