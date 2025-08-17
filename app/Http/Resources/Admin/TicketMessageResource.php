<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'type' => $this->type,
            'body' => $this->body,
            'attachments_count' => $this->attachments_count,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Sender information
            'sender' => $this->whenLoaded('sender', function () {
                return [
                    'id' => $this->sender->id,
                    'nom_complet' => $this->sender->nom_complet,
                    'email' => $this->sender->email,
                    'photo_profil' => $this->sender->photo_profil,
                    'roles' => $this->sender->roles->pluck('name'),
                ];
            }),

            // Attachments
            'attachments' => $this->whenLoaded('attachments', function () {
                return $this->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'original_name' => $attachment->original_name,
                        'mime_type' => $attachment->mime_type,
                        'size' => $attachment->size,
                        'human_size' => $attachment->human_size,
                        'url' => $attachment->url,
                        'is_image' => $attachment->isImage(),
                        'is_pdf' => $attachment->isPdf(),
                        'extension' => $attachment->getExtension(),
                        'created_at' => $attachment->created_at->toISOString(),
                    ];
                });
            }),

            // Computed fields
            'is_public' => $this->isPublic(),
            'is_internal' => $this->isInternal(),
            'has_attachments' => $this->attachments_count > 0,
            'time_ago' => $this->created_at->diffForHumans(),
        ];
    }
}
