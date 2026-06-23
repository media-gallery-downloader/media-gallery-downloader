<?php

namespace App\Services;

use Symfony\Component\Process\Process;

/**
 * Reads the first video and audio codec of a media file via ffprobe.
 */
class MediaProbeService
{
    /**
     * @return array{video: ?string, audio: ?string}
     */
    public function codecs(string $absolutePath): array
    {
        $result = ['video' => null, 'audio' => null];

        if (! is_file($absolutePath)) {
            return $result;
        }

        $process = new Process([
            'ffprobe', '-v', 'error',
            '-show_entries', 'stream=codec_type,codec_name',
            '-of', 'json',
            $absolutePath,
        ]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            return $result;
        }

        $data = json_decode($process->getOutput(), true);
        foreach ($data['streams'] ?? [] as $stream) {
            $type = $stream['codec_type'] ?? null;
            $name = $stream['codec_name'] ?? null;
            if ($type === 'video' && $result['video'] === null) {
                $result['video'] = $name;
            }
            if ($type === 'audio' && $result['audio'] === null) {
                $result['audio'] = $name;
            }
        }

        return $result;
    }
}
