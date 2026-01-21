<?php

namespace App\Services\Maintenance;

use App\Models\FailedDownload;
use App\Models\Media;
use App\Services\DownloadService;
use App\Services\ThumbnailService;
use App\Services\UpdaterService;
use Illuminate\Support\Facades\Cache;

/**
 * Handles media-related maintenance tasks like thumbnail regeneration,
 * failed download retry, and yt-dlp/Deno updates.
 */
class MediaMaintenanceService extends BaseMaintenanceService
{
    public function __construct(
        protected UpdaterService $updaterService,
        protected ThumbnailService $thumbnailService
    ) {}

    /**
     * Update yt-dlp
     */
    public function updateYtDlp(): bool
    {
        $result = $this->updaterService->checkAndUpdateYtdlp();
        Cache::put('last_ytdlp_update', now());
        $this->sendNotification('yt-dlp Update', $result ? 'yt-dlp is up to date.' : 'yt-dlp update failed.', $result);

        return $result;
    }

    /**
     * Update Deno
     */
    public function updateDeno(): bool
    {
        $result = $this->updaterService->checkAndUpdateDeno();
        Cache::put('last_deno_update', now());
        $this->sendNotification('Deno Update', $result ? 'Deno is up to date.' : 'Deno update failed.', $result);

        return $result;
    }

    /**
     * Generate thumbnails for videos/gifs that are missing them
     */
    public function regenerateThumbnails(): array
    {
        $results = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
        ];

        // Only get video/gif media items that are missing thumbnails
        $mediaItems = Media::where(function ($query) {
            $query->where('mime_type', 'like', 'video/%')
                ->orWhere('mime_type', '=', 'image/gif');
        })
            ->whereNull('thumbnail_path')
            ->get();

        foreach ($mediaItems as $media) {
            $results['processed']++;

            // Generate thumbnail
            $newThumbnail = $this->thumbnailService->generateThumbnail($media->path, $media->mime_type);

            if ($newThumbnail) {
                $media->update(['thumbnail_path' => $newThumbnail]);
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        Cache::put('last_thumbnail_regeneration', now());
        $this->sendNotification(
            'Thumbnail Regeneration',
            "Generated {$results['success']} missing thumbnails".($results['failed'] > 0 ? ", {$results['failed']} failed" : ''),
            $results['failed'] === 0
        );

        return $results;
    }

    /**
     * Retry failed downloads
     */
    public function retryFailedDownloads(): int
    {
        $retriedCount = 0;
        $downloadService = app(DownloadService::class);

        $pendingRetries = FailedDownload::pendingRetry()->take(10)->get();

        foreach ($pendingRetries as $failed) {
            $failed->markRetrying();

            try {
                $downloadService->downloadFromUrl($failed->url, uniqid('retry_'));
                $failed->markResolved();
                $retriedCount++;
            } catch (\Exception $e) {
                $failed->markFailed($e->getMessage());
            }
        }

        return $retriedCount;
    }

    /**
     * Log a failed download for retry
     */
    public function logFailedDownload(string $url, string $method, string $errorMessage): FailedDownload
    {
        return FailedDownload::create([
            'url' => $url,
            'method' => $method,
            'error_message' => $errorMessage,
            'status' => 'pending',
            'last_attempt_at' => now(),
        ]);
    }
}
