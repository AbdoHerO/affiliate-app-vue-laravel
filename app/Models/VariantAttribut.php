<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VariantAttribut extends Model
{
    protected $fillable = [
        'code',
        'nom',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    /**
     * Get the values for this attribute
     */
    public function valeurs(): HasMany
    {
        return $this->hasMany(VariantValeur::class, 'attribut_id');
    }

    /**
     * Get active values for this attribute
     */
    public function valeursActives(): HasMany
    {
        return $this->valeurs()->where('actif', true)->orderBy('ordre');
    }

    /**
     * Scope to get only active attributes
     */
    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
