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
        $ytDlpHandler = new YtDlpDownloadHandler;
        try {
            return $ytDlpHandler->download($this->url, $this->downloadId, $progressCallback);
        } catch (\Exception $e) {
            // If it's a YouTube URL, we trust yt-dlp's failure and do not fallback.
            if ($this->isYouTubeUrl($this->url)) {
                throw $e;
            }

            Log::info('yt-dlp failed, trying direct download', ['error' => $e->getMessage()]);
        }

        // Fallback to direct download
        $directHandler = new DirectDownloadHandler;
        $directHandler->setTimeout($this->timeout);

        return $directHandler->download($this->url, $this->downloadId, $progressCallback);
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

        // Log to failed downloads table for retry
        FailedDownload::create([
            'url' => $this->url,
            'method' => 'yt-dlp',
            'error_message' => $e->getMessage(),
            'status' => 'pending',
            'last_attempt_at' => now(),
        ]);
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
        FailedDownload::create([
            'url' => $this->url,
            'method' => 'yt-dlp',
            'error_message' => $exception?->getMessage() ?? 'Job failed unexpectedly',
            'status' => 'pending',
            'last_attempt_at' => now(),
        ]);
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
