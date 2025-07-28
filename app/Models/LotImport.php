<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LotImport extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'lots_import';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'affilie_id',
        'source',
        'fichier_nom',
        'total_lignes',
        'lignes_ok',
        'lignes_ko',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_lignes' => 'integer',
        'lignes_ok' => 'integer',
        'lignes_ko' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the affiliate that created this import lot.
     */
    public function affilie(): BelongsTo
    {
        return $this->belongsTo(ProfilAffilie::class, 'affilie_id');
    }

    /**
     * Get the import commands for this lot.
     */
    public function importCommandes(): HasMany
    {
        return $this->hasMany(ImportCommande::class, 'lot_id');
    }
}
