<?php

namespace App\Http\Resources\Admin;

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
            'first_response_at' => $this->first_response_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'last_activity_at' => $this->last_activity_at->toISOString(),
            'meta' => $this->meta,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),

            // Relationships
            'requester' => $this->whenLoaded('requester', function () {
                return [
                    'id' => $this->requester->id,
                    'nom_complet' => $this->requester->nom_complet,
                    'email' => $this->requester->email,
                    'photo_profil' => $this->requester->photo_profil,
                ];
            }),

            'assignee' => $this->whenLoaded('assignee', function () {
                return $this->assignee ? [
                    'id' => $this->assignee->id,
                    'nom_complet' => $this->assignee->nom_complet,
                    'email' => $this->assignee->email,
                    'photo_profil' => $this->assignee->photo_profil,
                ] : null;
            }),

            'messages' => TicketMessageResource::collection($this->whenLoaded('messages')),

            'relations' => $this->whenLoaded('relations', function () {
                return $this->relations->map(function ($relation) {
                    return [
                        'id' => $relation->id,
                        'related_type' => $relation->related_type,
                        'related_id' => $relation->related_id,
                        'related_type_name' => $relation->related_type_name,
                        'related_display_name' => $relation->related_display_name,
                        'created_at' => $relation->created_at->toISOString(),
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
}
