<?php

namespace App\Jobs;

use App\Models\FailedUpload;
use App\Services\Upload\ArchiveExtractor;
use App\Services\Upload\VideoProcessor;
use App\Services\UploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour for large archives

    public $tries = 1; // Single attempt, failures are logged for manual retry

    private ?string $extractDir = null;

    public function __construct(
        public string $filePath,
        public string $originalName,
        public string $mimeType,
        public string $uploadId
    ) {}

    public function handle(): void
    {
        $uploadService = app(UploadService::class);

        try {
            Log::info('Starting upload job', [
                'uploadId' => $this->uploadId,
                'file' => $this->originalName,
            ]);

            $uploadService->updateStatus($this->uploadId, 'processing', ['progress' => 0]);

            if (! file_exists($this->filePath)) {
                throw new \Exception('File not found at path: '.$this->filePath);
            }

            // Check if it's an archive or video
            if (UploadService::isArchive($this->originalName)) {
                $this->processArchive($uploadService);
            } else {
                $this->processVideoFile($uploadService);
            }

            // Clean up
            @unlink($this->filePath);
            $this->cleanupExtractedFiles();

            Log::info('Upload completed successfully', ['uploadId' => $this->uploadId]);

            $uploadService->removeFromQueue($this->uploadId);
        } catch (\Exception $e) {
            $this->handleFailure($e, $uploadService);
        }
    }

    /**
     * Process an archive file - extract and import videos
     */
    private function processArchive(UploadService $uploadService): void
    {
        $this->extractDir = storage_path('app/temp/extract_'.$this->uploadId);

        if (! file_exists($this->extractDir)) {
            mkdir($this->extractDir, 0755, true);
        }

        $uploadService->updateStatus($this->uploadId, 'processing', ['progress' => 10]);

        // Extract the archive
        $extractor = new ArchiveExtractor;
        $extractor->extract($this->filePath, $this->extractDir);

        $uploadService->updateStatus($this->uploadId, 'processing', ['progress' => 30]);

        // Find video files in extracted content
        $videoFiles = $this->findVideoFiles($this->extractDir);

        if (empty($videoFiles)) {
            throw new \Exception('No video files found in the archive');
        }

        $totalFiles = count($videoFiles);
        $processedFiles = 0;

        Log::info('Found video files in archive', [
            'uploadId' => $this->uploadId,
            'count' => $totalFiles,
        ]);

        $videoProcessor = app(VideoProcessor::class);

        foreach ($videoFiles as $videoFile) {
            $originalName = basename($videoFile);
            $videoProcessor->process($videoFile, $originalName, $uploadService, $this->uploadId, false);
            $processedFiles++;

            $progress = 30 + (($processedFiles / $totalFiles) * 70);
            $uploadService->updateStatus($this->uploadId, 'processing', ['progress' => $progress]);
        }

        Log::info('Archive processing completed', [
            'uploadId' => $this->uploadId,
            'totalFiles' => $totalFiles,
        ]);
    }

    /**
     * Process a single video file
     */
    private function processVideoFile(UploadService $uploadService): void
    {
        $videoProcessor = app(VideoProcessor::class);
        $videoProcessor->process($this->filePath, $this->originalName, $uploadService, $this->uploadId, true);
    }

    /**
     * Recursively find video files in a directory
     */
    private function findVideoFiles(string $directory): array
    {
        $videoFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && UploadService::isVideoFile($file->getPathname())) {
                $videoFiles[] = $file->getPathname();
            }
        }

        return $videoFiles;
    }

    /**
     * Handle upload failure
     */
    private function handleFailure(\Exception $e, UploadService $uploadService): void
    {
        Log::error('Upload failed', [
            'uploadId' => $this->uploadId,
            'error' => $e->getMessage(),
        ]);

        $uploadService->updateStatus($this->uploadId, 'failed', ['error' => $e->getMessage()]);

        FailedUpload::createFromUpload(
            $this->originalName,
            $this->mimeType,
            $e->getMessage()
        );

        // Clean up temp file on failure
        @unlink($this->filePath);
        $this->cleanupExtractedFiles();
    }

    /**
     * Clean up extracted files
     */
    private function cleanupExtractedFiles(): void
    {
        if ($this->extractDir && is_dir($this->extractDir)) {
            $this->deleteDirectory($this->extractDir);
        }
    }

    /**
     * Recursively delete a directory
     */
    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    /**
     * Handle a job failure
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('Upload job failed permanently', [
            'uploadId' => $this->uploadId,
            'file' => $this->originalName,
            'error' => $exception?->getMessage(),
        ]);

        // Clean up
        @unlink($this->filePath);
        $this->cleanupExtractedFiles();

        $uploadService = app(UploadService::class);
        $uploadService->updateStatus($this->uploadId, 'failed', [
            'error' => $exception?->getMessage() ?? 'Unknown error',
        ]);

        FailedUpload::createFromUpload(
            $this->originalName,
            $this->mimeType,
            $exception?->getMessage() ?? 'Job failed unexpectedly'
        );
    }
}
