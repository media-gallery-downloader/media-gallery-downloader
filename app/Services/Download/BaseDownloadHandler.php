<?php

namespace App\Services\Download;

use App\Helpers\MimeTypeHelper;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Base class for download handlers providing common functionality
 */
abstract class BaseDownloadHandler implements DownloadHandlerInterface
{
    /**
     * Create a unique temporary directory
     */
    protected function createTempDirectory(string $prefix): string
    {
        $tempDir = storage_path("app/temp/{$prefix}_".uniqid());
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        return $tempDir;
    }

    /**
     * Clean up a temporary directory
     */
    protected function cleanupTempDirectory(string $tempDir): void
    {
        if (file_exists($tempDir)) {
            array_map('unlink', glob($tempDir.'/*'));
            @rmdir($tempDir);
        }
    }

    /**
     * Store a downloaded file and create a media record
     */
    protected function storeAndCreateMedia(
        string $filePath,
        string $displayName,
        string $sourceUrl,
        ?string $mimeType = null
    ): Media {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        // Get mime type
        if (empty($mimeType)) {
            $mimeType = MimeTypeHelper::getMimeTypeFromExtension($extension);
            if (empty($mimeType)) {
                $mimeType = mime_content_type($filePath);
            }
        }

        // Validate video file
        if (! str_starts_with($mimeType, 'video/')) {
            throw new \Exception("Downloaded file is not a video file (MIME: $mimeType)");
        }

        $fileSize = filesize($filePath);

        // Generate procedural filename
        $uniqueId = Str::uuid()->toString();
        $proceduralFilename = $uniqueId.'.'.$extension;
        $path = 'media/'.$proceduralFilename;

        // Store file using streaming
        Storage::disk('public')->putFileAs('media', new \Illuminate\Http\File($filePath), $proceduralFilename);

        // Create media record
        $media = Media::create([
            'name' => $displayName,
            'mime_type' => $mimeType,
            'size' => $fileSize,
            'file_name' => $proceduralFilename,
            'path' => $path,
            'url' => Storage::url($path),
            'source' => $sourceUrl,
        ]);

        // Generate thumbnail if needed
        if ($media->needsThumbnail()) {
            $thumbnailService = app(\App\Services\ThumbnailService::class);
            $thumbnailPath = $thumbnailService->generateThumbnail($path, $mimeType);

            if ($thumbnailPath) {
                $media->update(['thumbnail_path' => $thumbnailPath]);
            }
        }

        return $media;
    }

    /**
     * Find a video file in a list of files
     */
    protected function findVideoFile(array $files): ?string
    {
        foreach ($files as $file) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $mime = MimeTypeHelper::getMimeTypeFromExtension($ext);
            if (str_starts_with($mime, 'video/')) {
                return $file;
            }
        }

        return null;
    }
}
