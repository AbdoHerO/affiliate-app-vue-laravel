<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetourArticle extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'retour_articles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'retour_id',
        'commande_article_id',
        'quantite',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantite' => 'integer',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the return for this article.
     */
    public function retour(): BelongsTo
    {
        return $this->belongsTo(Retour::class, 'retour_id');
    }

    /**
     * Get the order article for this return.
     */
    public function commandeArticle(): BelongsTo
    {
        return $this->belongsTo(CommandeArticle::class, 'commande_article_id');
    }
}
