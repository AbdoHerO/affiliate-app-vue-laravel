<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'category',
        'group',
        'description',
        'is_public',
        'is_encrypted',
        'validation_rules',
        'options',
        'sort_order',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_encrypted' => 'boolean',
        'validation_rules' => 'array',
        'options' => 'array',
        'sort_order' => 'integer',
    ];

    /**
     * Get the value attribute with proper type casting and decryption
     */
    public function getValueAttribute($value)
    {
        if ($this->is_encrypted && $value) {
            $value = Crypt::decryptString($value);
        }

        return $this->castValue($value);
    }

    /**
     * Set the value attribute with proper encryption
     */
    public function setValueAttribute($value)
    {
        $value = $this->prepareValue($value);

        if ($this->is_encrypted && $value !== null) {
            $value = Crypt::encryptString($value);
        }

        $this->attributes['value'] = $value;
    }

    /**
     * Cast value to proper type
     */
    protected function castValue($value)
    {
        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            'json', 'array' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Prepare value for storage
     */
    protected function prepareValue($value)
    {
        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            'boolean' => $value ? '1' : '0',
            'json', 'array' => json_encode($value),
            default => (string) $value,
        };
    }

    /**
     * Get setting value by key
     */
    public static function get(string $key, $default = null)
    {
        $cacheKey = "setting.{$key}";

        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set setting value by key
     */
    public static function set(string $key, $value, array $attributes = []): self
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            array_merge($attributes, ['value' => $value])
        );

        // Clear cache
        Cache::forget("setting.{$key}");
        Cache::forget("settings.category.{$setting->category}");

        return $setting;
    }

    /**
     * Get all settings by category
     */
    public static function getByCategory(string $category): array
    {
        $cacheKey = "settings.category.{$category}";

        return Cache::remember($cacheKey, 3600, function () use ($category) {
            return static::where('category', $category)
                ->orderBy('sort_order')
                ->orderBy('key')
                ->get()
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    /**
     * Get public settings (for frontend)
     */
    public static function getPublic(): array
    {
        $cacheKey = "settings.public";

        return Cache::remember($cacheKey, 3600, function () {
            return static::where('is_public', true)
                ->orderBy('category')
                ->orderBy('sort_order')
                ->orderBy('key')
                ->get()
                ->groupBy('category')
                ->map(function ($settings) {
                    return $settings->pluck('value', 'key')->toArray();
                })
                ->toArray();
        });
    }

    /**
     * Clear all settings cache
     */
    public static function clearCache(): void
    {
        $categories = static::distinct('category')->pluck('category');
        
        foreach ($categories as $category) {
            Cache::forget("settings.category.{$category}");
        }
        
        Cache::forget("settings.public");
        
        // Clear individual setting caches
        $keys = static::pluck('key');
        foreach ($keys as $key) {
            Cache::forget("setting.{$key}");
        }
    }

    /**
     * Boot method to clear cache on model events
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($setting) {
            Cache::forget("setting.{$setting->key}");
            Cache::forget("settings.category.{$setting->category}");
            Cache::forget("settings.public");
        });

        static::deleted(function ($setting) {
            Cache::forget("setting.{$setting->key}");
            Cache::forget("settings.category.{$setting->category}");
            Cache::forget("settings.public");
        });
    }
}
