<?php

namespace App\Services;

use App\Jobs\ProcessUploadJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class UploadService
{
    /**
     * Add an upload to the queue and dispatch the job
     */
    public function enqueueUpload(UploadedFile $file): void
    {
        $uploadId = \Illuminate\Support\Str::uuid()->toString();
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();

        // Move to a temporary location that persists across requests/jobs
        // We can't use the livewire temp file directly as it might be cleaned up
        $tempPath = storage_path('app/temp');
        if (!file_exists($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        $fileName = $uploadId . '_' . $originalName;
        $file->move($tempPath, $fileName);
        $fullPath = $tempPath . '/' . $fileName;

        Log::info('Adding upload to queue', [
            'file' => $originalName,
            'uploadId' => $uploadId
        ]);

        // Add to Redis queue
        $this->addToQueue($uploadId, $originalName, $mimeType);

        // Dispatch the upload job
        ProcessUploadJob::dispatch($fullPath, $originalName, $mimeType, $uploadId)
            ->onQueue('uploads');
    }

    public function getQueue(): array
    {
        return Cache::get('upload_queue', []);
    }

    public function addToQueue(string $id, string $filename, string $mimeType): void
    {
        $queue = $this->getQueue();
        $queue[] = [
            'id' => $id,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'status' => 'queued',
            'added_at' => now()->toISOString(),
        ];
        Cache::put('upload_queue', $queue);
    }

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
        Cache::put('upload_queue', $queue);
    }

    public function removeFromQueue(string $id): void
    {
        $queue = $this->getQueue();
        $queue = array_filter($queue, fn($item) => $item['id'] !== $id);
        Cache::put('upload_queue', array_values($queue));
    }

    public function clearQueue(): void
    {
        Cache::forget('upload_queue');
    }
}
