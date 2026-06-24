<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\MediaProbeService;
use App\Services\MediaTranscoder;
use App\Support\MediaFilename;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Re-encodes media. Two modes:
 *
 *   compatibility (default): files whose codec is OUTSIDE the configured
 *     baseline (config: mgd.codecs.*) are re-encoded to H.264 + AAC so they
 *     play everywhere.
 *
 *   size (--shrink): large, already-compatible files (e.g. big H.264) are
 *     re-encoded to a smaller codec (HEVC by default). Gated by --min-size so
 *     tiny files aren't churned. This is LOSSY and CPU-heavy — opt-in only, and
 *     only sensible when your players support the target codec.
 */
class Reencode extends Command
{
    protected $signature = 'media:reencode
        {--dry-run : Show what would be re-encoded without doing it}
        {--id=* : Only operate on these media IDs}
        {--shrink : Size mode: re-encode large baseline files to a smaller codec}
        {--to= : Target video codec (default: h264 for compatibility, hevc for --shrink)}
        {--min-size=0 : Skip files smaller than this (e.g. 500M, 2G)}
        {--accel=none : Hardware acceleration: none|vaapi|nvenc (see media:encoders)}
        {--crf= : Quality (lower = better/larger); codec default otherwise}';

    protected $description = 'Re-encode media for compatibility (->h264) or size (--shrink, ->hevc)';

    public function handle(MediaProbeService $probe, MediaTranscoder $transcoder): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $shrink = (bool) $this->option('shrink');
        $accel = (string) $this->option('accel');
        $target = (string) ($this->option('to') ?: ($shrink ? 'hevc' : 'h264'));
        $minSize = $this->parseSize((string) $this->option('min-size'));
        $crf = $this->option('crf') !== null ? (int) $this->option('crf') : null;
        $ids = (array) $this->option('id');

        $disk = Storage::disk('public');
        $baselineVideo = config('mgd.codecs.baseline_video', []);
        $baselineAudio = config('mgd.codecs.baseline_audio', []);

        $done = 0;
        $skipped = 0;
        $failed = 0;
        $missing = 0;
        $savedBytes = 0;

        $query = Media::query()->orderBy('id');
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $query->chunkById(50, function ($records) use ($disk, $probe, $transcoder, $shrink, $target, $accel, $minSize, $crf, $baselineVideo, $baselineAudio, $dryRun, &$done, &$skipped, &$failed, &$missing, &$savedBytes) {
            foreach ($records as $media) {
                if (! $media->path || ! is_file($disk->path($media->path))) {
                    $missing++;

                    continue;
                }

                $absolute = $disk->path($media->path);
                $size = (int) filesize($absolute);
                ['video' => $video, 'audio' => $audio] = $probe->codecs($absolute);

                $reason = $this->reasonToReencode($shrink, $video, $audio, $size, $minSize, $target, $baselineVideo, $baselineAudio);
                if ($reason === null) {
                    $skipped++;

                    continue;
                }

                $this->line(($dryRun ? '[dry-run] ' : '')."#{$media->id} {$media->path}  [{$reason}, ".$this->human($size).']');

                if ($dryRun) {
                    $done++;

                    continue;
                }

                $temp = $absolute.'.reencode.mp4';
                if (! $transcoder->transcode($absolute, $temp, $target, $accel, $crf)) {
                    $this->error("  failed: #{$media->id}");
                    $failed++;

                    continue;
                }

                $newSize = (int) filesize($temp);
                $this->place($media, $disk, $temp, $newSize);
                $savedBytes += max(0, $size - $newSize);
                $done++;
            }
        });

        $this->newLine();
        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}Re-encoded: {$done}, skipped: {$skipped}, missing: {$missing}, failed: {$failed}".
            ($savedBytes > 0 ? ', reclaimed: '.$this->human($savedBytes) : ''));

        return self::SUCCESS;
    }

    /**
     * Why (if at all) this file should be re-encoded, or null to skip.
     */
    private function reasonToReencode(bool $shrink, ?string $video, ?string $audio, int $size, int $minSize, string $target, array $baselineVideo, array $baselineAudio): ?string
    {
        if ($size < $minSize) {
            return null;
        }

        if ($shrink) {
            // Already compatible + large enough -> shrink to a smaller codec.
            if ($video !== null && $video !== $target && in_array($video, $baselineVideo, true)) {
                return "shrink {$video}->{$target}";
            }

            return null;
        }

        // Compatibility: codec outside the baseline -> h264/aac.
        $videoBad = $video !== null && ! in_array($video, $baselineVideo, true);
        $audioBad = $audio !== null && ! in_array($audio, $baselineAudio, true);
        if ($videoBad || $audioBad) {
            return 'compat '.trim(($videoBad ? "v:{$video} " : '').($audioBad ? "a:{$audio}" : ''))."->{$target}/aac";
        }

        return null;
    }

    /**
     * Put the re-encoded mp4 in place, updating the record. Output is always mp4,
     * so a non-mp4 source gets its extension (and record) switched to .mp4.
     */
    private function place(Media $media, Filesystem $disk, string $tempPath, int $newSize): void
    {
        $currentExt = strtolower(pathinfo((string) $media->file_name, PATHINFO_EXTENSION));

        if ($currentExt === 'mp4') {
            rename($tempPath, $disk->path($media->path));
            $media->update(['size' => $newSize]);

            return;
        }

        // Container changed to mp4: rename the stored file + update the record.
        $newFileName = pathinfo((string) $media->file_name, PATHINFO_FILENAME).'.mp4';
        $newPath = 'media/'.$newFileName;
        rename($tempPath, $disk->path($newPath));
        @unlink($disk->path($media->path));

        $media->update([
            'file_name' => $newFileName,
            'path' => $newPath,
            'url' => MediaFilename::urlFor($newPath),
            'mime_type' => 'video/mp4',
            'size' => $newSize,
        ]);
    }

    private function parseSize(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '0') {
            return 0;
        }
        if (preg_match('/^(\d+(?:\.\d+)?)\s*([kmgt]?)b?$/i', $value, $m)) {
            $n = (float) $m[1];
            $unit = strtolower($m[2]);

            return (int) ($n * (1024 ** ['' => 0, 'k' => 1, 'm' => 2, 'g' => 3, 't' => 4][$unit]));
        }

        return (int) $value;
    }

    private function human(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), 1).$units[$i];
    }
}
