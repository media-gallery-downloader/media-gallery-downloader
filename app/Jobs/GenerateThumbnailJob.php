<?php

namespace App\Jobs;

use App\Models\Media;
use App\Services\ThumbnailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Generates a media item's thumbnail off the critical download/upload path so a
 * slow or failing ffmpeg run can't stall (or fail) the whole download, or an
 * entire archive import.
 */
class GenerateThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    public $tries = 1; // Thumbnails are non-critical; regenerate via maintenance if needed.

    public function __construct(public Media $media) {}

    public function handle(ThumbnailService $thumbnailService): void
    {
        $media = $this->media->fresh();

        if (! $media || ! $media->needsThumbnail() || $media->thumbnail_path) {
            return;
        }

        $thumbnailPath = $thumbnailService->generateThumbnail($media->path, $media->mime_type);

        if ($thumbnailPath) {
            $media->update(['thumbnail_path' => $thumbnailPath]);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::warning('Thumbnail generation failed', [
            'mediaId' => $this->media->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
