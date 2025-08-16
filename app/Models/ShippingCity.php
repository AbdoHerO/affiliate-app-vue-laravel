<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingCity extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

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
        'active',
        'prices',
        'meta',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'active' => 'boolean',
        'prices' => 'array',
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Scope to filter by provider
     */
    public function scopeProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to filter by active status
     */
    public function scopeActive(Builder $query, bool $active = true): Builder
    {
        return $query->where('active', $active);
    }

    /**
     * Scope to search by name or city_id
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('city_id', 'like', "%{$search}%")
              ->orWhere('ref', 'like', "%{$search}%");
        });
    }

    /**
     * Get OzonExpress cities
     */
    public static function ozonExpressCities(): Builder
    {
        return static::provider('ozonexpress');
    }

    /**
     * Find city by provider and city_id
     */
    public static function findByProviderAndCityId(string $provider, string $cityId): ?static
    {
        return static::where('provider', $provider)
                    ->where('city_id', $cityId)
                    ->first();
    }

    /**
     * Upsert city data
     */
    public static function upsertCity(array $data): static
    {
        return static::updateOrCreate(
            [
                'provider' => $data['provider'],
                'city_id' => $data['city_id'],
            ],
            $data
        );
    }

    /**
     * Get delivery price
     */
    public function getDeliveryPrice(): ?float
    {
        return $this->prices['delivered'] ?? $this->prices['delivery'] ?? null;
    }

    /**
     * Get return price
     */
    public function getReturnPrice(): ?float
    {
        return $this->prices['returned'] ?? $this->prices['return'] ?? null;
    }

    /**
     * Get refused price
     */
    public function getRefusedPrice(): ?float
    {
        return $this->prices['refused'] ?? null;
    }

    /**
     * Check if city is available for delivery
     */
    public function isAvailable(): bool
    {
        return $this->active && !$this->trashed();
    }

    /**
     * Get formatted prices for display
     */
    public function getFormattedPricesAttribute(): array
    {
        if (!$this->prices) {
            return [];
        }

        $formatted = [];

        if ($delivery = $this->getDeliveryPrice()) {
            $formatted['delivery'] = number_format($delivery, 2) . ' MAD';
        }

        if ($return = $this->getReturnPrice()) {
            $formatted['return'] = number_format($return, 2) . ' MAD';
        }

        if ($refused = $this->getRefusedPrice()) {
            $formatted['refused'] = number_format($refused, 2) . ' MAD';
        }

        return $formatted;
    }
}
