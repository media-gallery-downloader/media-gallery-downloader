<?php

namespace App\Jobs;

use App\Helpers\MimeTypeHelper;
use App\Models\FailedUpload;
use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes

    public function __construct(
        public string $filePath,
        public string $originalName,
        public string $mimeType,
        public string $uploadId
    ) {}

    public function handle()
    {
        $uploadService = app(\App\Services\UploadService::class);

        try {
            Log::info('Starting upload job', [
                'uploadId' => $this->uploadId,
                'file' => $this->originalName
            ]);

            $uploadService->updateStatus($this->uploadId, 'processing', ['progress' => 0]);

            if (!file_exists($this->filePath)) {
                throw new \Exception("File not found at path: " . $this->filePath);
            }

            // Validate that it is a video file
            if (!str_starts_with($this->mimeType, 'video/')) {
                throw new \Exception("Uploaded file is not a video file (MIME: {$this->mimeType})");
            }

            $extension = pathinfo($this->originalName, PATHINFO_EXTENSION);
            if (empty($extension)) {
                $extension = MimeTypeHelper::getExtensionFromMimeType($this->mimeType);
            }

            $fileName = basename($this->filePath) . '.' . $extension;

            $uploadService->updateStatus($this->uploadId, 'processing', ['progress' => 25]);

            // Move from temp processing location to final public location
            $finalPath = Storage::disk('public')->putFileAs('media', new \Illuminate\Http\File($this->filePath), $fileName);

            if (!$finalPath) {
                throw new \Exception("Failed to store file");
            }

            $uploadService->updateStatus($this->uploadId, 'processing', ['progress' => 50]);

            $fileUrl = Storage::url($finalPath);
            $fileSize = Storage::disk('public')->size($finalPath);

            $media = Media::create([
                'name' => $this->originalName,
                'mime_type' => $this->mimeType,
                'size' => $fileSize,
                'file_name' => $fileName,
                'path' => $finalPath,
                'url' => $fileUrl,
                'source' => 'local',
            ]);

            $uploadService->updateStatus($this->uploadId, 'processing', ['progress' => 75]);

            // Generate thumbnail if needed
            if ($media->needsThumbnail()) {
                $thumbnailService = app(\App\Services\ThumbnailService::class);
                $thumbnailPath = $thumbnailService->generateThumbnail($media->path, $media->mime_type);

                if ($thumbnailPath) {
                    $media->update(['thumbnail_path' => $thumbnailPath]);
                }
            }

            $uploadService->updateStatus($this->uploadId, 'processing', ['progress' => 100]);

            // Clean up the temp file
            @unlink($this->filePath);

            Log::info('Upload completed successfully', [
                'uploadId' => $this->uploadId,
                'mediaId' => $media->id
            ]);

            $uploadService->removeFromQueue($this->uploadId);
        } catch (\Exception $e) {
            Log::error('Upload failed', [
                'uploadId' => $this->uploadId,
                'error' => $e->getMessage()
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
        }
    }
}
