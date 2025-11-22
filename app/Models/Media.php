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
}
