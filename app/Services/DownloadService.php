<?php

namespace App\Services;

use App\Jobs\ProcessDownloadJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class DownloadService
{
    /**
     * Add a URL to the download queue and dispatch the job
     */
    public function downloadFromUrl(string $url, string $downloadId): void
    {
        Log::info('Adding download to queue', [
            'url' => $url,
            'downloadId' => $downloadId,
        ]);

        // Add to Redis queue
        $this->addToQueue($downloadId, $url, $this->getDownloadMethod($url));

        // Dispatch the download job
        ProcessDownloadJob::dispatch($url, $downloadId)
            ->onQueue('downloads'); // Use a specific queue for downloads
    }

    public function getQueue(): array
    {
        return Cache::get('download_queue', []);
    }

    public function addToQueue(string $id, string $url, string $method): void
    {
        $queue = $this->getQueue();
        $queue[] = [
            'id' => $id,
            'url' => $url,
            'method' => $method,
            'status' => 'queued',
            'added_at' => now()->toISOString(),
        ];
        Cache::put('download_queue', $queue);
    }

    public function updateStatus(string $id, string $status, array $extra = []): void
    {
        $queue = $this->getQueue();
        foreach ($queue as &$item) {
            if ($item['id'] === $id) {
                $item['status'] = $status;
                $item = array_merge($item, $extra);
                break;
            }
        }
        Cache::put('download_queue', $queue);
    }

    public function removeFromQueue(string $id): void
    {
        $queue = $this->getQueue();
        $queue = array_filter($queue, fn ($item) => $item['id'] !== $id);
        Cache::put('download_queue', array_values($queue));
    }

    public function clearQueue(): void
    {
        Cache::forget('download_queue');
    }

    /**
     * Validate if a URL can be downloaded
     */
    public function validateUrl(string $url): bool
    {
        // Basic URL validation
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Check if it's a supported scheme
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! in_array($scheme, ['http', 'https'])) {
            return false;
        }

        return true;
    }

    /**
     * Check if yt-dlp supports this URL (without downloading)
     */
    public function canUseYtdlp(string $url): bool
    {
        try {
            $process = new Process([
                'yt-dlp',
                '--simulate',
                '--quiet',
                $url,
            ]);

            $process->setTimeout(10); // Short timeout for validation
            $process->run();

            return $process->isSuccessful();
        } catch (\Exception $e) {
            Log::debug('yt-dlp validation failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get estimated download method for a URL
     */
    public function getDownloadMethod(string $url): string
    {
        if ($this->canUseYtdlp($url)) {
            return 'yt-dlp';
        }

        return 'direct';
    }
}
