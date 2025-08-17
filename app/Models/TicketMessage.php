<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketMessage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'ticket_id',
        'sender_id',
        'type',
        'body',
        'attachments_count',
    ];

    protected $casts = [
        'attachments_count' => 'integer',
    ];

    protected $attributes = [
        'type' => 'public',
        'attachments_count' => 0,
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($message) {
            // Update ticket's last activity when a new message is created
            $message->ticket->updateLastActivity();

            // Mark first response if this is the first admin response
            if ($message->sender->hasRole('admin') && $message->ticket->requester_id !== $message->sender_id) {
                $message->ticket->markFirstResponse();
            }
        });
    }

    /**
     * Get the ticket this message belongs to.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the user who sent this message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get all attachments for this message.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class, 'message_id');
    }

    /**
     * Scope to filter by type.
     */
    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get public messages only.
     */
    public function scopePublic($query)
    {
        return $query->where('type', 'public');
    }

    /**
     * Scope to get internal messages only.
     */
    public function scopeInternal($query)
    {
        return $query->where('type', 'internal');
    }

    /**
     * Check if message is public.
     */
    public function isPublic(): bool
    {
        return $this->type === 'public';
    }

    /**
     * Check if message is internal.
     */
    public function isInternal(): bool
    {
        return $this->type === 'internal';
    }

    /**
     * Update attachments count.
     */
    public function updateAttachmentsCount(): void
    {
        $this->update(['attachments_count' => $this->attachments()->count()]);
    }
}
