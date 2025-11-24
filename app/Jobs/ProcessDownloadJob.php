<?php

namespace App\Jobs;

use App\Helpers\MimeTypeHelper;
use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class ProcessDownloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes

    public function __construct(
        public string $url,
        public string $downloadId
    ) {}

    public function handle()
    {
        $downloadService = app(\App\Services\DownloadService::class);

        try {
            Log::info('Starting download job', [
                'url' => $this->url,
                'downloadId' => $this->downloadId
            ]);

            $downloadService->updateStatus($this->downloadId, 'downloading');

            // Try yt-dlp first, fallback to direct download
            try {
                $media = $this->downloadWithYtdlp($this->url);
            } catch (\Exception $e) {
                // If it's a YouTube URL, we trust yt-dlp's failure and do not fallback.
                if (preg_match('/(youtube\.com|youtu\.be)/i', $this->url)) {
                    throw $e;
                }

                Log::info('yt-dlp failed, trying direct download', ['error' => $e->getMessage()]);
                $media = $this->downloadDirectly($this->url);
            }

            Log::info('Download completed successfully', [
                'downloadId' => $this->downloadId,
                'mediaId' => $media->id
            ]);

            $downloadService->removeFromQueue($this->downloadId);
        } catch (\Exception $e) {
            Log::error('Download failed', [
                'downloadId' => $this->downloadId,
                'error' => $e->getMessage()
            ]);

            $downloadService->updateStatus($this->downloadId, 'failed', ['error' => $e->getMessage()]);
        }
    }

    private function downloadWithYtdlp(string $url)
    {
        // Create a unique temporary directory in storage/app/temp
        $tempDir = storage_path('app/temp/ytdlp_' . uniqid());
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        try {
            // First, check if yt-dlp can handle this URL
            $checkProcess = new Process([
                'yt-dlp',
                '--simulate',
                '--quiet',
                $url
            ]);

            $checkProcess->run();
            if (!$checkProcess->isSuccessful()) {
                throw new \Exception("URL not supported by yt-dlp");
            }

            // Get video metadata to extract the title
            $metadataProcess = new Process([
                'yt-dlp',
                '--no-playlist',
                '--no-warnings',
                '--dump-json',
                $url
            ]);

            $metadataProcess->run();
            if (!$metadataProcess->isSuccessful()) {
                throw new \Exception("Error fetching video metadata: " . $metadataProcess->getErrorOutput());
            }

            // Parse the JSON output
            $metadata = json_decode($metadataProcess->getOutput(), true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($metadata['title'])) {
                throw new \Exception("Error parsing video metadata");
            }

            // Use fulltitle if available, otherwise title
            $videoTitle = $metadata['fulltitle'] ?? $metadata['title'];

            // Output file template
            $outputTemplate = $tempDir . '/%(title)s.%(ext)s';

            // Get format preference from config
            $maxHeight = config('mgd.video_quality.max_height', 1080);
            $preferredFormat = config(
                'mgd.video_quality.format_selector',
                'bestvideo[height<=1080]+bestaudio/best[height<=1080]/bestvideo[height<=720]+bestaudio/best[height<=720]/best'
            );

            // Build download command with config options
            $command = [
                'yt-dlp',
                '--no-playlist',
                '--no-warnings',
                '--merge-output-format',
                config('mdg.video_quality.format', 'mp4'),
                '-f',
                $preferredFormat,
                '-o',
                $outputTemplate,
            ];

            // Add subtitle options if enabled
            if (config('mgd.video_quality.embed_subs', true)) {
                $command[] = '--embed-subs';
            }

            if (config('mgd.video_quality.auto_subs', true)) {
                $command[] = '--write-auto-sub';
                $command[] = '--sub-lang';
                $command[] = config('mgd.video_quality.sub_lang', 'en');
            }

            $command[] = $url;

            $process = new Process($command);
            $process->setTimeout(config('mgd.timeouts.download', 600));

            $downloadService = app(\App\Services\DownloadService::class);
            $process->run(function ($type, $buffer) use ($downloadService) {
                if ($type === Process::OUT) {
                    // Parse progress from stdout
                    // yt-dlp output format: [download]  23.5% of 10.00MiB at 2.00MiB/s ETA 00:05
                    if (preg_match('/\[download\]\s+(\d+(?:\.\d+)?)%/', $buffer, $matches)) {
                        $percent = (float) $matches[1];
                        $downloadService->updateStatus($this->downloadId, 'downloading', ['progress' => $percent]);
                    }
                }
            });

            if (!$process->isSuccessful()) {
                throw new \Exception("Error executing yt-dlp: " . $process->getErrorOutput());
            }

            $files = glob($tempDir . '/*');
            if (empty($files)) {
                throw new \Exception("No files were downloaded");
            }

            // Prioritize video files
            $downloadedFile = null;

            // 1. Look for video
            foreach ($files as $file) {
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                $mime = MimeTypeHelper::getMimeTypeFromExtension($ext);
                if (str_starts_with($mime, 'video/')) {
                    $downloadedFile = $file;
                    break;
                }
            }

            // If no video file found, throw exception
            if (!$downloadedFile) {
                $foundFiles = implode(', ', array_map('basename', $files));
                throw new \Exception("yt-dlp did not download a video file. Found: " . $foundFiles);
            }

            $originalFilename = basename($downloadedFile);
            $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);

            $uniqueId = \Illuminate\Support\Str::uuid()->toString();
            $proceduralFilename = $uniqueId . '.' . $extension;

            // Use MimeTypeHelper to get mime type from extension as it's more reliable for video files
            // than mime_content_type which often returns application/octet-stream
            $mimeType = MimeTypeHelper::getMimeTypeFromExtension($extension);
            if (empty($mimeType)) {
                $mimeType = mime_content_type($downloadedFile);
            }

            // Validate that it is a video file
            if (!str_starts_with($mimeType, 'video/')) {
                throw new \Exception("Downloaded file is not a video file (MIME: $mimeType)");
            }

            $fileSize = filesize($downloadedFile);

            $path = 'media/' . $proceduralFilename;
            // Use streaming to avoid loading file into memory
            Storage::disk('public')->putFileAs('media', new \Illuminate\Http\File($downloadedFile), $proceduralFilename);

            $media = Media::create([
                'name' => $videoTitle,
                'mime_type' => $mimeType,
                'size' => $fileSize,
                'file_name' => $proceduralFilename,
                'path' => $path,
                'url' => Storage::url($path),
                'source' => $url,
            ]);

            // Generate thumbnail if needed
            if ($media->needsThumbnail()) {
                $thumbnailService = app(\App\Services\ThumbnailService::class);
                $thumbnailPath = $thumbnailService->generateThumbnail($path, $mimeType);

                if ($thumbnailPath) {
                    $media->update(['thumbnail_path' => $thumbnailPath]);
                }
            }

            // Clean up temp directory
            array_map('unlink', glob($tempDir . '/*'));
            rmdir($tempDir);

            return $media;
        } catch (\Exception $e) {
            // Clean up temp directory if it exists
            if (file_exists($tempDir)) {
                array_map('unlink', glob($tempDir . '/*'));
                rmdir($tempDir);
            }

            throw $e;
        }
    }

    private function downloadDirectly(string $url)
    {
        // Create a unique temporary directory in storage/app/temp
        $tempDir = storage_path('app/temp/direct_' . uniqid());
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        try {
            // Validate URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \Exception("Invalid URL provided");
            }

            // Get the file name from the URL
            $originalFilename = basename(parse_url($url, PHP_URL_PATH));
            if (empty($originalFilename)) {
                // Try to get filename from content disposition header
                $headers = get_headers($url, 1);
                if (isset($headers['Content-Disposition'])) {
                    if (preg_match('/filename="(.+?)"/', $headers['Content-Disposition'], $matches)) {
                        $originalFilename = $matches[1];
                    }
                }

                // If still empty, use a hash of the URL
                if (empty($originalFilename)) {
                    $originalFilename = md5($url);

                    // Try to determine extension from content type
                    $contentType = $headers['Content-Type'] ?? '';
                    $extension = MimeTypeHelper::getExtensionFromMimeType($contentType);
                    if (!empty($extension)) {
                        $originalFilename .= '.' . $extension;
                    }
                }
            }

            // Set the output file path for temporary storage
            $outputFilePath = $tempDir . '/' . $originalFilename;

            // Download the file using HTTP client with streaming
            $response = Http::withOptions([
                'sink' => $outputFilePath,
                'timeout' => $this->timeout,
            ])->get($url);

            if ($response->failed()) {
                throw new \Exception("Error downloading file. Status: " . $response->status());
            }

            // Generate procedural filename (like UploadService would)
            $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
            if (empty($extension)) {
                // Try to determine extension from mime type
                $mimeType = mime_content_type($outputFilePath);
                $extension = MimeTypeHelper::getExtensionFromMimeType($mimeType);
                if (empty($extension)) {
                    $extension = 'bin'; // Default extension if we can't determine one
                }
            }

            $uniqueId = \Illuminate\Support\Str::uuid()->toString();
            $proceduralFilename = $uniqueId . '.' . $extension;

            // Get file details
            $mimeType = mime_content_type($outputFilePath);

            // If mime type is generic, try to guess from extension
            if ($mimeType === 'application/octet-stream' || empty($mimeType)) {
                $extMime = MimeTypeHelper::getMimeTypeFromExtension($extension);
                if (!empty($extMime)) {
                    $mimeType = $extMime;
                }
            }

            // Validate that it is a video file
            if (!str_starts_with($mimeType, 'video/')) {
                throw new \Exception("Downloaded file is not a video file (MIME: $mimeType)");
            }

            $fileSize = filesize($outputFilePath);

            // Store the file in the public storage with procedural filename
            $path = 'media/' . $proceduralFilename;
            // Use streaming to avoid loading file into memory
            Storage::disk('public')->putFileAs('media', new \Illuminate\Http\File($outputFilePath), $proceduralFilename);

            // Create media record
            $displayName = pathinfo($originalFilename, PATHINFO_FILENAME); // Remove extension for display
            $media = Media::create([
                'name' => $displayName,
                'mime_type' => $mimeType,
                'size' => $fileSize,
                'file_name' => $proceduralFilename,
                'path' => $path,
                'url' => Storage::url($path),
                'source' => $url,
            ]);

            // Generate thumbnail if needed
            if ($media->needsThumbnail()) {
                $thumbnailService = app(\App\Services\ThumbnailService::class);
                $thumbnailPath = $thumbnailService->generateThumbnail($path, $mimeType);

                if ($thumbnailPath) {
                    $media->update(['thumbnail_path' => $thumbnailPath]);
                }
            }

            // Clean up temp directory
            if (file_exists($tempDir)) {
                array_map('unlink', glob($tempDir . '/*'));
                rmdir($tempDir);
            }

            return $media;
        } catch (\Exception $e) {
            // Clean up temp directory if it exists
            if (file_exists($tempDir)) {
                array_map('unlink', glob($tempDir . '/*'));
                rmdir($tempDir);
            }

            throw $e;
        }
    }
}
