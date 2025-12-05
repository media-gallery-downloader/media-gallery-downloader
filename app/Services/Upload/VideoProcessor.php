<?php

namespace App\Services\Upload;

use App\Helpers\MimeTypeHelper;
use App\Models\Media;
use App\Services\ThumbnailService;
use App\Services\UploadService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        // Generate unique filename
        $uniqueId = Str::uuid()->toString();
        $fileName = $uniqueId.'.'.$extension;

        if ($updateProgress) {
            $uploadService->updateStatus($uploadId, 'processing', ['progress' => 25]);
        }

        // Move to final public location
        $finalPath = Storage::disk('public')->putFileAs('media', new \Illuminate\Http\File($filePath), $fileName);

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
        $fileUrl = Storage::url($path);
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
     * Generate thumbnail if needed
     */
    protected function generateThumbnail(Media $media): void
    {
        if ($media->needsThumbnail()) {
            $thumbnailPath = $this->thumbnailService->generateThumbnail($media->path, $media->mime_type);

            if ($thumbnailPath) {
                $media->update(['thumbnail_path' => $thumbnailPath]);
            }
        }
    }
}
