<?php

namespace App\Services\Download;

use App\Models\Media;
use App\Services\Sites\SiteCookieCopy;
use Symfony\Component\Process\Process;

/**
 * Downloads media using yt-dlp for supported platforms
 * (YouTube, Vimeo, Twitter, etc.)
 */
class YtDlpDownloadHandler extends BaseDownloadHandler
{
    private DownloadOptions $options;

    public function __construct(private ?YtDlpArguments $arguments = null)
    {
        $this->options = DownloadOptions::none();
    }

    /**
     * The download's own options (one-off yt-dlp arguments), applied on top
     * of the Settings arguments for every yt-dlp command of this download.
     */
    public function withOptions(DownloadOptions $options): static
    {
        $this->options = $options;

        return $this;
    }

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
        $cookies = null;

        try {
            // yt-dlp works on a private copy of the site's cookies (see
            // SiteCookieCopy), written back once both runs are over.
            $cookies = $this->arguments()->copyCookies($url, $tempDir);

            // Get video metadata (this will fail if yt-dlp can't handle the URL)
            $metadata = $this->fetchMetadata($url, $tempDir, $cookies);
            $videoTitle = $metadata['fulltitle'] ?? $metadata['title'];

            // Download the video
            $downloadedFile = $this->executeDownload($url, $tempDir, $downloadId, $progressCallback, $cookies);

            // Create media record
            return $this->storeAndCreateMedia($downloadedFile, $videoTitle, $url);
        } finally {
            $cookies?->writeBack();
            $this->cleanupTempDirectory($tempDir);
        }
    }

    /**
     * Fetch video metadata from yt-dlp. Runs in $workingDirectory (the
     * download's temp directory) so anything yt-dlp writes relative to its
     * working directory stays in there.
     */
    protected function fetchMetadata(string $url, ?string $workingDirectory = null, ?SiteCookieCopy $cookies = null): array
    {
        $metadataProcess = new Process($this->buildMetadataCommand($url, $cookies), $workingDirectory);
        $metadataProcess->setTimeout(config('mgd.timeouts.metadata', 120));
        $metadataProcess->run();

        if (! $metadataProcess->isSuccessful()) {
            throw new \Exception($this->parseError($metadataProcess->getErrorOutput(), self::hostWithoutSite($url, $cookies)));
        }

        $metadata = json_decode($metadataProcess->getOutput(), true);
        if (json_last_error() !== JSON_ERROR_NONE || ! isset($metadata['title'])) {
            throw new \Exception('Error parsing video metadata');
        }

        return $metadata;
    }

    /**
     * Build the yt-dlp metadata command. It is the first request to the site,
     * so it gets the same cookies and extra arguments as the download:
     * options such as impersonation, a proxy, headers or the IP family can
     * decide whether the site can be reached at all.
     *
     * @return list<string>
     */
    protected function buildMetadataCommand(string $url, ?SiteCookieCopy $cookies = null): array
    {
        return [
            'yt-dlp',
            '--no-playlist',
            '--no-warnings',
            '--dump-json',
            ...self::cookieArguments($cookies),
            // Extra arguments go last, right before "--" (which stops the
            // URL from ever being read as an option) and the URL.
            ...$this->arguments()->extraArguments($url, $this->options),
            '--',
            $url,
        ];
    }

    /**
     * Execute the download command
     */
    protected function executeDownload(
        string $url,
        string $tempDir,
        string $downloadId,
        ?callable $progressCallback,
        ?SiteCookieCopy $cookies = null,
    ): string {
        $outputTemplate = $tempDir.'/%(title)s.%(ext)s';
        $command = $this->buildDownloadCommand($url, $outputTemplate, $cookies);

        // Run in the temp directory so nothing lands in the app's working directory.
        $process = new Process($command, $tempDir);
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
            throw new \Exception($this->parseError($process->getErrorOutput(), self::hostWithoutSite($url, $cookies)));
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
    protected function buildDownloadCommand(string $url, string $outputTemplate, ?SiteCookieCopy $cookies = null): array
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
        $command = array_merge($command, self::cookieArguments($cookies));

        // Add subtitle options
        if (config('mgd.video_quality.embed_subs', true)) {
            $command[] = '--embed-subs';
        }

        if (config('mgd.video_quality.auto_subs', true)) {
            $command[] = '--write-auto-subs';
            $command[] = '--sub-langs';
            $command[] = config('mgd.video_quality.sub_lang', 'en');
        }

        // Extra arguments go last, right before "--" (which stops the URL
        // from ever being read as an option) and the URL: they can override
        // the defaults above, and nothing the app adds comes after them.
        $command = array_merge($command, $this->arguments()->extraArguments($url, $this->options));

        $command[] = '--';
        $command[] = $url;

        return $command;
    }

    /**
     * The URL's host when the run had no site cookies, for error hints.
     */
    private static function hostWithoutSite(string $url, ?SiteCookieCopy $cookies): ?string
    {
        return $cookies === null ? (parse_url($url, PHP_URL_HOST) ?: null) : null;
    }

    /**
     * @return list<string>
     */
    private static function cookieArguments(?SiteCookieCopy $cookies): array
    {
        return $cookies !== null ? ['--cookies', $cookies->path] : [];
    }

    protected function arguments(): YtDlpArguments
    {
        return $this->arguments ??= app(YtDlpArguments::class);
    }

    /**
     * Parse error output for user-friendly messages
     */
    protected function parseError(string $errorOutput, ?string $hostWithoutSite = null): string
    {
        $errorOutput = YtDlpOutput::withoutCookieLines($errorOutput);
        $noSite = $hostWithoutSite !== null ? " No site in Settings → Sites covers {$hostWithoutSite} yet." : '';

        if (str_contains($errorOutput, 'cookies are no longer valid') || str_contains($errorOutput, 'rotated')) {
            return 'YouTube cookies have expired. Paste fresh YouTube cookies into the YouTube site in Settings → Sites.';
        }

        if (str_contains($errorOutput, 'Sign in to confirm your age')) {
            return 'This video is age-restricted. Add cookies from a logged-in YouTube account to the YouTube site in Settings → Sites.'.$noSite;
        }

        // yt-dlp says "Your IP address is unable to access the Reddit API"
        // whenever a reddit_session cookie was sent and Reddit still refused
        // the request. That is rarely about the IP address.
        if (str_contains($errorOutput, 'unable to access the Reddit API')) {
            return 'Reddit refused the request even with the uploaded cookies. Re-export fresh Reddit cookies and replace them in the Reddit site in Settings → Sites. If fresh cookies don\'t help, Reddit is blocking the way yt-dlp accesses it and a yt-dlp update is needed (Settings → Maintenance → yt-dlp update → Run Now).';
        }

        // Reddit (and others) require an authenticated session to fetch
        // metadata: cookies from a logged-in browser, added to the site in
        // Settings → Sites, resolve it.
        if (str_contains($errorOutput, 'authentication is required') || str_contains($errorOutput, 'Account authentication')) {
            return 'This site requires you to be logged in. Add this site\'s cookies in Settings → Sites.'.$noSite;
        }

        return 'yt-dlp error: '.($errorOutput ?: 'URL not supported');
    }
}
