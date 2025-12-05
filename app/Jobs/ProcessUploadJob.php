<?php

namespace App\Jobs;

use App\Helpers\MimeTypeHelper;
use App\Models\FailedUpload;
use App\Models\Media;
use App\Services\UploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour for large archives

    private ?string $extractDir = null;

    public function __construct(
        public string $filePath,
        public string $originalName,
        public string $mimeType,
        public string $uploadId
    ) {}

    public function handle()
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
                $this->processVideoFile($this->filePath, $this->originalName, $uploadService);
            }

            // Clean up the temp file
            @unlink($this->filePath);

            // Clean up extracted files if any
            $this->cleanupExtractedFiles();

            Log::info('Upload completed successfully', [
                'uploadId' => $this->uploadId,
            ]);

            $uploadService->removeFromQueue($this->uploadId);
        } catch (\Exception $e) {
            Log::error('Upload failed', [
                'uploadId' => $this->uploadId,
                'error' => $e->getMessage(),
            ]);

            $uploadService->updateStatus($this->uploadId, 'failed', ['error' => $e->getMessage()]);

            // Track failed upload
            FailedUpload::createFromUpload(
                $this->originalName,
                $this->mimeType,
                $e->getMessage()
            );

            // Clean up temp file on failure
            @unlink($this->filePath);
            $this->cleanupExtractedFiles();
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
        $extension = strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));

        // Handle double extensions
        if (preg_match('/\.(tar\.(gz|bz2))$/i', $this->originalName, $matches)) {
            $extension = strtolower($matches[1]);
        }

        switch ($extension) {
            case 'zip':
                $this->extractZip();
                break;
            case 'tar':
                $this->extractTar();
                break;
            case 'tar.gz':
            case 'tgz':
                $this->extractTarGz();
                break;
            case 'tar.bz2':
            case 'tbz2':
                $this->extractTarBz2();
                break;
            case '7z':
                $this->extract7z();
                break;
            case 'rar':
                $this->extractRar();
                break;
            default:
                throw new \Exception("Unsupported archive format: {$extension}");
        }

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

        foreach ($videoFiles as $videoFile) {
            $originalName = basename($videoFile);
            $this->processVideoFile($videoFile, $originalName, $uploadService, false);
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
    private function processVideoFile(string $filePath, string $originalName, UploadService $uploadService, bool $updateProgress = true): void
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mimeType = MimeTypeHelper::getMimeTypeFromExtension($extension);

        if (empty($mimeType)) {
            $mimeType = mime_content_type($filePath);
        }

        // Validate it's a video file
        if (! str_starts_with($mimeType, 'video/')) {
            Log::warning('Skipping non-video file', ['file' => $originalName, 'mime' => $mimeType]);

            return;
        }

        // Generate unique filename
        $uniqueId = Str::uuid()->toString();
        $fileName = $uniqueId.'.'.$extension;

        if ($updateProgress) {
            $uploadService->updateStatus($this->uploadId, 'processing', ['progress' => 25]);
        }

        // Move to final public location
        $finalPath = Storage::disk('public')->putFileAs('media', new \Illuminate\Http\File($filePath), $fileName);

        if (! $finalPath) {
            throw new \Exception("Failed to store file: {$originalName}");
        }

        if ($updateProgress) {
            $uploadService->updateStatus($this->uploadId, 'processing', ['progress' => 50]);
        }

        $fileUrl = Storage::url($finalPath);
        $fileSize = Storage::disk('public')->size($finalPath);

        // Use original filename (without extension) as the display name
        $displayName = pathinfo($originalName, PATHINFO_FILENAME);

        $media = Media::create([
            'name' => $displayName,
            'mime_type' => $mimeType,
            'size' => $fileSize,
            'file_name' => $fileName,
            'path' => $finalPath,
            'url' => $fileUrl,
            'source' => 'local',
        ]);

        if ($updateProgress) {
            $uploadService->updateStatus($this->uploadId, 'processing', ['progress' => 75]);
        }

        // Generate thumbnail if needed
        if ($media->needsThumbnail()) {
            $thumbnailService = app(\App\Services\ThumbnailService::class);
            $thumbnailPath = $thumbnailService->generateThumbnail($media->path, $media->mime_type);

            if ($thumbnailPath) {
                $media->update(['thumbnail_path' => $thumbnailPath]);
            }
        }

        if ($updateProgress) {
            $uploadService->updateStatus($this->uploadId, 'processing', ['progress' => 100]);
        }

        Log::info('Video file processed', [
            'original' => $originalName,
            'newFilename' => $fileName,
            'mediaId' => $media->id,
        ]);
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
     * Extract ZIP archive
     */
    private function extractZip(): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($this->filePath) !== true) {
            throw new \Exception('Failed to open ZIP archive');
        }
        $zip->extractTo($this->extractDir);
        $zip->close();
    }

    /**
     * Extract TAR archive
     */
    private function extractTar(): void
    {
        $phar = new \PharData($this->filePath);
        $phar->extractTo($this->extractDir);
    }

    /**
     * Extract TAR.GZ archive
     */
    private function extractTarGz(): void
    {
        $phar = new \PharData($this->filePath);
        $phar->decompress();

        // Get the decompressed tar file path
        $tarPath = preg_replace('/\.(gz|tgz)$/i', '', $this->filePath);
        if (preg_match('/\.tgz$/i', $this->filePath)) {
            $tarPath = preg_replace('/\.tgz$/i', '.tar', $this->filePath);
        }

        if (file_exists($tarPath)) {
            $tar = new \PharData($tarPath);
            $tar->extractTo($this->extractDir);
            @unlink($tarPath);
        }
    }

    /**
     * Extract TAR.BZ2 archive
     */
    private function extractTarBz2(): void
    {
        $phar = new \PharData($this->filePath);
        $phar->decompress();

        $tarPath = preg_replace('/\.(bz2|tbz2)$/i', '', $this->filePath);
        if (preg_match('/\.tbz2$/i', $this->filePath)) {
            $tarPath = preg_replace('/\.tbz2$/i', '.tar', $this->filePath);
        }

        if (file_exists($tarPath)) {
            $tar = new \PharData($tarPath);
            $tar->extractTo($this->extractDir);
            @unlink($tarPath);
        }
    }

    /**
     * Extract 7z archive (requires p7zip)
     */
    private function extract7z(): void
    {
        $command = sprintf(
            '7z x %s -o%s -y 2>&1',
            escapeshellarg($this->filePath),
            escapeshellarg($this->extractDir)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Failed to extract 7z archive: '.implode("\n", $output));
        }
    }

    /**
     * Extract RAR archive (requires unrar)
     */
    private function extractRar(): void
    {
        $command = sprintf(
            'unrar x -o+ %s %s 2>&1',
            escapeshellarg($this->filePath),
            escapeshellarg($this->extractDir.'/')
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Failed to extract RAR archive: '.implode("\n", $output));
        }
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
