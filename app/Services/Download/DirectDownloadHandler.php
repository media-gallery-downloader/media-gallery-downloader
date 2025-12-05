<?php

namespace App\Services\Download;

use App\Helpers\MimeTypeHelper;
use App\Models\Media;
use Illuminate\Support\Facades\Http;

/**
 * Downloads media directly via HTTP for direct file URLs
 */
class DirectDownloadHandler extends BaseDownloadHandler
{
    protected int $timeout = 300;

    public function getName(): string
    {
        return 'direct';
    }

    public function canHandle(string $url): bool
    {
        // Can handle any valid URL
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function download(string $url, string $downloadId, ?callable $progressCallback = null): Media
    {
        $tempDir = $this->createTempDirectory('direct');

        try {
            // Validate URL
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \Exception('Invalid URL provided');
            }

            // Determine filename
            $originalFilename = $this->determineFilename($url);

            // Download the file
            $outputFilePath = $tempDir.'/'.$originalFilename;
            $this->downloadFile($url, $outputFilePath);

            // Get display name and determine mime type
            $displayName = pathinfo($originalFilename, PATHINFO_FILENAME);
            $mimeType = $this->determineMimeType($outputFilePath, $originalFilename);

            // Create media record
            $media = $this->storeAndCreateMedia($outputFilePath, $displayName, $url, $mimeType);

            $this->cleanupTempDirectory($tempDir);

            return $media;
        } catch (\Exception $e) {
            $this->cleanupTempDirectory($tempDir);
            throw $e;
        }
    }

    /**
     * Determine the filename from URL or headers
     */
    protected function determineFilename(string $url): string
    {
        $originalFilename = basename(parse_url($url, PHP_URL_PATH));
        $headers = null;

        if (empty($originalFilename)) {
            $headers = get_headers($url, true);
            if ($headers !== false && isset($headers['Content-Disposition'])) {
                if (preg_match('/filename="(.+?)"/', $headers['Content-Disposition'], $matches)) {
                    $originalFilename = $matches[1];
                }
            }
        }

        // If still empty, use a hash of the URL
        if (empty($originalFilename)) {
            $originalFilename = md5($url);

            // Try to determine extension from content type
            $contentType = is_array($headers) ? ($headers['Content-Type'] ?? '') : '';
            if (is_array($contentType)) {
                $contentType = $contentType[0] ?? '';
            }
            $extension = MimeTypeHelper::getExtensionFromMimeType($contentType);
            if (! empty($extension)) {
                $originalFilename .= '.'.$extension;
            }
        }

        return $originalFilename;
    }

    /**
     * Download the file using HTTP client
     */
    protected function downloadFile(string $url, string $outputPath): void
    {
        $response = Http::withOptions([
            'sink' => $outputPath,
            'timeout' => $this->timeout,
        ])->get($url);

        if ($response->failed()) {
            throw new \Exception('Error downloading file. Status: '.$response->status());
        }
    }

    /**
     * Determine the mime type of the downloaded file
     */
    protected function determineMimeType(string $filePath, string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        // Get extension from file if not in filename
        if (empty($extension)) {
            $mimeType = mime_content_type($filePath);
            $extension = MimeTypeHelper::getExtensionFromMimeType($mimeType);
            if (empty($extension)) {
                $extension = 'bin';
            }
        }

        $mimeType = mime_content_type($filePath);

        // If mime type is generic, try to guess from extension
        if ($mimeType === 'application/octet-stream' || empty($mimeType)) {
            $extMime = MimeTypeHelper::getMimeTypeFromExtension($extension);
            if (! empty($extMime)) {
                $mimeType = $extMime;
            }
        }

        return $mimeType;
    }

    /**
     * Set the timeout for downloads
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }
}
