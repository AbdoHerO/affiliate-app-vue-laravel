<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TicketAttachment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'message_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    protected $attributes = [
        'disk' => 'public',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($attachment) {
            // Update message attachments count
            $attachment->message->updateAttachmentsCount();
        });

        static::deleted(function ($attachment) {
            // Update message attachments count
            $attachment->message->updateAttachmentsCount();
            
            // Delete the actual file
            if (Storage::disk($attachment->disk)->exists($attachment->path)) {
                Storage::disk($attachment->disk)->delete($attachment->path);
            }
        });
    }

    /**
     * Get the message this attachment belongs to.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'message_id');
    }

    /**
     * Get the full URL for this attachment.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Get human readable file size.
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if attachment is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if attachment is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Get file extension.
     */
    public function getExtension(): string
    {
        return pathinfo($this->original_name, PATHINFO_EXTENSION);
    }
}
