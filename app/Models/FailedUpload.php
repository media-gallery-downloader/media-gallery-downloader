<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'mime_type',
        'error_message',
        'retry_count',
        'last_attempt_at',
        'status',
    ];

    protected $casts = [
        'last_attempt_at' => 'datetime',
    ];

    /**
     * Scope for pending
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for permanently failed
     */
    public function scopePermanentlyFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Mark as failed
     */
    public function markFailed(string $error): void
    {
        $maxRetries = config('mgd.max_retries', 3);

        $this->update([
            'status' => $this->retry_count >= $maxRetries ? 'failed' : 'pending',
            'error_message' => $error,
            'last_attempt_at' => now(),
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Mark as resolved
     */
    public function markResolved(): void
    {
        $this->update(['status' => 'resolved']);
    }

    /**
     * Create a failed upload record
     */
    public static function createFromUpload(string $filename, string $mimeType, string $error): self
    {
        return static::create([
            'filename' => $filename,
            'mime_type' => $mimeType,
            'error_message' => $error,
            'retry_count' => 1,
            'last_attempt_at' => now(),
            'status' => 'pending',
        ]);
    }
}
