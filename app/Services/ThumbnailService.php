<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ThumbnailService
{
    /**
     * Generate thumbnail for video or animated GIF
     */
    public function generateThumbnail(string $sourcePath, string $mimeType): ?string
    {
        try {
            if (str_starts_with($mimeType, 'video/')) {
                return $this->generateVideoThumbnail($sourcePath);
            } elseif ($mimeType === 'image/gif') {
                return $this->generateGifThumbnail($sourcePath);
            }
        } catch (\Exception $e) {
            Log::error('Thumbnail generation failed', [
                'source' => $sourcePath,
                'mime_type' => $mimeType,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Generate thumbnail from video using ffmpeg
     */
    private function generateVideoThumbnail(string $sourcePath): ?string
    {
        $sourceFullPath = Storage::disk('public')->path($sourcePath);

        if (!file_exists($sourceFullPath)) {
            Log::warning("Source file not found for thumbnail generation: $sourceFullPath");
            return null;
        }

        // Generate thumbnail filename
        $thumbnailFilename = pathinfo($sourcePath, PATHINFO_FILENAME) . '_thumb.jpg';
        $thumbnailPath = 'thumbnails/' . $thumbnailFilename;
        $thumbnailFullPath = Storage::disk('public')->path($thumbnailPath);

        // Create thumbnails directory if it doesn't exist
        Storage::disk('public')->makeDirectory('thumbnails');

        // Use ffmpeg to extract frame at 1 second
        $process = new Process([
            'ffmpeg',
            '-i',
            $sourceFullPath,
            '-ss',
            '00:00:01',           // Seek to 1 second
            '-vframes',
            '1',             // Extract 1 frame
            '-vf',
            'scale=400:400:force_original_aspect_ratio=decrease,pad=400:400:(ow-iw)/2:(oh-ih)/2:black', // Scale and pad to 400x400
            '-q:v',
            '2',                 // High quality
            '-y',                        // Overwrite existing
            $thumbnailFullPath
        ]);

        $process->setTimeout(30);
        $process->run();

        if ($process->isSuccessful() && file_exists($thumbnailFullPath)) {
            return $thumbnailPath;
        }

        // Fallback: try without seeking (for very short videos)
        $process = new Process([
            'ffmpeg',
            '-i',
            $sourceFullPath,
            '-vframes',
            '1',
            '-vf',
            'scale=400:400:force_original_aspect_ratio=decrease,pad=400:400:(ow-iw)/2:(oh-ih)/2:black',
            '-q:v',
            '2',
            '-y',
            $thumbnailFullPath
        ]);

        $process->setTimeout(30);
        $process->run();

        if ($process->isSuccessful() && file_exists($thumbnailFullPath)) {
            return $thumbnailPath;
        }

        return null;
    }

    /**
     * Generate static thumbnail from animated GIF
     */
    private function generateGifThumbnail(string $sourcePath): ?string
    {
        $sourceFullPath = Storage::disk('public')->path($sourcePath);

        // Generate thumbnail filename
        $thumbnailFilename = pathinfo($sourcePath, PATHINFO_FILENAME) . '_thumb.jpg';
        $thumbnailPath = 'thumbnails/' . $thumbnailFilename;
        $thumbnailFullPath = Storage::disk('public')->path($thumbnailPath);

        // Create thumbnails directory if it doesn't exist
        Storage::disk('public')->makeDirectory('thumbnails');

        // Method 1: Use ImageMagick if available
        if ($this->hasImageMagick()) {
            $process = new Process([
                'convert',
                $sourceFullPath . '[0]',    // Extract first frame
                '-resize',
                '400x400',       // Resize to 400x400
                '-background',
                'black',     // Black background
                '-gravity',
                'center',       // Center the image
                '-extent',
                '400x400',       // Pad to exact size
                '-quality',
                '90',           // High quality
                $thumbnailFullPath
            ]);

            $process->setTimeout(30);
            $process->run();

            if ($process->isSuccessful() && file_exists($thumbnailFullPath)) {
                return $thumbnailPath;
            }
        }

        // Method 2: Use ffmpeg as fallback
        $process = new Process([
            'ffmpeg',
            '-i',
            $sourceFullPath,
            '-vframes',
            '1',
            '-vf',
            'scale=400:400:force_original_aspect_ratio=decrease,pad=400:400:(ow-iw)/2:(oh-ih)/2:black',
            '-q:v',
            '2',
            '-y',
            $thumbnailFullPath
        ]);

        $process->setTimeout(30);
        $process->run();

        if ($process->isSuccessful() && file_exists($thumbnailFullPath)) {
            return $thumbnailPath;
        }

        return null;
    }

    /**
     * Check if ImageMagick is available
     */
    private function hasImageMagick(): bool
    {
        $process = new Process(['convert', '-version']);
        $process->run();
        return $process->isSuccessful();
    }

    /**
     * Delete thumbnail when media is deleted
     */
    public function deleteThumbnail(?string $thumbnailPath): void
    {
        if ($thumbnailPath && Storage::disk('public')->exists($thumbnailPath)) {
            Storage::disk('public')->delete($thumbnailPath);
        }
    }
}
