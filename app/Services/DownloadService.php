<?php

namespace App\Services;

use App\Jobs\ProcessDownloadJob;
use App\Models\QueueItem;
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

        // Record the queued job (atomic single-row insert) and dispatch.
        $this->addToQueue($downloadId, $url, 'auto');

        // Dispatch the download job
        ProcessDownloadJob::dispatch($url, $downloadId)
            ->onQueue('downloads'); // Use a specific queue for downloads
    }

    public function getQueue(): array
    {
        return QueueItem::where('type', 'download')
            ->orderBy('id')
            ->get()
            ->map(fn (QueueItem $item) => array_merge([
                'id' => $item->queue_id,
                'url' => $item->url,
                'method' => $item->method,
                'status' => $item->status,
                'added_at' => $item->created_at?->toISOString(),
            ], $item->meta ?? []))
            ->all();
    }

    public function addToQueue(string $id, string $url, string $method): void
    {
        QueueItem::updateOrCreate(
            ['queue_id' => $id],
            ['type' => 'download', 'url' => $url, 'method' => $method, 'status' => 'queued'],
        );
    }

    public function updateStatus(string $id, string $status, array $extra = []): void
    {
        $item = QueueItem::where('queue_id', $id)->first();
        if (! $item) {
            return;
        }

        $item->status = $status;
        if (! empty($extra)) {
            $item->meta = array_merge($item->meta ?? [], $extra);
        }
        $item->save();
    }

    public function removeFromQueue(string $id): void
    {
        QueueItem::where('queue_id', $id)->delete();
    }

    public function clearQueue(): void
    {
        QueueItem::where('type', 'download')->delete();
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
