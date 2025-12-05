<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    /** @use HasFactory<\Database\Factories\MediaFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'mime_type',
        'size',
        'file_name',
        'path',
        'url',
        'source',
        'thumbnail_path',
    ];

    /**
     * Get thumbnail URL
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->thumbnail_path) {
            return Storage::url($this->thumbnail_path);
        }

        return null;
    }

    /**
     * Check if media needs a thumbnail
     */
    public function needsThumbnail(): bool
    {
        return str_starts_with($this->mime_type, 'video/') ||
            $this->mime_type === 'image/gif';
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (Media $media) {
            // Delete the main file
            if ($media->path && Storage::disk('public')->exists($media->path)) {
                Storage::disk('public')->delete($media->path);
            }

            // Delete the thumbnail
            if ($media->thumbnail_path && Storage::disk('public')->exists($media->thumbnail_path)) {
                Storage::disk('public')->delete($media->thumbnail_path);
            }
        });
    }
}
