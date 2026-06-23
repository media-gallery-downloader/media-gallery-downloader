<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Re-encodes a video to a target codec (H.264 or HEVC) + AAC audio, in a
 * faststarted mp4. Supports software (libx264/libx265) and VAAPI hardware
 * encoding. Used by `media:reencode` for compatibility re-encodes (non-baseline
 * codec -> h264) and size-optimisation (large h264 -> hevc).
 */
class MediaTranscoder
{
    /**
     * Transcode $inputPath into $outputPath (mp4). Returns false on failure
     * (and removes any partial output).
     *
     * @param  string  $targetVideo  'h264' | 'hevc'
     * @param  string  $accel  'none' | 'vaapi'
     */
    public function transcode(string $inputPath, string $outputPath, string $targetVideo, string $accel = 'none', ?int $crf = null): bool
    {
        $process = new Process($this->command($inputPath, $outputPath, $targetVideo, $accel, $crf));
        $process->setTimeout((float) config('mgd.timeouts.reencode', 21600));
        $process->run();

        if (! $process->isSuccessful() || ! is_file($outputPath) || filesize($outputPath) === 0) {
            @unlink($outputPath);
            Log::warning('Transcode failed', [
                'input' => $inputPath,
                'codec' => $targetVideo,
                'accel' => $accel,
                'error' => substr($process->getErrorOutput(), -2000),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Build the ffmpeg argv for the requested target and acceleration.
     *
     * @return list<string>
     */
    public function command(string $input, string $output, string $targetVideo, string $accel, ?int $crf): array
    {
        if ($accel === 'vaapi') {
            $device = (string) config('mgd.transcode.vaapi_device', '/dev/dri/renderD128');
            $encoder = $targetVideo === 'hevc' ? 'hevc_vaapi' : 'h264_vaapi';

            return [
                'ffmpeg', '-y',
                '-hwaccel', 'vaapi', '-hwaccel_device', $device, '-hwaccel_output_format', 'vaapi',
                '-i', $input,
                '-map', '0:v:0?', '-map', '0:a:0?',
                '-vf', 'format=nv12|vaapi,hwupload',
                '-c:v', $encoder,
                '-c:a', 'aac', '-b:a', '192k',
                '-movflags', '+faststart',
                $output,
            ];
        }

        // Software encode.
        $encoder = $targetVideo === 'hevc' ? 'libx265' : 'libx264';
        $crf ??= $targetVideo === 'hevc' ? 26 : 21;

        $args = [
            'ffmpeg', '-y', '-i', $input,
            '-map', '0:v:0?', '-map', '0:a:0?',
            '-c:v', $encoder, '-crf', (string) $crf, '-preset', 'medium', '-pix_fmt', 'yuv420p',
        ];
        if ($targetVideo === 'hevc') {
            $args[] = '-tag:v';
            $args[] = 'hvc1'; // broad player compatibility for HEVC-in-mp4
        }

        return array_merge($args, [
            '-c:a', 'aac', '-b:a', '192k',
            '-movflags', '+faststart',
            $output,
        ]);
    }
}
