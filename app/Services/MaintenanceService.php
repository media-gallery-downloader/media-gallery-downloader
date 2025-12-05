<?php

namespace App\Services;

use App\Jobs\ProcessDirectImportJob;
use App\Models\FailedDownload;
use App\Models\Media;
use App\Settings\MaintenanceSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MaintenanceService
{
    public function __construct(
        protected UpdaterService $updaterService,
        protected ThumbnailService $thumbnailService
    ) {}

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
     * Create database backup
     */
    public function createDatabaseBackup(): ?string
    {
        $backupDir = storage_path('app/data/backups');
        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $filename = 'backup_'.date('Y-m-d_His').'.sql';
        $filepath = $backupDir.'/'.$filename;

        $database = config('database.connections.sqlite.database');

        if (config('database.default') === 'sqlite' && $database) {
            // SQLite backup - just copy the file
            if (File::copy($database, $filepath)) {
                Cache::put('last_database_backup', now());
                $this->cleanupOldBackups();
                $this->sendNotification('Database Backup', "Backup created: {$filename}", true);

                return $filepath;
            }
        } else {
            // MySQL backup using mysqldump
            $connection = config('database.default');
            $host = config("database.connections.{$connection}.host");
            $port = config("database.connections.{$connection}.port");
            $database = config("database.connections.{$connection}.database");
            $username = config("database.connections.{$connection}.username");
            $password = config("database.connections.{$connection}.password");

            $command = sprintf(
                'mysqldump --host=%s --port=%s --user=%s --password=%s %s > %s',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($database),
                escapeshellarg($filepath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                Cache::put('last_database_backup', now());
                $this->cleanupOldBackups();
                $this->sendNotification('Database Backup', "Backup created: {$filename}", true);

                return $filepath;
            }
        }

        $this->sendNotification('Database Backup', 'Backup failed!', false);

        return null;
    }

    /**
     * Clean up old database backups
     */
    protected function cleanupOldBackups(): int
    {
        $settings = app(MaintenanceSettings::class);
        $retentionDays = $settings->database_backup_retention_days ?? 30;

        $backupDir = storage_path('app/data/backups');
        if (! File::exists($backupDir)) {
            return 0;
        }

        $cutoffTime = now()->subDays($retentionDays)->timestamp;
        $deletedCount = 0;

        foreach (File::files($backupDir) as $file) {
            if ($file->getMTime() < $cutoffTime) {
                File::delete($file->getPathname());
                $deletedCount++;
            }
        }

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

    /**
     * Regenerate all thumbnails
     */
    public function regenerateThumbnails(): array
    {
        $results = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
        ];

        $mediaItems = Media::whereIn('mime_type', function ($query) {
            $query->select('mime_type')
                ->from('media')
                ->where('mime_type', 'like', 'video/%')
                ->orWhere('mime_type', '=', 'image/gif');
        })->get();

        foreach ($mediaItems as $media) {
            $results['processed']++;

            // Delete existing thumbnail
            if ($media->thumbnail_path) {
                $this->thumbnailService->deleteThumbnail($media->thumbnail_path);
            }

            // Generate new thumbnail
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
            "Processed {$results['processed']}: {$results['success']} success, {$results['failed']} failed",
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

    /**
     * Send notification via email or webhook
     */
    protected function sendNotification(string $title, string $message, bool $success): void
    {
        try {
            $settings = app(MaintenanceSettings::class);

            if (! $settings->notifications_enabled) {
                return;
            }

            if ($success && ! $settings->notify_on_success) {
                return;
            }

            if (! $success && ! $settings->notify_on_failure) {
                return;
            }

            // Send email notification
            if ($settings->notification_email) {
                // You would implement email sending here
                // Mail::to($settings->notification_email)->send(new MaintenanceNotification($title, $message, $success));
                Log::info("Would send email to {$settings->notification_email}: {$title} - {$message}");
            }

            // Send webhook notification
            if ($settings->notification_webhook_url) {
                Http::post($settings->notification_webhook_url, [
                    'title' => $title,
                    'message' => $message,
                    'success' => $success,
                    'timestamp' => now()->toISOString(),
                    'app' => config('app.name'),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send notification: '.$e->getMessage());
        }
    }

    /**
     * Get list of database backups
     */
    public function getBackups(): array
    {
        $backupDir = storage_path('app/backups');
        if (! File::exists($backupDir)) {
            return [];
        }

        $backups = [];
        foreach (File::files($backupDir) as $file) {
            $backups[] = [
                'name' => $file->getFilename(),
                'path' => $file->getPathname(),
                'size' => $file->getSize(),
                'created_at' => \Carbon\Carbon::createFromTimestamp($file->getMTime()),
            ];
        }

        // Sort by date descending
        usort($backups, fn ($a, $b) => $b['created_at']->timestamp - $a['created_at']->timestamp);

        return $backups;
    }

    /**
     * Scan incoming directory and queue video files for import
     *
     * @return array{queued: int, skipped: int, errors: array}
     */
    public function scanAndQueueImports(): array
    {
        $incomingPath = config('mgd.import.incoming_path');
        $failedPath = config('mgd.import.failed_path');
        $batchSize = config('mgd.import.batch_size', 10);

        $results = [
            'queued' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // Ensure directories exist
        if (! File::exists($incomingPath)) {
            File::makeDirectory($incomingPath, 0755, true);
            Log::info('Created import incoming directory', ['path' => $incomingPath]);
        }

        if (! File::exists($failedPath)) {
            File::makeDirectory($failedPath, 0755, true);
            Log::info('Created import failed directory', ['path' => $failedPath]);
        }

        // Get all files in incoming directory
        if (! File::exists($incomingPath)) {
            return $results;
        }

        $files = File::files($incomingPath);

        if (empty($files)) {
            Log::info('No files found in import incoming directory');

            return $results;
        }

        Log::info('Found files for import', ['count' => count($files)]);

        $queuedCount = 0;

        foreach ($files as $file) {
            $filename = $file->getFilename();

            // Skip hidden files and log files
            if (str_starts_with($filename, '.') || str_ends_with($filename, '.log')) {
                $results['skipped']++;

                continue;
            }

            // Check if it's a video file
            if (! UploadService::isVideoFile($filename)) {
                Log::info('Skipping non-video file in import', ['file' => $filename]);
                $results['skipped']++;

                continue;
            }

            try {
                // Dispatch job with delay to spread out processing
                $delay = (int) floor($queuedCount / $batchSize) * 30; // 30 second delay per batch

                ProcessDirectImportJob::dispatch($file->getPathname(), $filename)
                    ->onQueue('imports')
                    ->delay(now()->addSeconds($delay));

                $results['queued']++;
                $queuedCount++;

                Log::info('Queued file for import', [
                    'file' => $filename,
                    'delay' => $delay,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to queue file for import', [
                    'file' => $filename,
                    'error' => $e->getMessage(),
                ]);
                $results['errors'][] = "{$filename}: {$e->getMessage()}";
            }
        }

        Cache::put('last_import_scan', now());

        $message = "Queued {$results['queued']} files, skipped {$results['skipped']}";
        if (! empty($results['errors'])) {
            $message .= ', '.count($results['errors']).' errors';
        }

        $this->sendNotification('Bulk Import Scan', $message, empty($results['errors']));

        Log::info('Import scan completed', $results);

        return $results;
    }

    /**
     * Get status information about the import directories
     *
     * @return array{incoming_count: int, failed_count: int, incoming_size: int, failed_size: int}
     */
    public function getImportStatus(): array
    {
        $incomingPath = config('mgd.import.incoming_path');
        $failedPath = config('mgd.import.failed_path');

        $status = [
            'incoming_count' => 0,
            'failed_count' => 0,
            'incoming_size' => 0,
            'failed_size' => 0,
            'incoming_path' => $incomingPath,
            'failed_path' => $failedPath,
        ];

        if (File::exists($incomingPath)) {
            $incomingFiles = File::files($incomingPath);
            $status['incoming_count'] = count(array_filter($incomingFiles, function ($file) {
                $name = $file->getFilename();

                return ! str_starts_with($name, '.') && ! str_ends_with($name, '.log');
            }));
            $status['incoming_size'] = array_sum(array_map(fn ($f) => $f->getSize(), $incomingFiles));
        }

        if (File::exists($failedPath)) {
            $failedFiles = File::files($failedPath);
            // Only count non-log files
            $status['failed_count'] = count(array_filter($failedFiles, function ($file) {
                return ! str_ends_with($file->getFilename(), '.log');
            }));
            $status['failed_size'] = array_sum(array_map(fn ($f) => $f->getSize(), $failedFiles));
        }

        return $status;
    }

    /**
     * Clear all files from the failed import directory
     */
    public function clearFailedImports(): int
    {
        $failedPath = config('mgd.import.failed_path');

        if (! File::exists($failedPath)) {
            return 0;
        }

        $files = File::files($failedPath);
        $deletedCount = 0;

        foreach ($files as $file) {
            File::delete($file->getPathname());
            $deletedCount++;
        }

        Log::info('Cleared failed imports', ['count' => $deletedCount]);

        return $deletedCount;
    }

    /**
     * Retry a specific failed import by moving it back to incoming
     */
    public function retryFailedImport(string $filename): bool
    {
        $failedPath = config('mgd.import.failed_path');
        $incomingPath = config('mgd.import.incoming_path');

        $failedFilePath = $failedPath.'/'.$filename;

        if (! File::exists($failedFilePath)) {
            return false;
        }

        // Remove the .log file if it exists
        $logFilePath = $failedFilePath.'.log';
        if (File::exists($logFilePath)) {
            File::delete($logFilePath);
        }

        // Move back to incoming
        $incomingFilePath = $incomingPath.'/'.$filename;

        // Handle duplicate filenames
        $counter = 1;
        while (File::exists($incomingFilePath)) {
            $pathInfo = pathinfo($filename);
            $incomingFilePath = $incomingPath.'/'.$pathInfo['filename'].'_retry'.$counter.'.'.$pathInfo['extension'];
            $counter++;
        }

        return File::move($failedFilePath, $incomingFilePath);
    }

    /**
     * Restore media records from a SQL backup file
     *
     * @param  string  $sqlContent  The SQL content from backup file
     * @return array ['imported' => int, 'skipped' => int, 'queued' => int]
     */
    public function restoreFromBackup(string $sqlContent): array
    {
        $imported = 0;
        $skipped = 0;
        $queued = 0;

        // Parse the SQL to extract media records
        // SQLite backup is just a copy of the database, so we need to read it differently
        // For SQLite, we'll import directly. For SQL dumps, we'd parse INSERT statements.

        // Check if this is a SQLite database file (starts with "SQLite format 3")
        if (str_starts_with($sqlContent, 'SQLite format 3')) {
            // It's a raw SQLite database - restore from it
            return $this->restoreFromSqliteBackup($sqlContent);
        }

        // Otherwise, try to parse as SQL INSERT statements
        return $this->restoreFromSqlDump($sqlContent);
    }

    /**
     * Restore from a SQLite database backup
     */
    protected function restoreFromSqliteBackup(string $dbContent): array
    {
        $imported = 0;
        $skipped = 0;
        $queued = 0;
        $duplicateRecords = [];

        // Create a temporary file for the backup database
        $tempDbPath = storage_path('app/temp/restore_'.uniqid().'.db');
        if (! File::exists(dirname($tempDbPath))) {
            File::makeDirectory(dirname($tempDbPath), 0755, true);
        }

        try {
            file_put_contents($tempDbPath, $dbContent);

            // Connect to the backup database
            $backupDb = new \PDO('sqlite:'.$tempDbPath);
            $backupDb->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Get all media records from backup
            $stmt = $backupDb->query('SELECT * FROM media');
            $backupRecords = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $noSourceRecords = [];

            foreach ($backupRecords as $record) {
                // Skip records without source URL - they can't be re-downloaded
                if (empty($record['source'])) {
                    $noSourceRecords[] = $record['name'] ?? 'Unknown';

                    continue;
                }

                // Check if record already exists (by name + size, or by source URL)
                $existing = Media::where(function ($query) use ($record) {
                    $query->where('name', $record['name'])
                        ->where('size', $record['size']);
                })->orWhere(function ($query) use ($record) {
                    if (! empty($record['source'])) {
                        $query->where('source', $record['source']);
                    }
                })->first();

                if ($existing) {
                    $duplicateRecords[] = $record['name'] ?? 'Unknown';
                    $skipped++;

                    continue;
                }

                // Create new media record
                $media = Media::create([
                    'name' => $record['name'],
                    'mime_type' => $record['mime_type'],
                    'size' => $record['size'] ?? 0,
                    'file_name' => $record['file_name'] ?? null,
                    'path' => $record['path'] ?? null,
                    'url' => $record['url'] ?? null,
                    'source' => $record['source'] ?? null,
                    'thumbnail_path' => null, // Will be regenerated if file exists
                    'created_at' => $record['created_at'] ?? now(),
                    'updated_at' => $record['updated_at'] ?? now(),
                ]);

                $imported++;

                // Check if file exists locally
                $fileExists = false;
                if (! empty($record['path']) && Storage::disk('public')->exists($record['path'])) {
                    $fileExists = true;

                    // Regenerate thumbnail if needed
                    if ($media->needsThumbnail()) {
                        $thumbnailPath = $this->thumbnailService->generateThumbnail($record['path'], $record['mime_type']);
                        if ($thumbnailPath) {
                            $media->update(['thumbnail_path' => $thumbnailPath]);
                        }
                    }
                }

                // If file doesn't exist but has source URL, queue for download
                if (! $fileExists && ! empty($record['source'])) {
                    \App\Jobs\ProcessRestoreDownloadJob::dispatch($media->id)->onQueue('imports');
                    $queued++;
                }
            }

            Log::info('Restored from SQLite backup', [
                'imported' => $imported,
                'skipped' => $skipped,
                'queued' => $queued,
                'no_source' => count($noSourceRecords),
                'duplicates' => count($duplicateRecords),
            ]);
        } finally {
            // Clean up temp file
            if (File::exists($tempDbPath)) {
                File::delete($tempDbPath);
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'queued' => $queued,
            'no_source' => $noSourceRecords,
            'duplicates' => $duplicateRecords,
        ];
    }

    /**
     * Restore from SQL dump (INSERT statements)
     */
    protected function restoreFromSqlDump(string $sqlContent): array
    {
        $imported = 0;
        $skipped = 0;
        $queued = 0;
        $noSourceRecords = [];
        $duplicateRecords = [];

        // Parse INSERT statements for the media table
        // Pattern: INSERT INTO media (columns) VALUES (values);
        // or: INSERT INTO "media" VALUES (values);
        preg_match_all(
            '/INSERT\s+INTO\s+["\']?media["\']?\s*(?:\([^)]+\))?\s*VALUES\s*\(([^;]+)\);/i',
            $sqlContent,
            $matches
        );

        if (empty($matches[1])) {
            Log::warning('No media INSERT statements found in SQL dump');

            return ['imported' => 0, 'skipped' => 0, 'queued' => 0, 'no_source' => []];
        }

        foreach ($matches[1] as $valuesStr) {
            // Parse the values - this is simplified and may need enhancement
            // for complex cases with escaped quotes, etc.
            $values = str_getcsv($valuesStr, ',', "'");

            if (count($values) < 5) {
                continue; // Skip malformed records
            }

            // Assuming standard column order: id, name, mime_type, size, file_name, path, url, source, thumbnail_path, created_at, updated_at
            $record = [
                'name' => trim($values[1] ?? '', "'\""),
                'mime_type' => trim($values[2] ?? '', "'\""),
                'size' => (int) ($values[3] ?? 0),
                'file_name' => trim($values[4] ?? '', "'\"") ?: null,
                'path' => trim($values[5] ?? '', "'\"") ?: null,
                'source' => trim($values[7] ?? '', "'\"") ?: null,
            ];

            // Skip records without source URL - they can't be re-downloaded
            if (empty($record['source'])) {
                $noSourceRecords[] = $record['name'] ?: 'Unknown';

                continue;
            }

            // Check for duplicates
            $existing = Media::where('name', $record['name'])
                ->where('size', $record['size'])
                ->first();

            if ($existing) {
                $duplicateRecords[] = $record['name'] ?: 'Unknown';
                $skipped++;

                continue;
            }

            $media = Media::create([
                'name' => $record['name'],
                'mime_type' => $record['mime_type'],
                'size' => $record['size'],
                'file_name' => $record['file_name'],
                'path' => $record['path'],
                'url' => $record['path'] ? Storage::url($record['path']) : null,
                'source' => $record['source'],
            ]);

            $imported++;

            // Check if file exists, queue download if not
            $fileExists = ! empty($record['path']) && Storage::disk('public')->exists($record['path']);

            if (! $fileExists && ! empty($record['source'])) {
                \App\Jobs\ProcessRestoreDownloadJob::dispatch($media->id)->onQueue('imports');
                $queued++;
            } elseif ($fileExists && $media->needsThumbnail()) {
                $thumbnailPath = $this->thumbnailService->generateThumbnail($record['path'], $record['mime_type']);
                if ($thumbnailPath) {
                    $media->update(['thumbnail_path' => $thumbnailPath]);
                }
            }
        }

        Log::info('Restored from SQL dump', [
            'imported' => $imported,
            'skipped' => $skipped,
            'queued' => $queued,
            'no_source' => count($noSourceRecords),
            'duplicates' => count($duplicateRecords),
        ]);

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'queued' => $queued,
            'no_source' => $noSourceRecords,
            'duplicates' => $duplicateRecords,
        ];
    }
}
