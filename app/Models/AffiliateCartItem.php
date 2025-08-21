<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCartItem extends Model
{
    use HasFactory;

    protected $table = 'affiliate_cart_items';
    
    protected $fillable = [
        'user_id',
        'produit_id',
        'variante_id',
        'qty',
        'sell_price',
        'added_at',
    ];

    protected $casts = [
        'user_id' => 'string',
        'produit_id' => 'string',
        'variante_id' => 'string',
        'qty' => 'integer',
        'sell_price' => 'decimal:2',
        'added_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProduitVariante::class, 'variante_id');
    }

    public function getItemKeyAttribute(): string
    {
        return $this->produit_id . '_' . ($this->variante_id ?? 'default');
    }
}
