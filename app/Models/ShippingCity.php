<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ShippingCity extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'shipping_cities';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'provider',
        'city_id',
        'ref',
        'name',
        'prices',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'prices' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
