<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\FaststartService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-off / periodic maintenance: rewrite existing mp4/mov media so the moov
 * atom sits at the front of the file ("web faststart"), enabling instant start
 * and seeking over HTTP. Lossless and idempotent (already-faststart files are
 * skipped), so it is safe to re-run. Use --dry-run to preview.
 */
class RemuxFaststart extends Command
{
    protected $signature = 'media:remux-faststart {--dry-run : Show what would change without modifying anything}';

    protected $description = 'Faststart existing mp4/mov media (moov atom to the front) for instant HTTP playback';

    public function handle(FaststartService $faststart): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk('public');

        $remuxed = 0;
        $skipped = 0;
        $missing = 0;
        $failed = 0;

        Media::query()->orderBy('id')->chunkById(100, function ($records) use ($disk, $faststart, $dryRun, &$remuxed, &$skipped, &$missing, &$failed) {
            foreach ($records as $media) {
                if (! $media->path) {
                    $skipped++;

                    continue;
                }

                $absolute = $disk->path($media->path);

                if (! $faststart->supports($absolute)) {
                    $skipped++; // not an mp4/mov container

                    continue;
                }
                if (! is_file($absolute)) {
                    $this->warn("Missing file, skipping #{$media->id}: {$media->path}");
                    $missing++;

                    continue;
                }
                if ($faststart->isFaststarted($absolute)) {
                    $skipped++; // already web-optimised

                    continue;
                }

                $this->line(($dryRun ? '[dry-run] ' : '')."faststart: {$media->path}");

                if ($dryRun) {
                    $remuxed++;

                    continue;
                }

                if ($faststart->optimize($absolute)) {
                    $media->update(['size' => $disk->size($media->path)]);
                    $remuxed++;
                } else {
                    $failed++;
                }
            }
        });

        $this->newLine();
        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}Faststarted: {$remuxed}, skipped (n/a or already done): {$skipped}, missing: {$missing}, failed: {$failed}");

        return self::SUCCESS;
    }
}
