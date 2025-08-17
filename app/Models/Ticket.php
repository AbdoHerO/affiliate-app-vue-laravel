<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'subject',
        'status',
        'priority',
        'category',
        'requester_id',
        'assignee_id',
        'first_response_at',
        'resolved_at',
        'last_activity_at',
        'meta',
    ];

    protected $casts = [
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'meta' => 'array',
    ];

    protected $attributes = [
        'status' => 'open',
        'priority' => 'normal',
        'category' => 'general',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (!$ticket->last_activity_at) {
                $ticket->last_activity_at = now();
            }
        });
    }

    /**
     * Get the user who requested this ticket.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * Get the user assigned to this ticket.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Get all messages for this ticket.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('created_at');
    }

    /**
     * Get all relations for this ticket.
     */
    public function relations(): HasMany
    {
        return $this->hasMany(TicketRelation::class);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, $status)
    {
        if (is_array($status)) {
            return $query->whereIn('status', $status);
        }
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by priority.
     */
    public function scopePriority($query, $priority)
    {
        if (is_array($priority)) {
            return $query->whereIn('priority', $priority);
        }
        return $query->where('priority', $priority);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to filter by requester.
     */
    public function scopeRequester($query, $requesterId)
    {
        return $query->where('requester_id', $requesterId);
    }

    /**
     * Scope to filter by assignee.
     */
    public function scopeAssignee($query, $assigneeId)
    {
        return $query->where('assignee_id', $assigneeId);
    }

    /**
     * Scope to search in subject and message body.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('subject', 'like', "%{$search}%")
              ->orWhereHas('messages', function ($mq) use ($search) {
                  $mq->where('body', 'like', "%{$search}%");
              });
        });
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, $from, $to)
    {
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }
        return $query;
    }

    /**
     * Check if ticket is open.
     */
    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'pending', 'waiting_user', 'waiting_third_party']);
    }

    /**
     * Check if ticket is closed.
     */
    public function isClosed(): bool
    {
        return in_array($this->status, ['resolved', 'closed']);
    }

    /**
     * Update last activity timestamp.
     */
    public function updateLastActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    /**
     * Mark ticket as having first response.
     */
    public function markFirstResponse(): void
    {
        if (!$this->first_response_at) {
            $this->update(['first_response_at' => now()]);
        }
    }

    /**
     * Mark ticket as resolved.
     */
    public function markResolved(): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'last_activity_at' => now(),
        ]);
    }
}
