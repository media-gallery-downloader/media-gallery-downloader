<?php

use App\Models\Media;
use App\Services\MediaProbeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

function putEncoded(string $diskPath, string $encoder): void
{
    $abs = Storage::disk('public')->path($diskPath);
    @mkdir(dirname($abs), 0777, true);
    (new Process([
        'ffmpeg', '-y', '-f', 'lavfi', '-i', 'testsrc=duration=1:size=128x96:rate=5',
        '-c:v', $encoder, '-pix_fmt', 'yuv420p', $abs,
    ]))->run();
}

function videoCodec(string $diskPath): ?string
{
    return (new MediaProbeService)->codecs(Storage::disk('public')->path($diskPath))['video'];
}

$ffmpegMissing = ! @is_executable(trim((string) shell_exec('command -v ffmpeg')));

describe('media:reencode', function () {
    beforeEach(fn () => Storage::fake('public'));

    it('compatibility mode re-encodes a non-baseline codec to h264', function () {
        putEncoded('media/legacy.mp4', 'mpeg4'); // not in baseline
        Media::factory()->create(['path' => 'media/legacy.mp4', 'file_name' => 'legacy.mp4', 'mime_type' => 'video/mp4']);

        $this->artisan('media:reencode')->assertSuccessful();

        expect(videoCodec('media/legacy.mp4'))->toBe('h264');
    });

    it('shrink mode re-encodes baseline h264 to hevc', function () {
        putEncoded('media/big.mp4', 'libx264'); // h264, in baseline
        Media::factory()->create(['path' => 'media/big.mp4', 'file_name' => 'big.mp4', 'mime_type' => 'video/mp4']);

        $this->artisan('media:reencode', ['--shrink' => true, '--to' => 'hevc', '--min-size' => '0'])->assertSuccessful();

        expect(videoCodec('media/big.mp4'))->toBe('hevc');
    });

    it('--shrink respects --min-size (skips small files)', function () {
        putEncoded('media/small.mp4', 'libx264');
        Media::factory()->create(['path' => 'media/small.mp4', 'file_name' => 'small.mp4', 'mime_type' => 'video/mp4']);

        $this->artisan('media:reencode', ['--shrink' => true, '--min-size' => '1G'])->assertSuccessful();

        expect(videoCodec('media/small.mp4'))->toBe('h264'); // untouched
    });

    it('--dry-run changes nothing', function () {
        putEncoded('media/legacy2.mp4', 'mpeg4');
        Media::factory()->create(['path' => 'media/legacy2.mp4', 'file_name' => 'legacy2.mp4', 'mime_type' => 'video/mp4']);

        $this->artisan('media:reencode', ['--dry-run' => true])->assertSuccessful();

        expect(videoCodec('media/legacy2.mp4'))->toBe('mpeg4'); // untouched
    });
})->skip($ffmpegMissing, 'ffmpeg not available');
