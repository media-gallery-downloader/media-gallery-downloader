<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Rewrites mp4/mov files so the `moov` atom (the index) sits at the front of the
 * file ("web faststart"). Without this the browser must download large parts of
 * the file before it can start or seek over HTTP — the cause of videos that
 * stall, freeze, or freeze-on-seek when streamed through the app, even though
 * they play fine from a local SMB/NFS mount.
 *
 * The rewrite is lossless (`-c copy`, container remux only) and idempotent.
 */
class FaststartService
{
    /** Containers that use the mp4 box / "moov atom" layout. */
    private const SUPPORTED_EXTENSIONS = ['mp4', 'm4v', 'mov'];

    /**
     * Faststart an mp4/mov in place. No-op for other containers or files that
     * are already faststarted.
     *
     * @return bool true if the file was remuxed, false if skipped or failed
     */
    public function optimize(string $absolutePath): bool
    {
        if (! $this->supports($absolutePath) || $this->isFaststarted($absolutePath)) {
            return false;
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $tempPath = $absolutePath.'.faststart.'.$extension;

        $process = new Process([
            'ffmpeg', '-y',
            '-i', $absolutePath,
            '-map', '0',
            '-c', 'copy',
            '-movflags', '+faststart',
            $tempPath,
        ]);
        $process->setTimeout(config('mgd.timeouts.download', 600));
        $process->run();

        if (! $process->isSuccessful() || ! is_file($tempPath) || filesize($tempPath) === 0) {
            @unlink($tempPath);
            Log::warning('Faststart remux failed', [
                'file' => $absolutePath,
                'error' => $process->getErrorOutput(),
            ]);

            return false;
        }

        rename($tempPath, $absolutePath);

        return true;
    }

    /**
     * Whether faststart applies to this file's container.
     */
    public function supports(string $absolutePath): bool
    {
        if (! is_file($absolutePath)) {
            return false;
        }

        return in_array(strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)), self::SUPPORTED_EXTENSIONS, true);
    }

    /**
     * Heuristic: an mp4/mov is "faststart" when its `moov` box appears before
     * the `mdat` box. Walks the top-level box list without reading the payload.
     */
    public function isFaststarted(string $absolutePath): bool
    {
        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            return false; // can't tell -> let the remux decide
        }

        try {
            $fileSize = filesize($absolutePath);
            $offset = 0;

            while ($offset < $fileSize) {
                if (fseek($handle, $offset) !== 0) {
                    break;
                }
                $header = fread($handle, 8);
                if ($header === false || strlen($header) < 8) {
                    break;
                }

                /** @var array{1: int} $sizeParts */
                $sizeParts = unpack('N', substr($header, 0, 4));
                $boxSize = $sizeParts[1];
                $type = substr($header, 4, 4);

                if ($type === 'moov') {
                    return true;  // moov before mdat -> already faststart
                }
                if ($type === 'mdat') {
                    return false; // mdat before moov -> needs faststart
                }

                if ($boxSize === 1) { // 64-bit extended size
                    $largeHeader = fread($handle, 8);
                    if ($largeHeader === false || strlen($largeHeader) < 8) {
                        break;
                    }
                    /** @var array{1: int} $largeParts */
                    $largeParts = unpack('J', $largeHeader);
                    $boxSize = $largeParts[1];
                }

                if ($boxSize < 8) {
                    break; // malformed / box-to-end-of-file
                }
                $offset += $boxSize;
            }
        } finally {
            fclose($handle);
        }

        return false;
    }
}
