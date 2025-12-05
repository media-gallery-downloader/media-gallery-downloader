<?php

namespace App\Services\Download;

use App\Models\Media;

/**
 * Interface for download handlers
 */
interface DownloadHandlerInterface
{
    /**
     * Check if this handler can process the given URL
     */
    public function canHandle(string $url): bool;

    /**
     * Download the media from the URL
     *
     * @param  string  $url  The URL to download from
     * @param  string  $downloadId  The download ID for progress tracking
     * @param  callable|null  $progressCallback  Optional callback for progress updates
     * @return Media The created media record
     *
     * @throws \Exception If download fails
     */
    public function download(string $url, string $downloadId, ?callable $progressCallback = null): Media;

    /**
     * Get the handler name for logging
     */
    public function getName(): string;
}
