<?php

use App\Models\Media;
use App\Services\FaststartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

function putNonFaststartMp4(string $diskPath): void
{
    $abs = Storage::disk('public')->path($diskPath);
    @mkdir(dirname($abs), 0777, true);
    (new Process([
        'ffmpeg', '-y', '-f', 'lavfi', '-i', 'testsrc=duration=1:size=128x96:rate=5',
        '-c:v', 'mpeg4', '-pix_fmt', 'yuv420p', $abs,
    ]))->run();
}

describe('media:remux-faststart', function () {
    beforeEach(fn () => Storage::fake('public'));

    it('faststarts an mp4 whose moov atom is at the end', function () {
        putNonFaststartMp4('media/clip-1.mp4');
        $media = Media::factory()->create(['file_name' => 'clip-1.mp4', 'path' => 'media/clip-1.mp4', 'mime_type' => 'video/mp4']);

        $this->artisan('media:remux-faststart')->assertSuccessful();

        $svc = app(FaststartService::class);
        expect($svc->isFaststarted(Storage::disk('public')->path('media/clip-1.mp4')))->toBeTrue()
            ->and($media->fresh()->size)->toBe(Storage::disk('public')->size('media/clip-1.mp4'));
    })->skip(! @is_executable(trim((string) shell_exec('command -v ffmpeg'))), 'ffmpeg not available');

    it('skips non-mp4 containers and missing files, and is idempotent', function () {
        Storage::disk('public')->put('media/clip.webm', 'webm bytes'); // unsupported container
        Media::factory()->create(['file_name' => 'clip.webm', 'path' => 'media/clip.webm']);
        Media::factory()->create(['file_name' => 'gone.mp4', 'path' => 'media/gone.mp4']); // no file on disk

        // No throw, completes cleanly.
        $this->artisan('media:remux-faststart')->assertSuccessful();
    });

    it('--dry-run changes nothing', function () {
        putNonFaststartMp4('media/clip-2.mp4');
        $media = Media::factory()->create(['file_name' => 'clip-2.mp4', 'path' => 'media/clip-2.mp4', 'mime_type' => 'video/mp4']);
        $before = Storage::disk('public')->size('media/clip-2.mp4');

        $this->artisan('media:remux-faststart', ['--dry-run' => true])->assertSuccessful();

        $svc = app(FaststartService::class);
        expect($svc->isFaststarted(Storage::disk('public')->path('media/clip-2.mp4')))->toBeFalse()
            ->and(Storage::disk('public')->size('media/clip-2.mp4'))->toBe($before);
    })->skip(! @is_executable(trim((string) shell_exec('command -v ffmpeg'))), 'ffmpeg not available');
});
