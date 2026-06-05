<?php

namespace App\Services;

use App\Jobs\ProcessUploadJob;
use App\Models\QueueItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class UploadService
{
    /**
     * Add an upload to the queue and dispatch the job
     */
    public function enqueueUpload(UploadedFile|TemporaryUploadedFile $file): void
    {
        $uploadId = \Illuminate\Support\Str::uuid()->toString();
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();

        Log::info('UploadService: enqueueUpload called', [
            'originalName' => $originalName,
            'mimeType' => $mimeType,
            'fileClass' => get_class($file),
        ]);

        // Move to a temporary location that persists across requests/jobs
        // We can't use the livewire temp file directly as it might be cleaned up
        $tempPath = storage_path('app/temp');
        if (! file_exists($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        // Use UUID only for temp filename to avoid path length issues
        // Original name is passed separately to the job for database storage
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $fileName = $uploadId.($extension ? '.'.$extension : '');
        $fullPath = $tempPath.'/'.$fileName;

        // Handle both TemporaryUploadedFile and regular UploadedFile
        if ($file instanceof TemporaryUploadedFile) {
            // For Livewire temp files, copy the content
            $sourcePath = $file->getRealPath();
            Log::info('UploadService: Copying from temp file', ['source' => $sourcePath, 'dest' => $fullPath]);

            if (! copy($sourcePath, $fullPath)) {
                throw new \Exception("Failed to copy file from {$sourcePath} to {$fullPath}");
            }

            // Delete the Livewire temp file since we've copied it
            @unlink($sourcePath);
        } else {
            // For regular uploaded files, use move
            $file->move($tempPath, $fileName);
        }

        Log::info('Adding upload to queue', [
            'file' => $originalName,
            'uploadId' => $uploadId,
            'path' => $fullPath,
        ]);

        // Add to Redis queue
        $this->addToQueue($uploadId, $originalName, $mimeType);

        // Dispatch the upload job
        ProcessUploadJob::dispatch($fullPath, $originalName, $mimeType, $uploadId)
            ->onQueue('uploads');
    }

    public function getQueue(): array
    {
        return QueueItem::where('type', 'upload')
            ->orderBy('id')
            ->get()
            ->map(fn (QueueItem $item) => array_merge([
                'id' => $item->queue_id,
                'filename' => $item->filename,
                'mime_type' => $item->mime_type,
                'status' => $item->status,
                'added_at' => $item->created_at?->toISOString(),
            ], $item->meta ?? []))
            ->all();
    }

    public function addToQueue(string $id, string $filename, string $mimeType): void
    {
        QueueItem::updateOrCreate(
            ['queue_id' => $id],
            ['type' => 'upload', 'filename' => $filename, 'mime_type' => $mimeType, 'status' => 'queued'],
        );
    }

    public function updateStatus(string $id, string $status, array $extra = []): void
    {
        $item = QueueItem::where('queue_id', $id)->first();
        if (! $item) {
            return;
        }

        $item->status = $status;
        if (! empty($extra)) {
            $item->meta = array_merge($item->meta ?? [], $extra);
        }
        $item->save();
    }

    public function removeFromQueue(string $id): void
    {
        QueueItem::where('queue_id', $id)->delete();
    }

    public function clearQueue(): void
    {
        QueueItem::where('type', 'upload')->delete();
    }

    /**
     * Get supported archive formats
     */
    public static function getSupportedArchiveFormats(): array
    {
        return ['zip', 'tar', 'tar.gz', 'tgz', 'tar.bz2', 'tbz2', '7z', 'rar'];
    }

    /**
     * Get supported video extensions
     */
    public static function getSupportedVideoExtensions(): array
    {
        return ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'm4v', 'mpg', 'mpeg', '3gp', 'ogv'];
    }

    /**
     * Check if a file is a supported archive by extension
     */
    public static function isArchive(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Handle double extensions like .tar.gz
        if (preg_match('/\.(tar\.(gz|bz2))$/i', $filename, $matches)) {
            $extension = strtolower($matches[1]);
        }

        return in_array($extension, self::getSupportedArchiveFormats());
    }

    /**
     * Check if a file is a video by extension
     */
    public static function isVideoFile(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($extension, self::getSupportedVideoExtensions());
    }
}
