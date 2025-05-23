<?php

namespace App\Services;

use App\Helpers\MimeTypeHelper;
use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;

class UploadService
{
    /**
     * Process an uploaded file and create a media record
     */
    public function processUpload(UploadedFile $tempFile)
    {
        try {
            $tempFilePath = $tempFile->getRealPath();

            $name = $tempFile->getClientOriginalName();
            $mimeType = $tempFile->getMimeType();
            $fileSize = $tempFile->getSize();

            $extension = pathinfo($tempFile->getClientOriginalName(), PATHINFO_EXTENSION);
            // If no extension found, try to determine from mime type
            if (empty($extension)) {
                $extension = MimeTypeHelper::getExtensionFromMimeType($mimeType);
            }

            $fileName = basename($tempFile) . '.' . $extension;
            $filePath = Storage::disk('public')->putFileAs('media', $tempFile, $fileName);

            chown(storage_path('app/public/' . $filePath), 'app');
            chgrp(storage_path('app/public/' . $filePath), 'app');

            $fileUrl = Storage::url($filePath);

            try {
                $fileSize = Storage::disk('public')->size($filePath);
            } catch (\Exception $e) {
                Log::error("Error getting file size: " . $e->getMessage());
                $fileSize = 0;
            }

            $media = Media::create([
                'name' => $name,
                'mime_type' => $mimeType,
                'size' => $fileSize,
                'file_name' => $fileName,
                'path' => $filePath,
                'url' => $fileUrl,
                'source' => 'local',
            ]);

            if (file_exists($tempFilePath)) {
                @unlink($tempFilePath);
            }

            Notification::make()
                ->title('File uploaded successfully')
                ->success()
                ->send();

            return $media;
        } catch (\Exception $e) {
            Log::error("Error processing file: " . $e->getMessage(), [
                'exception' => $e
            ]);

            throw $e;
        }
    }
}
