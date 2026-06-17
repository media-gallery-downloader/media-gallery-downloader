<?php

namespace App\Services\Upload;

use App\Helpers\MimeTypeHelper;
use App\Jobs\GenerateThumbnailJob;
use App\Models\Media;
use App\Services\ThumbnailService;
use App\Services\UploadService;
use App\Support\MediaFilename;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Handles processing of uploaded video files
 */
class VideoProcessor
{
    public function __construct(
        protected ThumbnailService $thumbnailService
    ) {}

    /**
     * Process a single video file
     *
     * @param  string  $filePath  Path to the video file
     * @param  string  $originalName  Original filename
     * @param  UploadService  $uploadService  Upload service for progress updates
     * @param  string  $uploadId  Upload ID for progress tracking
     * @param  bool  $updateProgress  Whether to update progress
     * @return Media|null The created media record, or null if skipped
     */
    public function process(
        string $filePath,
        string $originalName,
        UploadService $uploadService,
        string $uploadId,
        bool $updateProgress = true
    ): ?Media {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mimeType = $this->determineMimeType($filePath, $extension);

        // Validate it's a video file
        if (! str_starts_with($mimeType, 'video/')) {
            Log::warning('Skipping non-video file', ['file' => $originalName, 'mime' => $mimeType]);

            return null;
        }

        // Build a readable, unique "<title>-<unix-seconds>.<ext>" filename.
        $displayName = pathinfo($originalName, PATHINFO_FILENAME);
        $fileName = MediaFilename::generate($displayName, time(), $extension);

        if ($updateProgress) {
            $uploadService->updateStatus($uploadId, 'processing', ['progress' => 25]);
        }

        // Move to final public location
        $finalPath = Storage::disk('public')->putFileAs('media', new File($filePath), $fileName);

        if (! $finalPath) {
            throw new \Exception("Failed to store file: {$originalName}");
        }

        if ($updateProgress) {
            $uploadService->updateStatus($uploadId, 'processing', ['progress' => 50]);
        }

        // Create media record
        $media = $this->createMediaRecord($finalPath, $fileName, $originalName, $mimeType);

        if ($updateProgress) {
            $uploadService->updateStatus($uploadId, 'processing', ['progress' => 75]);
        }

        // Generate thumbnail
        $this->generateThumbnail($media);

        if ($updateProgress) {
            $uploadService->updateStatus($uploadId, 'processing', ['progress' => 100]);
        }

        Log::info('Video file processed', [
            'original' => $originalName,
            'newFilename' => $fileName,
            'mediaId' => $media->id,
        ]);

        return $media;
    }

    /**
     * Determine the MIME type of a file
     */
    protected function determineMimeType(string $filePath, string $extension): string
    {
        $mimeType = MimeTypeHelper::getMimeTypeFromExtension($extension);

        if (empty($mimeType)) {
            $mimeType = mime_content_type($filePath);
        }

        return $mimeType;
    }

    /**
     * Create the media database record
     */
    protected function createMediaRecord(string $path, string $fileName, string $originalName, string $mimeType): Media
    {
        $fileUrl = MediaFilename::urlFor($path);
        $fileSize = Storage::disk('public')->size($path);
        $displayName = pathinfo($originalName, PATHINFO_FILENAME);

        return Media::create([
            'name' => $displayName,
            'mime_type' => $mimeType,
            'size' => $fileSize,
            'file_name' => $fileName,
            'path' => $path,
            'url' => $fileUrl,
            'source' => 'local',
        ]);
    }

    /**
     * Queue thumbnail generation if needed.
     *
     * Dispatched as a job so that importing an archive with many videos doesn't
     * run ffmpeg serially inside the upload job (one slow/hung file would stall
     * the whole import).
     */
    protected function generateThumbnail(Media $media): void
    {
        if ($media->needsThumbnail()) {
            GenerateThumbnailJob::dispatch($media)->onQueue('thumbnails');
        }
    }
}
