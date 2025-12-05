<?php

namespace App\Services\Maintenance;

use App\Models\Media;
use App\Services\ThumbnailService;
use App\Settings\MaintenanceSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Handles database backup and restore operations
 */
class BackupService extends BaseMaintenanceService
{
    public function __construct(
        protected ThumbnailService $thumbnailService
    ) {}

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
            $filepath = $this->createMysqlBackup($filepath);
            if ($filepath) {
                return $filepath;
            }
        }

        $this->sendNotification('Database Backup', 'Backup failed!', false);

        return null;
    }

    /**
     * Create MySQL backup using mysqldump
     */
    protected function createMysqlBackup(string $filepath): ?string
    {
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
            $filename = basename($filepath);
            $this->sendNotification('Database Backup', "Backup created: {$filename}", true);

            return $filepath;
        }

        return null;
    }

    /**
     * Clean up old database backups
     */
    public function cleanupOldBackups(): int
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
     * Restore media records from a SQL backup file
     *
     * @param  string  $sqlContent  The SQL content from backup file
     * @return array ['imported' => int, 'skipped' => int, 'queued' => int]
     */
    public function restoreFromBackup(string $sqlContent): array
    {
        // Check if this is a SQLite database file (starts with "SQLite format 3")
        if (str_starts_with($sqlContent, 'SQLite format 3')) {
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
        $noSourceRecords = [];

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

            foreach ($backupRecords as $record) {
                $result = $this->processBackupRecord($record);
                $imported += $result['imported'];
                $skipped += $result['skipped'];
                $queued += $result['queued'];
                if ($result['no_source']) {
                    $noSourceRecords[] = $result['no_source'];
                }
                if ($result['duplicate']) {
                    $duplicateRecords[] = $result['duplicate'];
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
     * Process a single backup record for import
     */
    protected function processBackupRecord(array $record): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'queued' => 0, 'no_source' => null, 'duplicate' => null];

        // Skip records without source URL - they can't be re-downloaded
        if (empty($record['source'])) {
            $result['no_source'] = $record['name'] ?? 'Unknown';

            return $result;
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
            $result['duplicate'] = $record['name'] ?? 'Unknown';
            $result['skipped'] = 1;

            return $result;
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
            'thumbnail_path' => null,
            'created_at' => $record['created_at'] ?? now(),
            'updated_at' => $record['updated_at'] ?? now(),
        ]);

        $result['imported'] = 1;

        // Check if file exists locally
        $fileExists = ! empty($record['path']) && Storage::disk('public')->exists($record['path']);

        if ($fileExists) {
            // Regenerate thumbnail if needed
            if ($media->needsThumbnail()) {
                $thumbnailPath = $this->thumbnailService->generateThumbnail($record['path'], $record['mime_type']);
                if ($thumbnailPath) {
                    $media->update(['thumbnail_path' => $thumbnailPath]);
                }
            }
        } elseif (! empty($record['source'])) {
            // Queue for download
            \App\Jobs\ProcessRestoreDownloadJob::dispatch($media->id)->onQueue('imports');
            $result['queued'] = 1;
        }

        return $result;
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
            $values = str_getcsv($valuesStr, ',', "'");

            if (count($values) < 5) {
                continue;
            }

            $record = [
                'name' => trim($values[1] ?? '', "'\""),
                'mime_type' => trim($values[2] ?? '', "'\""),
                'size' => (int) ($values[3] ?? 0),
                'file_name' => trim($values[4] ?? '', "'\"") ?: null,
                'path' => trim($values[5] ?? '', "'\"") ?: null,
                'source' => trim($values[7] ?? '', "'\"") ?: null,
            ];

            $result = $this->processBackupRecordFromDump($record);
            $imported += $result['imported'];
            $skipped += $result['skipped'];
            $queued += $result['queued'];
            if ($result['no_source']) {
                $noSourceRecords[] = $result['no_source'];
            }
            if ($result['duplicate']) {
                $duplicateRecords[] = $result['duplicate'];
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

    /**
     * Process a single backup record from SQL dump
     */
    protected function processBackupRecordFromDump(array $record): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'queued' => 0, 'no_source' => null, 'duplicate' => null];

        // Skip records without source URL
        if (empty($record['source'])) {
            $result['no_source'] = $record['name'] ?: 'Unknown';

            return $result;
        }

        // Check for duplicates
        $existing = Media::where('name', $record['name'])
            ->where('size', $record['size'])
            ->first();

        if ($existing) {
            $result['duplicate'] = $record['name'] ?: 'Unknown';
            $result['skipped'] = 1;

            return $result;
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

        $result['imported'] = 1;

        // Check if file exists, queue download if not
        $fileExists = ! empty($record['path']) && Storage::disk('public')->exists($record['path']);

        if (! $fileExists && ! empty($record['source'])) {
            \App\Jobs\ProcessRestoreDownloadJob::dispatch($media->id)->onQueue('imports');
            $result['queued'] = 1;
        } elseif ($fileExists && $media->needsThumbnail()) {
            $thumbnailPath = $this->thumbnailService->generateThumbnail($record['path'], $record['mime_type']);
            if ($thumbnailPath) {
                $media->update(['thumbnail_path' => $thumbnailPath]);
            }
        }

        return $result;
    }
}
