<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TicketRelation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'ticket_id',
        'related_type',
        'related_id',
    ];

    /**
     * Get the ticket this relation belongs to.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the related model (polymorphic).
     */
    public function related(): MorphTo
    {
        return $this->morphTo('related', 'related_type', 'related_id');
    }

    /**
     * Scope to filter by related type.
     */
    public function scopeRelatedType($query, $type)
    {
        return $query->where('related_type', $type);
    }

    /**
     * Get human readable related type name.
     */
    public function getRelatedTypeNameAttribute(): string
    {
        $typeMap = [
            'App\Models\Commande' => 'Order',
            'App\Models\User' => 'User',
            'App\Models\Commission' => 'Commission',
            'App\Models\Withdrawal' => 'Withdrawal',
            'App\Models\Produit' => 'Product',
            'App\Models\KycDocument' => 'KYC Document',
        ];

        return $typeMap[$this->related_type] ?? class_basename($this->related_type);
    }

    /**
     * Get related entity display name.
     */
    public function getRelatedDisplayNameAttribute(): string
    {
        if (!$this->related) {
            return 'Unknown';
        }

        // Try common name fields
        $nameFields = ['nom_complet', 'name', 'subject', 'titre', 'title'];
        
        foreach ($nameFields as $field) {
            if (isset($this->related->$field)) {
                return $this->related->$field;
            }
        }

        // Fallback to ID
        return "#{$this->related->id}";
    }
}
