<?php

namespace App\Services\Maintenance;

use App\Models\Media;
use App\Settings\MaintenanceSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Handles storage cleanup operations including orphaned files,
 * temp files, and old media cleanup.
 */
class CleanupService extends BaseMaintenanceService
{
    /**
     * Remove duplicate media files
     */
    public function removeDuplicates(): int
    {
        $duplicates = Media::select('name', 'size')
            ->groupBy('name', 'size')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $deletedCount = 0;

        foreach ($duplicates as $duplicate) {
            $records = Media::where('name', $duplicate->name)
                ->where('size', $duplicate->size)
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            // Skip the first one (keep it)
            $toDelete = $records->skip(1);

            foreach ($toDelete as $record) {
                $record->delete();
                $deletedCount++;
            }
        }

        Cache::put('last_duplicate_removal', now());
        $this->sendNotification('Duplicate Removal', "Removed {$deletedCount} duplicate files.", $deletedCount > 0);

        return $deletedCount;
    }

    /**
     * Clean up orphaned files from storage
     */
    public function cleanupOrphanedFiles(): int
    {
        $deletedCount = 0;
        $disk = Storage::disk('public');

        // Get all media file paths from database
        $dbPaths = Media::pluck('path')->filter()->toArray();
        $dbThumbnails = Media::pluck('thumbnail_path')->filter()->toArray();
        $knownPaths = array_merge($dbPaths, $dbThumbnails);

        // Scan media directory
        $files = $disk->allFiles('media');
        foreach ($files as $file) {
            if (! in_array($file, $knownPaths)) {
                $disk->delete($file);
                $deletedCount++;
                Log::info("Deleted orphaned file: {$file}");
            }
        }

        // Scan thumbnails directory
        $thumbnails = $disk->allFiles('thumbnails');
        foreach ($thumbnails as $thumbnail) {
            if (! in_array($thumbnail, $knownPaths)) {
                $disk->delete($thumbnail);
                $deletedCount++;
                Log::info("Deleted orphaned thumbnail: {$thumbnail}");
            }
        }

        // Also clean up stale temp files
        $tempCleaned = $this->cleanupTempFiles();
        $deletedCount += $tempCleaned;

        Cache::put('last_storage_cleanup', now());
        $this->sendNotification('Storage Cleanup', "Removed {$deletedCount} orphaned files.", true);

        return $deletedCount;
    }

    /**
     * Clean up stale temporary upload/download files older than specified time
     */
    public function cleanupTempFiles(int $maxAgeMinutes = 60): int
    {
        $tempPath = storage_path('app/temp');
        $deletedCount = 0;

        if (! File::exists($tempPath)) {
            return 0;
        }

        $cutoffTime = now()->subMinutes($maxAgeMinutes)->timestamp;

        // Clean up regular temp files
        foreach (File::files($tempPath) as $file) {
            if ($file->getMTime() < $cutoffTime) {
                File::delete($file->getPathname());
                $deletedCount++;
                Log::info("Deleted stale temp file: {$file->getFilename()}");
            }
        }

        // Clean up temp directories (from yt-dlp and direct downloads)
        foreach (File::directories($tempPath) as $dir) {
            $dirTime = filemtime($dir);
            if ($dirTime < $cutoffTime) {
                // Delete all files in the directory first
                foreach (File::files($dir) as $file) {
                    File::delete($file->getPathname());
                    $deletedCount++;
                }
                // Then remove the directory
                File::deleteDirectory($dir);
                Log::info('Deleted stale temp directory: '.basename($dir));
            }
        }

        if ($deletedCount > 0) {
            Log::info("Cleaned up {$deletedCount} stale temp files/directories");
        }

        return $deletedCount;
    }

    /**
     * Clean up old files based on age
     */
    public function cleanupOldFiles(?int $daysOld = null): int
    {
        if ($daysOld === null) {
            $settings = app(MaintenanceSettings::class);
            $daysOld = $settings->storage_cleanup_days_old ?? 90;
        }

        if ($daysOld <= 0) {
            return 0;
        }

        $cutoffDate = now()->subDays($daysOld);
        $deletedCount = 0;

        $oldMedia = Media::where('created_at', '<', $cutoffDate)->get();

        foreach ($oldMedia as $media) {
            $media->delete();
            $deletedCount++;
        }

        Log::info("Cleaned up {$deletedCount} files older than {$daysOld} days");

        return $deletedCount;
    }

    /**
     * Rotate log files
     */
    public function rotateLogs(): int
    {
        $settings = app(MaintenanceSettings::class);
        $retentionDays = $settings->log_retention_days ?? 14;

        $logDir = storage_path('logs');
        if (! File::exists($logDir)) {
            return 0;
        }

        $cutoffTime = now()->subDays($retentionDays)->timestamp;
        $deletedCount = 0;

        foreach (File::files($logDir) as $file) {
            if ($file->getExtension() === 'log' && $file->getMTime() < $cutoffTime) {
                File::delete($file->getPathname());
                $deletedCount++;
                Log::info('Deleted old log file: '.$file->getFilename());
            }
        }

        Cache::put('last_log_rotation', now());

        return $deletedCount;
    }
}
