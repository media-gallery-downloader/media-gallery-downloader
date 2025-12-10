<?php

namespace App\Services\Download;

use App\Models\Media;
use Symfony\Component\Process\Process;

/**
 * Downloads media using yt-dlp for supported platforms
 * (YouTube, Vimeo, Twitter, etc.)
 */
class YtDlpDownloadHandler extends BaseDownloadHandler
{
    public function getName(): string
    {
        return 'yt-dlp';
    }

    public function canHandle(string $url): bool
    {
        // yt-dlp supports most video platforms, we'll try it first
        // and let it fail gracefully if not supported
        return true;
    }

    public function download(string $url, string $downloadId, ?callable $progressCallback = null): Media
    {
        $tempDir = $this->createTempDirectory('ytdlp');

        try {
            // Get video metadata (this will fail if yt-dlp can't handle the URL)
            $metadata = $this->fetchMetadata($url);
            $videoTitle = $metadata['fulltitle'] ?? $metadata['title'];

            // Download the video
            $downloadedFile = $this->executeDownload($url, $tempDir, $downloadId, $progressCallback);

            // Create media record
            $media = $this->storeAndCreateMedia($downloadedFile, $videoTitle, $url);

            $this->cleanupTempDirectory($tempDir);

            return $media;
        } catch (\Exception $e) {
            $this->cleanupTempDirectory($tempDir);
            throw $e;
        }
    }

    /**
     * Fetch video metadata from yt-dlp
     */
    protected function fetchMetadata(string $url): array
    {
        $cookieArgs = $this->getCookieArguments();

        $metadataCommand = array_merge(
            ['yt-dlp', '--no-playlist', '--no-warnings', '--dump-json'],
            $cookieArgs,
            [$url]
        );

        $metadataProcess = new Process($metadataCommand);
        $metadataProcess->setTimeout(120); // 2 minute timeout for metadata
        $metadataProcess->run();

        if (! $metadataProcess->isSuccessful()) {
            throw new \Exception('Error fetching video metadata: '.$metadataProcess->getErrorOutput());
        }

        $metadata = json_decode($metadataProcess->getOutput(), true);
        if (json_last_error() !== JSON_ERROR_NONE || ! isset($metadata['title'])) {
            throw new \Exception('Error parsing video metadata');
        }

        return $metadata;
    }

    /**
     * Execute the download command
     */
    protected function executeDownload(
        string $url,
        string $tempDir,
        string $downloadId,
        ?callable $progressCallback
    ): string {
        $outputTemplate = $tempDir.'/%(title)s.%(ext)s';
        $command = $this->buildDownloadCommand($url, $outputTemplate);

        $process = new Process($command);
        $process->setTimeout(config('mgd.timeouts.download', 600));

        $process->run(function ($type, $buffer) use ($progressCallback) {
            if ($type === Process::OUT && $progressCallback) {
                if (preg_match('/\[download\]\s+(\d+(?:\.\d+)?)%/', $buffer, $matches)) {
                    $percent = (float) $matches[1];
                    $progressCallback($percent);
                }
            }
        });

        if (! $process->isSuccessful()) {
            throw new \Exception('Error executing yt-dlp: '.$process->getErrorOutput());
        }

        $files = glob($tempDir.'/*');
        if (empty($files)) {
            throw new \Exception('No files were downloaded');
        }

        $downloadedFile = $this->findVideoFile($files);
        if (! $downloadedFile) {
            $foundFiles = implode(', ', array_map('basename', $files));
            throw new \Exception('yt-dlp did not download a video file. Found: '.$foundFiles);
        }

        return $downloadedFile;
    }

    /**
     * Build the yt-dlp download command
     */
    protected function buildDownloadCommand(string $url, string $outputTemplate): array
    {
        $preferredFormat = config(
            'mgd.video_quality.format_selector',
            'bestvideo[height<=1080]+bestaudio/best[height<=1080]/bestvideo[height<=720]+bestaudio/best[height<=720]/best'
        );

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

        // Add cookie arguments
        $command = array_merge($command, $this->getCookieArguments());

        // Add subtitle options
        if (config('mgd.video_quality.embed_subs', true)) {
            $command[] = '--embed-subs';
        }

        if (config('mgd.video_quality.auto_subs', true)) {
            $command[] = '--write-auto-sub';
            $command[] = '--sub-lang';
            $command[] = config('mgd.video_quality.sub_lang', 'en');
        }

        $command[] = $url;

        return $command;
    }

    /**
     * Get cookie arguments for yt-dlp
     */
    protected function getCookieArguments(): array
    {
        $args = [];

        $browserName = config('mgd.youtube.cookies_from_browser');
        if ($browserName) {
            $args[] = '--cookies-from-browser';
            $args[] = $browserName;

            return $args;
        }

        $cookiesFile = config('mgd.youtube.cookies_file');
        if ($cookiesFile && file_exists($cookiesFile)) {
            $args[] = '--cookies';
            $args[] = $cookiesFile;
        }

        return $args;
    }

    /**
     * Parse error output for user-friendly messages
     */
    protected function parseError(string $errorOutput): string
    {
        if (str_contains($errorOutput, 'cookies are no longer valid') || str_contains($errorOutput, 'rotated')) {
            return 'YouTube cookies have expired. Please upload fresh cookies in Settings → YouTube Authentication.';
        }

        if (str_contains($errorOutput, 'Sign in to confirm your age')) {
            return 'This video is age-restricted. Please upload valid YouTube cookies in Settings → YouTube Authentication.';
        }

        return 'yt-dlp error: '.($errorOutput ?: 'URL not supported');
    }
}
