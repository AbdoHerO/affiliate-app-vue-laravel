<?php

namespace App\Http\Resources\Affiliate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'status' => $this->status,
            'priority' => $this->priority,
            'category' => $this->category,
            'first_response_at' => $this->first_response_at?->format('Y-m-d H:i:s'),
            'resolved_at' => $this->resolved_at?->format('Y-m-d H:i:s'),
            'last_activity_at' => $this->last_activity_at->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),

            // Status badge info for UI
            'status_badge' => $this->getStatusBadge(),
            'priority_badge' => $this->getPriorityBadge(),
            'category_label' => $this->getCategoryLabel(),

            // Relationships
            'requester' => $this->whenLoaded('requester', function () {
                return [
                    'id' => $this->requester->id,
                    'nom_complet' => $this->requester->nom_complet,
                    'email' => $this->requester->email,
                ];
            }),

            'assignee' => $this->whenLoaded('assignee', function () {
                return $this->assignee ? [
                    'id' => $this->assignee->id,
                    'nom_complet' => $this->assignee->nom_complet,
                    'email' => $this->assignee->email,
                ] : null;
            }),

            'messages' => $this->whenLoaded('messages', function () {
                return $this->messages->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'ticket_id' => $message->ticket_id,
                        'sender_id' => $message->sender_id,
                        'type' => $message->type,
                        'body' => $message->body,
                        'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                        'updated_at' => $message->updated_at->format('Y-m-d H:i:s'),
                        'sender' => $message->relationLoaded('sender') ? [
                            'id' => $message->sender->id,
                            'nom_complet' => $message->sender->nom_complet,
                            'email' => $message->sender->email,
                        ] : null,
                        'attachments' => $message->relationLoaded('attachments') ? 
                            $message->attachments->map(function ($attachment) {
                                return [
                                    'id' => $attachment->id,
                                    'original_name' => $attachment->original_name,
                                    'path' => $attachment->path,
                                    'size' => $attachment->size,
                                    'mime_type' => $attachment->mime_type,
                                    'created_at' => $attachment->created_at->format('Y-m-d H:i:s'),
                                    'download_url' => route('affiliate.tickets.attachments.download', $attachment->id),
                                    'url' => $attachment->url, // Direct storage URL like admin panel
                                ];
                            }) : [],
                    ];
                });
            }),

            // Computed fields
            'messages_count' => $this->whenCounted('messages'),
            'is_open' => $this->isOpen(),
            'is_closed' => $this->isClosed(),
            'has_first_response' => !is_null($this->first_response_at),
            'is_resolved' => !is_null($this->resolved_at),

            // Time calculations
            'age_in_hours' => $this->created_at->diffInHours(now()),
            'last_activity_hours_ago' => $this->last_activity_at->diffInHours(now()),
            'response_time_hours' => $this->first_response_at 
                ? $this->created_at->diffInHours($this->first_response_at)
                : null,
            'resolution_time_hours' => $this->resolved_at 
                ? $this->created_at->diffInHours($this->resolved_at)
                : null,
        ];
    }

    /**
     * Get status badge information for UI display.
     */
    private function getStatusBadge(): array
    {
        $statusMap = [
            'open' => ['color' => 'success', 'text' => 'Ouvert'],
            'pending' => ['color' => 'warning', 'text' => 'En attente'],
            'waiting_user' => ['color' => 'info', 'text' => 'En attente de votre réponse'],
            'waiting_third_party' => ['color' => 'secondary', 'text' => 'En attente tiers'],
            'resolved' => ['color' => 'primary', 'text' => 'Résolu'],
            'closed' => ['color' => 'default', 'text' => 'Fermé'],
        ];

        return $statusMap[$this->status] ?? ['color' => 'default', 'text' => $this->status];
    }

    /**
     * Get priority badge information for UI display.
     */
    private function getPriorityBadge(): array
    {
        $priorityMap = [
            'low' => ['color' => 'success', 'text' => 'Faible'],
            'normal' => ['color' => 'info', 'text' => 'Normal'],
            'high' => ['color' => 'warning', 'text' => 'Élevée'],
            'urgent' => ['color' => 'error', 'text' => 'Urgent'],
        ];

        return $priorityMap[$this->priority] ?? ['color' => 'default', 'text' => $this->priority];
    }

    /**
     * Get category label for UI display.
     */
    private function getCategoryLabel(): string
    {
        $categoryMap = [
            'general' => 'Général',
            'orders' => 'Commandes',
            'payments' => 'Paiements',
            'commissions' => 'Commissions',
            'kyc' => 'KYC',
            'technical' => 'Technique',
            'other' => 'Autre',
        ];

        return $categoryMap[$this->category] ?? $this->category;
    }
}
