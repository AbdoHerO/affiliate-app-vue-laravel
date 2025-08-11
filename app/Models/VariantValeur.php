<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VariantValeur extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'attribut_id',
        'code',
        'libelle',
        'actif',
        'ordre',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'ordre' => 'integer',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the attribute this value belongs to
     */
    public function attribut(): BelongsTo
    {
        return $this->belongsTo(VariantAttribut::class, 'attribut_id');
    }

    /**
     * Scope to get only active values
     */
    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope to order by ordre field
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('ordre');
    }
}
