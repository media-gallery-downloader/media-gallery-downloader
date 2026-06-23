<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\MediaProbeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Probes the media library's codecs and lists files whose video/audio codec is
 * outside the configured baseline (config: mgd.codecs.*) — i.e. files that may
 * not play in your target browsers and are candidates for re-encoding.
 */
class ProbeCodecs extends Command
{
    protected $signature = 'media:probe-codecs {--json : Output the flagged files as JSON}';

    protected $description = 'List media whose codecs are outside the browser-compatible baseline';

    public function handle(MediaProbeService $probe): int
    {
        $baselineVideo = config('mgd.codecs.baseline_video', []);
        $baselineAudio = config('mgd.codecs.baseline_audio', []);
        $disk = Storage::disk('public');

        $flagged = [];
        $videoDist = [];
        $audioDist = [];
        $missing = 0;
        $scanned = 0;

        Media::query()->orderBy('id')->chunkById(100, function ($records) use ($disk, $probe, $baselineVideo, $baselineAudio, &$flagged, &$videoDist, &$audioDist, &$missing, &$scanned) {
            foreach ($records as $media) {
                if (! $media->path || ! is_file($disk->path($media->path))) {
                    $missing++;

                    continue;
                }

                $scanned++;
                ['video' => $video, 'audio' => $audio] = $probe->codecs($disk->path($media->path));

                if ($video !== null) {
                    $videoDist[$video] = ($videoDist[$video] ?? 0) + 1;
                }
                if ($audio !== null) {
                    $audioDist[$audio] = ($audioDist[$audio] ?? 0) + 1;
                }

                $videoBad = $video !== null && ! in_array($video, $baselineVideo, true);
                $audioBad = $audio !== null && ! in_array($audio, $baselineAudio, true);

                if ($videoBad || $audioBad) {
                    $flagged[] = [
                        'id' => $media->id,
                        'path' => $media->path,
                        'video' => $video ?? '-',
                        'audio' => $audio ?? '-',
                        'reason' => trim(($videoBad ? "video:{$video} " : '').($audioBad ? "audio:{$audio}" : '')),
                    ];
                }
            }
        });

        if ($this->option('json')) {
            $this->line((string) json_encode($flagged, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info("Scanned {$scanned} files (".count($flagged).' flagged, '."{$missing} missing on disk).");
        $this->newLine();

        $this->line('Video codecs: '.$this->formatDist($videoDist));
        $this->line('Audio codecs: '.$this->formatDist($audioDist));
        $this->newLine();

        if ($flagged === []) {
            $this->info('No files outside the baseline. 🎉');

            return self::SUCCESS;
        }

        $this->warn('Files outside the baseline (candidates for re-encoding):');
        $this->table(['ID', 'Path', 'Video', 'Audio'], array_map(
            fn ($f) => [$f['id'], $f['path'], $f['video'], $f['audio']],
            $flagged,
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $dist
     */
    private function formatDist(array $dist): string
    {
        if ($dist === []) {
            return '(none)';
        }
        arsort($dist);

        return implode(', ', array_map(fn ($codec, $count) => "{$codec}×{$count}", array_keys($dist), array_values($dist)));
    }
}
