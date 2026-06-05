<?php

namespace App\Services\Download;

use App\Models\Media;
use App\Settings\MaintenanceSettings;
use Illuminate\Support\Facades\Log;
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
        $metadataProcess->setTimeout(config('mgd.timeouts.metadata', 120));
        $metadataProcess->run();

        if (! $metadataProcess->isSuccessful()) {
            throw new \Exception($this->parseError($metadataProcess->getErrorOutput()));
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
            throw new \Exception($this->parseError($process->getErrorOutput()));
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
            config('mgd.video_quality.format', 'mp4'),
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
            $command[] = '--write-auto-subs';
            $command[] = '--sub-langs';
            $command[] = config('mgd.video_quality.sub_lang', 'en');
        }

        // Add user-configured extra arguments from settings
        $command = array_merge($command, $this->getExtraArguments());

        $command[] = $url;

        return $command;
    }

    /**
     * yt-dlp flags that are refused in user-supplied extra arguments because
     * they allow arbitrary command execution, reading/writing arbitrary files,
     * or escaping the managed output directory.
     */
    protected const DISALLOWED_EXTRA_FLAGS = [
        '--exec',
        '--exec-before-download',
        '--config-location',
        '--config-locations',
        '--load-info-json',
        '--load-info',
        '--batch-file',
        '-a',
        '--output',
        '-o',
        '--paths',
        '-P',
        '--downloader',
        '--external-downloader',
        '--use-postprocessor',
        '--print-to-file',
        '--cookies',
        '--cookies-from-browser',
    ];

    /**
     * Get extra arguments configured in settings
     */
    protected function getExtraArguments(): array
    {
        $args = [];

        try {
            $settings = app(MaintenanceSettings::class);
            $extraArgs = $settings->ytdlp_extra_args ?? '';

            if (! empty($extraArgs)) {
                // Split by newlines and filter empty lines
                $lines = array_filter(
                    array_map('trim', explode("\n", $extraArgs)),
                    fn ($line) => ! empty($line) && ! str_starts_with($line, '#')
                );

                foreach ($lines as $line) {
                    // Handle arguments that may contain spaces (e.g., "--output /path/to/file")
                    // by using preg_split to respect quoted strings
                    $parts = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);
                    $args = array_merge($args, $parts);
                }
            }
        } catch (\Exception $e) {
            // If settings aren't available, just continue without extra args
            return [];
        }

        if ($disallowed = $this->findDisallowedFlag($args)) {
            // Fail safe: drop the entire user-supplied set rather than running a
            // command with a dangerous flag. Admin-only, but still validated.
            Log::warning('Ignoring yt-dlp extra arguments: disallowed flag present', [
                'flag' => $disallowed,
            ]);

            return [];
        }

        return $args;
    }

    /**
     * Return the first disallowed flag found in a token list, or null if clean.
     * Long flags are matched case-insensitively and support the --flag=value
     * form; short flags are matched exactly (yt-dlp short flags are case
     * sensitive, e.g. -P differs from -p).
     */
    protected function findDisallowedFlag(array $tokens): ?string
    {
        foreach ($tokens as $token) {
            if (! str_starts_with($token, '-')) {
                continue;
            }

            $flag = explode('=', $token, 2)[0];
            $normalized = str_starts_with($flag, '--') ? strtolower($flag) : $flag;

            if (in_array($normalized, self::DISALLOWED_EXTRA_FLAGS, true)) {
                return $flag;
            }
        }

        return null;
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
            return 'YouTube cookies have expired. Please upload fresh cookies in Settings → Authentication (Cookies).';
        }

        if (str_contains($errorOutput, 'Sign in to confirm your age')) {
            return 'This video is age-restricted. Please upload valid YouTube cookies in Settings → Authentication (Cookies).';
        }

        // Reddit (and others) now require an authenticated session to fetch
        // metadata. The cookies.txt configured in Settings → YouTube
        // Authentication is sent to yt-dlp for every site, so adding this
        // site's cookies to that file resolves it.
        if (str_contains($errorOutput, 'authentication is required') || str_contains($errorOutput, 'Account authentication')) {
            return 'This site requires you to be logged in. Add this site\'s cookies to your cookies.txt (Settings → Authentication (Cookies)); the same cookies file is used for all sites.';
        }

        return 'yt-dlp error: '.($errorOutput ?: 'URL not supported');
    }
}
