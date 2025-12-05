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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Job to download videos for restored media records that have a source URL
 * but no local file. This is used during backup restore when videos need
 * to be re-downloaded from their original sources.
 */
class ProcessRestoreDownloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 7200; // 2 hours for large files

    public $tries = 1; // Single attempt, log failures

    public function __construct(
        public int $mediaId
    ) {}

    public function handle(): void
    {
        $media = Media::find($this->mediaId);

        if (! $media) {
            Log::warning('ProcessRestoreDownloadJob: Media record not found', ['mediaId' => $this->mediaId]);

            return;
        }

        // Skip if file already exists
        if ($media->path && Storage::disk('public')->exists($media->path)) {
            Log::info('ProcessRestoreDownloadJob: File already exists', ['mediaId' => $this->mediaId]);

            return;
        }

        // Skip if no source URL
        if (empty($media->source)) {
            Log::warning('ProcessRestoreDownloadJob: No source URL', ['mediaId' => $this->mediaId]);

            return;
        }

        Log::info('ProcessRestoreDownloadJob: Starting download', [
            'mediaId' => $this->mediaId,
            'source' => $media->source,
            'name' => $media->name,
        ]);

        try {
            $downloadedPath = $this->downloadWithYtdlp($media->source);

            if (! $downloadedPath) {
                throw new \Exception('Download returned no file path');
            }

            // Generate new UUID filename
            $extension = pathinfo($downloadedPath, PATHINFO_EXTENSION);
            $uniqueId = Str::uuid()->toString();
            $proceduralFilename = $uniqueId.'.'.$extension;
            $path = 'media/'.$proceduralFilename;

            // Move file to storage
            Storage::disk('public')->putFileAs('media', new \Illuminate\Http\File($downloadedPath), $proceduralFilename);

            // Clean up temp file
            @unlink($downloadedPath);

            // Update media record
            $media->update([
                'path' => $path,
                'file_name' => $proceduralFilename,
                'url' => Storage::url($path),
                'size' => Storage::disk('public')->size($path),
            ]);

            // Generate thumbnail if needed
            if ($media->needsThumbnail()) {
                $thumbnailService = app(\App\Services\ThumbnailService::class);
                $thumbnailPath = $thumbnailService->generateThumbnail($path, $media->mime_type);

                if ($thumbnailPath) {
                    $media->update(['thumbnail_path' => $thumbnailPath]);
                }
            }

            Log::info('ProcessRestoreDownloadJob: Download completed', [
                'mediaId' => $this->mediaId,
                'path' => $path,
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessRestoreDownloadJob: Download failed', [
                'mediaId' => $this->mediaId,
                'source' => $media->source,
                'error' => $e->getMessage(),
            ]);

            // Don't retry - the restore service will show failed downloads
        }
    }

    /**
     * Download using yt-dlp
     */
    private function downloadWithYtdlp(string $url): ?string
    {
        $tempDir = storage_path('app/temp/restore_'.uniqid());
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        try {
            $cookieArgs = $this->getCookieArguments();

            // Output template
            $outputTemplate = $tempDir.'/%(title)s.%(ext)s';

            // Get format preference from config
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

            $command = array_merge($command, $cookieArgs);

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
            $process->run();

            if (! $process->isSuccessful()) {
                throw new \Exception('yt-dlp error: '.$process->getErrorOutput());
            }

            $files = glob($tempDir.'/*');
            if (empty($files)) {
                throw new \Exception('No files were downloaded');
            }

            // Find video file
            $downloadedFile = null;
            foreach ($files as $file) {
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                $mime = MimeTypeHelper::getMimeTypeFromExtension($ext);
                if (str_starts_with($mime, 'video/')) {
                    $downloadedFile = $file;
                    break;
                }
            }

            if (! $downloadedFile) {
                throw new \Exception('No video file found in download');
            }

            return $downloadedFile;
        } catch (\Exception $e) {
            // Clean up temp directory
            if (file_exists($tempDir)) {
                array_map('unlink', glob($tempDir.'/*'));
                @rmdir($tempDir);
            }
            throw $e;
        }
    }

    /**
     * Get cookie arguments for yt-dlp
     */
    private function getCookieArguments(): array
    {
        $cookiesPath = storage_path('app/youtube_cookies.txt');

        if (file_exists($cookiesPath)) {
            return ['--cookies', $cookiesPath];
        }

        return [];
    }
}
