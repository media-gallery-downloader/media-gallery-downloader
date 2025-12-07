<?php

namespace App\Services\Maintenance;

use App\Jobs\ProcessDirectImportJob;
use App\Services\UploadService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Handles bulk import operations from the incoming directory
 */
class ImportService extends BaseMaintenanceService
{
    protected const CACHE_KEY = 'import_queue';

    /**
     * Get the import queue
     */
    public function getQueue(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }

    /**
     * Add an item to the import queue
     */
    public function addToQueue(string $id, string $path, string $filename): void
    {
        $queue = $this->getQueue();
        $queue[] = [
            'id' => $id,
            'path' => $path,
            'filename' => $filename,
            'status' => 'queued',
            'added_at' => now()->toISOString(),
        ];
        Cache::put(self::CACHE_KEY, $queue);
    }

    /**
     * Update the status of a queue item
     */
    public function updateStatus(string $id, string $status, array $extra = []): void
    {
        $queue = $this->getQueue();
        foreach ($queue as &$item) {
            if ($item['id'] === $id) {
                $item['status'] = $status;
                $item = array_merge($item, $extra);
                break;
            }
        }
        Cache::put(self::CACHE_KEY, $queue);
    }

    /**
     * Remove an item from the queue
     */
    public function removeFromQueue(string $id): void
    {
        $queue = $this->getQueue();
        $queue = array_filter($queue, fn ($item) => $item['id'] !== $id);
        Cache::put(self::CACHE_KEY, array_values($queue));
    }

    /**
     * Clear the import queue
     */
    public function clearQueue(): void
    {
        Cache::forget(self::CACHE_KEY);
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
        $this->ensureDirectoriesExist($incomingPath, $failedPath);

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
            $result = $this->processImportFile($file, $batchSize, $queuedCount);
            $results['queued'] += $result['queued'];
            $results['skipped'] += $result['skipped'];
            if ($result['error']) {
                $results['errors'][] = $result['error'];
            }
            $queuedCount += $result['queued'];
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
     * Ensure import directories exist
     */
    protected function ensureDirectoriesExist(string $incomingPath, string $failedPath): void
    {
        if (! File::exists($incomingPath)) {
            File::makeDirectory($incomingPath, 0755, true);
            Log::info('Created import incoming directory', ['path' => $incomingPath]);
        }

        if (! File::exists($failedPath)) {
            File::makeDirectory($failedPath, 0755, true);
            Log::info('Created import failed directory', ['path' => $failedPath]);
        }
    }

    /**
     * Process a single file for import
     */
    protected function processImportFile(\SplFileInfo $file, int $batchSize, int $queuedCount): array
    {
        $result = ['queued' => 0, 'skipped' => 0, 'error' => null];
        $filename = $file->getFilename();

        // Skip hidden files and log files
        if (str_starts_with($filename, '.') || str_ends_with($filename, '.log')) {
            $result['skipped'] = 1;

            return $result;
        }

        // Check if it's a video file
        if (! UploadService::isVideoFile($filename)) {
            Log::info('Skipping non-video file in import', ['file' => $filename]);
            $result['skipped'] = 1;

            return $result;
        }

        try {
            // Generate unique import ID
            $importId = Str::uuid()->toString();

            // Dispatch job with delay to spread out processing
            $delay = (int) floor($queuedCount / $batchSize) * 30;

            // Add to tracking queue
            $this->addToQueue($importId, $file->getPathname(), $filename);

            ProcessDirectImportJob::dispatch($importId, $file->getPathname(), $filename)
                ->onQueue('imports')
                ->delay(now()->addSeconds($delay));

            $result['queued'] = 1;

            Log::info('Queued file for import', [
                'file' => $filename,
                'importId' => $importId,
                'delay' => $delay,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue file for import', [
                'file' => $filename,
                'error' => $e->getMessage(),
            ]);
            $result['error'] = "{$filename}: {$e->getMessage()}";
        }

        return $result;
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
}
