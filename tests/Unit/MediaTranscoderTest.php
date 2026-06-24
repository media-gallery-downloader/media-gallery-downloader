<?php

use App\Services\MediaTranscoder;

function cmd(string $target, string $accel, ?int $crf = null): array
{
    return (new MediaTranscoder)->command('in.mkv', 'out.mp4', $target, $accel, $crf);
}

describe('MediaTranscoder::command', function () {
    it('software h264 uses libx264 + crf, faststart, no hvc1 tag', function () {
        $c = cmd('h264', 'none', 21);

        expect($c)->toContain('libx264')
            ->and($c)->toContain('-crf')->toContain('21')
            ->and($c)->toContain('+faststart')
            ->and($c)->not->toContain('hvc1');
    });

    it('software hevc uses libx265 + hvc1 tag', function () {
        $c = cmd('hevc', 'none');

        expect($c)->toContain('libx265')->toContain('-tag:v')->toContain('hvc1');
    });

    it('vaapi uses the vaapi encoder, hwupload filter and device', function () {
        $c = cmd('h264', 'vaapi');

        expect($c)->toContain('h264_vaapi')
            ->and($c)->toContain('-hwaccel')->toContain('vaapi')
            ->and($c)->toContain('format=nv12|vaapi,hwupload');
    });

    it('nvenc uses the nvenc encoder + cq quality', function () {
        $c = cmd('hevc', 'nvenc', 24);

        expect($c)->toContain('hevc_nvenc')
            ->and($c)->toContain('-cq')->toContain('24')
            ->and($c)->toContain('hvc1');
    });
});
