<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReglementLigne extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'reglement_lignes';

    /**
     * The primary key for the model.
     *
     * @var array<int, string>
     */
    protected $primaryKey = ['reglement_id', 'commission_id'];

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reglement_id',
        'commission_id',
        'montant',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'montant' => 'decimal:2',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the settlement for this line.
     */
    public function reglement(): BelongsTo
    {
        return $this->belongsTo(ReglementAffilie::class, 'reglement_id');
    }

    /**
     * Get the commission for this line.
     */
    public function commission(): BelongsTo
    {
        return $this->belongsTo(CommissionAffilie::class, 'commission_id');
    }
}
