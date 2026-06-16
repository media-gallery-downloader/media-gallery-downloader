<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Support\MediaFilename;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * One-off migration: rename legacy UUID-named media files to the readable
 * "<title>-<unix-timestamp>.<ext>" scheme and update their records (and
 * thumbnails). Idempotent and safe to re-run; use --dry-run to preview.
 */
class RenameMediaFiles extends Command
{
    protected $signature = 'media:rename-files {--dry-run : Show what would change without modifying anything}';

    protected $description = 'Rename UUID-named media files to "<title>-<unix-timestamp>.<ext>" and update their records';

    /** Matches the legacy "<uuid>.<ext>" filenames produced before the rename. */
    private const UUID_FILENAME = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\./i';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk('public');

        $renamed = 0;
        $thumbnails = 0;
        $skipped = 0;
        $missing = 0;
        $collisions = 0;

        Media::query()->orderBy('id')->chunkById(100, function ($records) use ($disk, $dryRun, &$renamed, &$thumbnails, &$skipped, &$missing, &$collisions) {
            foreach ($records as $media) {
                // Idempotency: only legacy UUID-named files are migrated.
                if (! preg_match(self::UUID_FILENAME, (string) $media->file_name)) {
                    $skipped++;

                    continue;
                }

                if (! $media->path || ! $disk->exists($media->path)) {
                    $this->warn("Missing file, skipping record #{$media->id}: {$media->path}");
                    $missing++;

                    continue;
                }

                $extension = pathinfo((string) $media->file_name, PATHINFO_EXTENSION);
                $timestamp = $media->created_at->timestamp;
                $title = (string) $media->name;

                $base = MediaFilename::build($title, $timestamp, $extension);
                $newFile = $dryRun ? $base : MediaFilename::generate($title, $timestamp, $extension, 'public', 'media');
                if ($newFile !== $base) {
                    $collisions++;
                }
                $newPath = 'media/'.$newFile;

                $newThumb = null;
                if ($media->thumbnail_path && $disk->exists($media->thumbnail_path)) {
                    $newThumb = 'thumbnails/'.MediaFilename::thumbnailName($newFile);
                }

                $prefix = $dryRun ? '[dry-run] ' : '';
                $this->line("{$prefix}{$media->path}  ->  {$newPath}");
                if ($newThumb) {
                    $this->line("{$prefix}  thumb: {$media->thumbnail_path}  ->  {$newThumb}");
                }

                if ($dryRun) {
                    $renamed++;
                    if ($newThumb) {
                        $thumbnails++;
                    }

                    continue;
                }

                if ($this->rename($disk, $media, $newPath, $newThumb)) {
                    $renamed++;
                    if ($newThumb) {
                        $thumbnails++;
                    }
                }
            }
        });

        $this->newLine();
        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}Renamed: {$renamed}, thumbnails: {$thumbnails}, skipped (already migrated): {$skipped}, missing files: {$missing}, collisions resolved: {$collisions}");

        return self::SUCCESS;
    }

    /**
     * Move the file (and thumbnail) then update the record. On a DB failure the
     * files are moved back so disk and database stay consistent.
     */
    private function rename(Filesystem $disk, Media $media, string $newPath, ?string $newThumb): bool
    {
        $oldPath = $media->path;
        $oldThumb = $media->thumbnail_path;

        $disk->move($oldPath, $newPath);
        if ($newThumb) {
            $disk->move($oldThumb, $newThumb);
        }

        $update = [
            'file_name' => basename($newPath),
            'path' => $newPath,
            'url' => MediaFilename::urlFor($newPath),
        ];
        if ($newThumb) {
            $update['thumbnail_path'] = $newThumb;
        }

        try {
            $media->update($update);
        } catch (\Throwable $e) {
            $disk->move($newPath, $oldPath);
            if ($newThumb) {
                $disk->move($newThumb, $oldThumb);
            }
            $this->error("Failed to update record #{$media->id}, reverted file move: {$e->getMessage()}");

            return false;
        }

        return true;
    }
}
