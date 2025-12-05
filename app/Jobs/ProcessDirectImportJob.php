<?php

namespace App\Jobs;

use App\Helpers\MimeTypeHelper;
use App\Models\Media;
use App\Services\ThumbnailService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessDirectImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     * Set high to handle very large files (100s of GB)
     */
    public $timeout = 7200; // 2 hours

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $filePath,
        public string $originalName
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ThumbnailService $thumbnailService): void
    {
        $failedPath = config('mgd.import.failed_path');

        try {
            Log::info('Starting direct import job', [
                'file' => $this->originalName,
                'path' => $this->filePath,
            ]);

            // Verify file still exists
            if (! File::exists($this->filePath)) {
                throw new \Exception("Source file no longer exists: {$this->filePath}");
            }

            // Get file metadata
            $extension = strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
            $mimeType = MimeTypeHelper::getMimeTypeFromExtension($extension);

            if (empty($mimeType)) {
                $mimeType = mime_content_type($this->filePath);
            }

            // Validate it's a video file
            if (! str_starts_with($mimeType, 'video/')) {
                throw new \Exception("File is not a video: {$this->originalName} (MIME: {$mimeType})");
            }

            $fileSize = File::size($this->filePath);
            $fileModifiedTime = Carbon::createFromTimestamp(File::lastModified($this->filePath));

            // Extract display name (filename without extension)
            $displayName = pathinfo($this->originalName, PATHINFO_FILENAME);

            // Generate unique filename using UUID (consistent with upload/download)
            $uniqueId = Str::uuid()->toString();
            $finalFileName = $uniqueId.'.'.$extension;
            $finalPath = 'media/'.$finalFileName;

            // Store file using Storage facade (handles streaming for large files)
            $stored = Storage::disk('public')->putFileAs(
                'media',
                new \Illuminate\Http\File($this->filePath),
                $finalFileName
            );

            if (! $stored) {
                throw new \Exception("Failed to store file: {$this->originalName}");
            }

            // Delete the original file from incoming directory
            File::delete($this->filePath);

            // Create the media record
            $media = Media::create([
                'name' => $displayName,
                'mime_type' => $mimeType,
                'size' => $fileSize,
                'file_name' => $finalFileName,
                'path' => $finalPath,
                'url' => Storage::url($finalPath),
                'source' => 'import',
                'created_at' => $fileModifiedTime,
                'updated_at' => $fileModifiedTime,
            ]);

            Log::info('File imported successfully', [
                'original' => $this->originalName,
                'mediaId' => $media->id,
                'newFilename' => $finalFileName,
            ]);

            // Generate thumbnail if needed
            if ($media->needsThumbnail()) {
                try {
                    $thumbnailPath = $thumbnailService->generateThumbnail($media->path, $media->mime_type);

                    if ($thumbnailPath) {
                        $media->update(['thumbnail_path' => $thumbnailPath]);
                        Log::info('Thumbnail generated for import', ['mediaId' => $media->id]);
                    }
                } catch (\Exception $e) {
                    // Log but don't fail the import if thumbnail generation fails
                    Log::warning('Thumbnail generation failed for import', [
                        'mediaId' => $media->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Direct import failed', [
                'file' => $this->originalName,
                'error' => $e->getMessage(),
            ]);

            $this->moveToFailed($e->getMessage(), $failedPath);

            throw $e;
        }
    }

    /**
     * Move the file to the failed directory and create a log file.
     */
    protected function moveToFailed(string $errorMessage, string $failedPath): void
    {
        try {
            // Ensure failed directory exists
            if (! File::exists($failedPath)) {
                File::makeDirectory($failedPath, 0755, true);
            }

            // Only move if source file still exists
            if (File::exists($this->filePath)) {
                $failedFilePath = $failedPath.'/'.$this->originalName;

                // Handle duplicate filenames in failed directory
                $counter = 1;
                while (File::exists($failedFilePath)) {
                    $pathInfo = pathinfo($this->originalName);
                    $failedFilePath = $failedPath.'/'.$pathInfo['filename'].'_'.$counter.'.'.$pathInfo['extension'];
                    $counter++;
                }

                File::move($this->filePath, $failedFilePath);

                // Create log file with error details
                $logFilePath = $failedFilePath.'.log';
                $logContent = sprintf(
                    "Import Failed: %s\n".
                        "Original File: %s\n".
                        "Timestamp: %s\n".
                        "Error: %s\n",
                    basename($failedFilePath),
                    $this->originalName,
                    now()->toDateTimeString(),
                    $errorMessage
                );

                File::put($logFilePath, $logContent);

                Log::info('Failed import moved to failed directory', [
                    'original' => $this->originalName,
                    'failedPath' => $failedFilePath,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to move file to failed directory', [
                'file' => $this->originalName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessDirectImportJob failed permanently', [
            'file' => $this->originalName,
            'error' => $exception->getMessage(),
        ]);
    }
}
