<?php

namespace App\Services;

use App\Models\FailedDownload;
use App\Services\Maintenance\BackupService;
use App\Services\Maintenance\CleanupService;
use App\Services\Maintenance\ImportService;
use App\Services\Maintenance\MediaMaintenanceService;

/**
 * Facade service for maintenance operations.
 * Delegates to specialized services for specific functionality.
 */
class MaintenanceService
{
    public function __construct(
        protected CleanupService $cleanupService,
        protected BackupService $backupService,
        protected ImportService $importService,
        protected MediaMaintenanceService $mediaMaintenanceService
    ) {}

    // ==========================================
    // Cleanup Operations (delegated to CleanupService)
    // ==========================================

    public function removeDuplicates(): int
    {
        return $this->cleanupService->removeDuplicates();
    }

    public function cleanupOrphanedFiles(): int
    {
        return $this->cleanupService->cleanupOrphanedFiles();
    }

    public function cleanupTempFiles(int $maxAgeMinutes = 60): int
    {
        return $this->cleanupService->cleanupTempFiles($maxAgeMinutes);
    }

    public function cleanupOldFiles(?int $daysOld = null): int
    {
        return $this->cleanupService->cleanupOldFiles($daysOld);
    }

    public function rotateLogs(): int
    {
        return $this->cleanupService->rotateLogs();
    }

    // ==========================================
    // Backup Operations (delegated to BackupService)
    // ==========================================

    public function createDatabaseBackup(): ?string
    {
        return $this->backupService->createDatabaseBackup();
    }

    public function getBackups(): array
    {
        return $this->backupService->getBackups();
    }

    public function restoreFromBackup(string $sqlContent): array
    {
        return $this->backupService->restoreFromBackup($sqlContent);
    }

    // ==========================================
    // Import Operations (delegated to ImportService)
    // ==========================================

    public function scanAndQueueImports(): array
    {
        return $this->importService->scanAndQueueImports();
    }

    public function getImportStatus(): array
    {
        return $this->importService->getImportStatus();
    }

    public function clearFailedImports(): int
    {
        return $this->importService->clearFailedImports();
    }

    public function retryFailedImport(string $filename): bool
    {
        return $this->importService->retryFailedImport($filename);
    }

    // ==========================================
    // Media Maintenance (delegated to MediaMaintenanceService)
    // ==========================================

    public function updateYtDlp(): bool
    {
        return $this->mediaMaintenanceService->updateYtDlp();
    }

    public function regenerateThumbnails(): array
    {
        return $this->mediaMaintenanceService->regenerateThumbnails();
    }

    public function retryFailedDownloads(): int
    {
        return $this->mediaMaintenanceService->retryFailedDownloads();
    }

    public function logFailedDownload(string $url, string $method, string $errorMessage): FailedDownload
    {
        return $this->mediaMaintenanceService->logFailedDownload($url, $method, $errorMessage);
    }
}
