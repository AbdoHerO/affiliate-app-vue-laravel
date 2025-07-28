<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OffreVisibilite extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'offre_visibilite';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'offre_id',
        'mode',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the offer for this visibility setting.
     */
    public function offre(): BelongsTo
    {
        return $this->belongsTo(Offre::class, 'offre_id');
    }

    /**
     * Get the affiliate visibility rules.
     */
    public function affilies(): HasMany
    {
        return $this->hasMany(OffreVisibiliteAffilie::class, 'offre_id', 'offre_id');
    }

    /**
     * Get the tier visibility rules.
     */
    public function gammes(): HasMany
    {
        return $this->hasMany(OffreVisibiliteGamme::class, 'offre_id', 'offre_id');
    }
}
