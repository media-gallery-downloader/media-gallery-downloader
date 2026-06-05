<?php

namespace App\Jobs;

use App\Models\FailedDownload;
use App\Services\Download\DirectDownloadHandler;
use App\Services\Download\YtDlpDownloadHandler;
use App\Services\DownloadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDownloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes

    public $tries = 1; // Single attempt, failures are logged for manual retry

    /** Tracks which handler was actually being used when a failure occurred. */
    protected string $attemptedMethod = 'yt-dlp';

    public function __construct(
        public string $url,
        public string $downloadId
    ) {}

    public function handle(): void
    {
        $downloadService = app(DownloadService::class);

        try {
            Log::info('Starting download job', [
                'url' => $this->url,
                'downloadId' => $this->downloadId,
            ]);

            $downloadService->updateStatus($this->downloadId, 'downloading');

            $media = $this->executeDownload($downloadService);

            Log::info('Download completed successfully', [
                'downloadId' => $this->downloadId,
                'mediaId' => $media->id,
            ]);

            $downloadService->removeFromQueue($this->downloadId);
        } catch (\Exception $e) {
            $this->handleFailure($e, $downloadService);
        }
    }

    /**
     * Execute the download using appropriate handler
     */
    protected function executeDownload(DownloadService $downloadService)
    {
        $progressCallback = function (float $percent) use ($downloadService) {
            $downloadService->updateStatus($this->downloadId, 'downloading', ['progress' => $percent]);
        };

        // Try yt-dlp first
        $ytDlpError = '';
        $ytDlpHandler = new YtDlpDownloadHandler;
        try {
            // Update method to show we're using yt-dlp
            $downloadService->updateStatus($this->downloadId, 'downloading', ['method' => 'yt-dlp']);

            return $ytDlpHandler->download($this->url, $this->downloadId, $progressCallback);
        } catch (\Exception $e) {
            // If it's a YouTube URL, we trust yt-dlp's failure and do not fallback.
            if ($this->isYouTubeUrl($this->url)) {
                throw $e;
            }

            $ytDlpError = $e->getMessage();
            // Log at warning so the real reason (e.g. "site requires login") is
            // captured in production, not hidden at info level.
            Log::warning('yt-dlp failed, trying direct download', [
                'downloadId' => $this->downloadId,
                'url' => $this->url,
                'error' => $ytDlpError,
            ]);
        }

        // Fallback to direct download (only meaningful for direct media-file URLs).
        $this->attemptedMethod = 'direct';
        $downloadService->updateStatus($this->downloadId, 'downloading', ['method' => 'direct']);
        $directHandler = new DirectDownloadHandler;
        $directHandler->setTimeout($this->timeout);

        try {
            return $directHandler->download($this->url, $this->downloadId, $progressCallback);
        } catch (\Exception $directError) {
            // For a page URL (e.g. Reddit), the direct fallback only fetches HTML
            // and fails with a misleading MIME error. yt-dlp's error is the
            // actionable one, so surface it as the primary cause.
            throw new \Exception(
                $ytDlpError.' (direct-download fallback also failed: '.$directError->getMessage().')'
            );
        }
    }

    /**
     * Check if the URL is a YouTube URL
     */
    protected function isYouTubeUrl(string $url): bool
    {
        return (bool) preg_match('/(youtube\.com|youtu\.be)/i', $url);
    }

    /**
     * Handle download failure
     */
    protected function handleFailure(\Exception $e, DownloadService $downloadService): void
    {
        Log::error('Download failed', [
            'downloadId' => $this->downloadId,
            'url' => $this->url,
            'error' => $e->getMessage(),
        ]);

        $downloadService->updateStatus($this->downloadId, 'failed', ['error' => $e->getMessage()]);

        // Log to failed downloads table for manual retry
        $this->recordFailure($e->getMessage());
    }

    /**
     * Record (or refresh) the failed-download row for this URL.
     *
     * Reuses an existing un-resolved row for the same URL instead of inserting a
     * new one on every attempt, so repeated failures/retries don't accumulate
     * duplicate rows. The method reflects the handler that was actually in use.
     */
    protected function recordFailure(string $message): void
    {
        $existing = FailedDownload::where('url', $this->url)
            ->whereIn('status', ['pending', 'retrying'])
            ->latest('id')
            ->first();

        $attributes = [
            'method' => $this->attemptedMethod,
            'error_message' => $message,
            'status' => 'pending',
            'last_attempt_at' => now(),
        ];

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        FailedDownload::create(array_merge(['url' => $this->url], $attributes));
    }

    /**
     * Handle a job failure (e.g., timeout, exception not caught)
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('Download job failed permanently', [
            'downloadId' => $this->downloadId,
            'url' => $this->url,
            'error' => $exception?->getMessage(),
        ]);

        $this->cleanupStaleTempDirectories();

        // Update status
        $downloadService = app(DownloadService::class);
        $downloadService->updateStatus($this->downloadId, 'failed', [
            'error' => $exception?->getMessage() ?? 'Unknown error',
        ]);

        // Log to failed downloads table
        $this->recordFailure($exception?->getMessage() ?? 'Job failed unexpectedly');
    }

    /**
     * Clean up any stale temp directories
     */
    protected function cleanupStaleTempDirectories(): void
    {
        $tempPatterns = [
            storage_path('app/temp/ytdlp_*'),
            storage_path('app/temp/direct_*'),
        ];

        foreach ($tempPatterns as $pattern) {
            foreach (glob($pattern, GLOB_ONLYDIR) as $dir) {
                // Only clean up directories older than 5 minutes to avoid race conditions
                if (filemtime($dir) < time() - 300) {
                    array_map('unlink', glob($dir.'/*'));
                    @rmdir($dir);
                }
            }
        }
    }
}
