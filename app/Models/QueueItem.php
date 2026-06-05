<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One queued download or upload. Backs DownloadService / UploadService.
 *
 * Stable, queryable fields (type, status, url, ...) are columns; per-update
 * dynamic data (progress, error, and any other extras) lives in the `meta`
 * JSON column so it round-trips with its original types.
 */
class QueueItem extends Model
{
    protected $fillable = [
        'queue_id',
        'type',
        'status',
        'url',
        'filename',
        'mime_type',
        'method',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
