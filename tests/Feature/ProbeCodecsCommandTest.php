<?php

use App\Models\Media;
use App\Services\MediaProbeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

function putVideoCodec(string $diskPath, string $encoder): void
{
    $abs = Storage::disk('public')->path($diskPath);
    @mkdir(dirname($abs), 0777, true);
    (new Process([
        'ffmpeg', '-y', '-f', 'lavfi', '-i', 'testsrc=duration=1:size=128x96:rate=5',
        '-c:v', $encoder, '-pix_fmt', 'yuv420p', $abs,
    ]))->run();
}

$ffmpegMissing = ! @is_executable(trim((string) shell_exec('command -v ffmpeg')));

describe('codec probing', function () {
    beforeEach(fn () => Storage::fake('public'));

    it('reads the video codec via ffprobe', function () {
        putVideoCodec('media/a.mp4', 'mpeg4');
        putVideoCodec('media/b.mp4', 'libx264');

        $probe = new MediaProbeService;

        expect($probe->codecs(Storage::disk('public')->path('media/a.mp4'))['video'])->toBe('mpeg4')
            ->and($probe->codecs(Storage::disk('public')->path('media/b.mp4'))['video'])->toBe('h264');
    });

    it('flags files whose codec is outside the baseline', function () {
        putVideoCodec('media/legacy.mp4', 'mpeg4');   // not in baseline
        putVideoCodec('media/modern.mp4', 'libx264'); // h264 -> baseline
        Media::factory()->create(['path' => 'media/legacy.mp4', 'file_name' => 'legacy.mp4', 'mime_type' => 'video/mp4']);
        Media::factory()->create(['path' => 'media/modern.mp4', 'file_name' => 'modern.mp4', 'mime_type' => 'video/mp4']);

        $this->artisan('media:probe-codecs')
            ->assertSuccessful()
            ->expectsOutputToContain('legacy.mp4');
    });
})->skip($ffmpegMissing, 'ffmpeg not available');
