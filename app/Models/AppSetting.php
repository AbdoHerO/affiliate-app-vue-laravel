<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class AppSetting extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'app_settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'is_encrypted',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_encrypted' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get a setting value by key
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = "app_setting_{$key}";

        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            if (!$setting) {
                return $default;
            }

            try {
                $value = $setting->is_encrypted && $setting->value
                    ? Crypt::decryptString($setting->value)
                    : $setting->value;

                return static::castValue($value, $setting->type);
            } catch (\Exception $e) {
                // If decryption fails, return the raw value or default
                return $setting->value ?: $default;
            }
        });
    }

    /**
     * Set a setting value by key
     */
    public static function set(string $key, mixed $value, string $type = 'string', bool $isEncrypted = false, ?string $description = null): void
    {
        $stringValue = static::valueToString($value, $type);
        
        if ($isEncrypted) {
            $stringValue = Crypt::encryptString($stringValue);
        }
        
        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $stringValue,
                'type' => $type,
                'is_encrypted' => $isEncrypted,
                'description' => $description,
            ]
        );
        
        // Clear cache
        Cache::forget("app_setting_{$key}");
    }

    /**
     * Delete a setting by key
     */
    public static function forget(string $key): bool
    {
        Cache::forget("app_setting_{$key}");
        
        return static::where('key', $key)->delete() > 0;
    }

    /**
     * Cast value to appropriate type
     */
    protected static function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Convert value to string for storage
     */
    protected static function valueToString(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };
    }

    /**
     * Get multiple settings by keys
     */
    public static function getMultiple(array $keys): array
    {
        $result = [];
        
        foreach ($keys as $key) {
            $result[$key] = static::get($key);
        }
        
        return $result;
    }

    /**
     * Set multiple settings at once
     */
    public static function setMultiple(array $settings): void
    {
        foreach ($settings as $key => $config) {
            if (is_array($config)) {
                static::set(
                    $key,
                    $config['value'],
                    $config['type'] ?? 'string',
                    $config['is_encrypted'] ?? false,
                    $config['description'] ?? null
                );
            } else {
                static::set($key, $config);
            }
        }
    }
}
