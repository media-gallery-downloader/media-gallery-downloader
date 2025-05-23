<?php

namespace App\Services;

use App\Helpers\MimeTypeHelper;
use App\Models\Media;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;

class DownloadService
{
    /**
     * Download media from a URL
     */
    public function downloadFromUrl(string $url)
    {
        try {
            // Validate URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \Exception("Invalid URL provided");
            }

            // Get file information
            $fileName = basename(parse_url($url, PHP_URL_PATH));
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);

            // If no extension in the URL, try to determine from headers
            if (empty($extension)) {
                $headers = get_headers($url, 1);
                $contentType = $headers['Content-Type'] ?? '';
                $extension = $extension = MimeTypeHelper::getExtensionFromMimeType($contentType);
                $fileName = md5($url) . '.' . $extension;
            }

            // Download the file to a temporary location
            $tempFile = tempnam(sys_get_temp_dir(), 'download_');
            file_put_contents($tempFile, file_get_contents($url));

            // Get file details
            $mimeType = mime_content_type($tempFile);
            $fileSize = filesize($tempFile);

            // Store the file
            $path = 'media/' . $fileName;
            Storage::disk('public')->put($path, file_get_contents($tempFile));

            // Clean up temp file
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }

            // Create media record
            $media = Media::create([
                'name' => $fileName,
                'mime_type' => $mimeType,
                'size' => $fileSize,
                'file_name' => $fileName,
                'path' => $path,
                'url' => Storage::url($path),
                'source' => $url
            ]);

            Notification::make()
                ->title('File downloaded successfully')
                ->success()
                ->send();

            return $media;
        } catch (\Exception $e) {
            Log::error("Error downloading file: " . $e->getMessage(), [
                'url' => $url,
                'exception' => $e
            ]);

            throw $e;
        }
    }
}
