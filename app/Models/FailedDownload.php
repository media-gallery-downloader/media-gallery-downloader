<?php

namespace App\Models;

use App\Services\Download\DownloadOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedDownload extends Model
{
    use HasFactory;

    protected $fillable = [
        'url',
        'method',
        'error_message',
        'retry_count',
        'last_attempt_at',
        'next_retry_at',
        'status',
        'extra_args',
        'combine_args',
    ];

    protected $casts = [
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'combine_args' => 'boolean',
    ];

    /**
     * The one-off yt-dlp arguments this download was started with; rows
     * saved before those existed have none.
     */
    public function downloadOptions(): DownloadOptions
    {
        return DownloadOptions::fromArray($this->only(['extra_args', 'combine_args']));
    }

    /**
     * Scope for pending retries
     */
    public function scopePendingRetry($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    /**
     * Scope for permanently failed
     */
    public function scopePermanentlyFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Mark as retrying
     */
    public function markRetrying(): void
    {
        $this->update([
            'status' => 'retrying',
            'last_attempt_at' => now(),
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Mark as failed with exponential backoff
     */
    public function markFailed(string $errorMessage, int $maxRetries = 5): void
    {
        $newRetryCount = $this->retry_count + 1;

        if ($newRetryCount >= $maxRetries) {
            $this->update([
                'status' => 'failed',
                'error_message' => $errorMessage,
                'last_attempt_at' => now(),
                'retry_count' => $newRetryCount,
                'next_retry_at' => null,
            ]);
        } else {
            // Exponential backoff: 10min, 20min, 40min, 80min — then the 5th
            // failure marks the row permanently 'failed' (no more auto-retries).
            $delayMinutes = pow(2, $newRetryCount) * 5;

            $this->update([
                'status' => 'pending',
                'error_message' => $errorMessage,
                'last_attempt_at' => now(),
                'retry_count' => $newRetryCount,
                'next_retry_at' => now()->addMinutes($delayMinutes),
            ]);
        }
    }

    /**
     * Mark as resolved
     */
    public function markResolved(): void
    {
        $this->update([
            'status' => 'resolved',
            'next_retry_at' => null,
        ]);
    }
}
