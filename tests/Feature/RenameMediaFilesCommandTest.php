<?php

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Seed a UUID-named media record with its file (and optionally thumbnail) on the
 * fake public disk. Returns the record.
 */
function seedUuidMedia(string $name = 'My Day at the Zoo', int $ts = 1749134400, bool $withThumb = true): Media
{
    $uuid = (string) Str::uuid();
    Storage::disk('public')->put("media/{$uuid}.mp4", 'video-bytes');

    $attributes = [
        'name' => $name,
        'file_name' => "{$uuid}.mp4",
        'path' => "media/{$uuid}.mp4",
        'url' => "/storage/media/{$uuid}.mp4",
        'thumbnail_path' => null,
        'created_at' => Carbon::createFromTimestamp($ts),
    ];

    if ($withThumb) {
        Storage::disk('public')->put("thumbnails/{$uuid}_thumb.jpg", 'thumb-bytes');
        $attributes['thumbnail_path'] = "thumbnails/{$uuid}_thumb.jpg";
    }

    return Media::factory()->create($attributes);
}

describe('media:rename-files', function () {
    beforeEach(fn () => Storage::fake('public'));

    it('renames a uuid record and its thumbnail to title-timestamp form', function () {
        $media = seedUuidMedia();
        $oldFile = $media->path;
        $oldThumb = $media->thumbnail_path;

        $this->artisan('media:rename-files')->assertSuccessful();

        $media->refresh();
        expect($media->file_name)->toBe('My Day at the Zoo-1749134400.mp4')
            ->and($media->path)->toBe('media/My Day at the Zoo-1749134400.mp4')
            ->and($media->url)->toContain('media/My%20Day%20at%20the%20Zoo-1749134400.mp4')
            ->and($media->thumbnail_path)->toBe('thumbnails/My Day at the Zoo-1749134400_thumb.jpg');

        expect(Storage::disk('public')->exists($media->path))->toBeTrue()
            ->and(Storage::disk('public')->exists($oldFile))->toBeFalse()
            ->and(Storage::disk('public')->exists($media->thumbnail_path))->toBeTrue()
            ->and(Storage::disk('public')->exists($oldThumb))->toBeFalse();
    });

    it('is idempotent - a second run renames nothing', function () {
        $media = seedUuidMedia();

        $this->artisan('media:rename-files');
        $afterFirst = $media->refresh()->file_name;

        $this->artisan('media:rename-files');
        expect($media->refresh()->file_name)->toBe($afterFirst);
    });

    it('--dry-run changes nothing on disk or in the database', function () {
        $media = seedUuidMedia();
        $originalFile = $media->file_name;
        $originalPath = $media->path;

        $this->artisan('media:rename-files', ['--dry-run' => true])->assertSuccessful();

        expect($media->refresh()->file_name)->toBe($originalFile)
            ->and(Storage::disk('public')->exists($originalPath))->toBeTrue();
    });

    it('skips a record whose source file is missing', function () {
        $media = seedUuidMedia();
        Storage::disk('public')->delete($media->path); // file gone
        $originalFile = $media->file_name;

        $this->artisan('media:rename-files')->assertSuccessful();

        expect($media->refresh()->file_name)->toBe($originalFile); // unchanged
    });

    it('resolves collisions for records sharing a title and timestamp', function () {
        seedUuidMedia('Clip', 1749134400, withThumb: false);
        seedUuidMedia('Clip', 1749134400, withThumb: false);

        $this->artisan('media:rename-files')->assertSuccessful();

        $names = Media::pluck('file_name')->sort()->values()->all();
        expect($names)->toBe(['Clip-1749134400-2.mp4', 'Clip-1749134400.mp4']);
    });

    it('handles titles with zero-width emoji joiners without a corrupted-path error', function () {
        // Regression: Flysystem throws CorruptedPathDetected on paths containing
        // \p{C} (e.g. the ZWJ in 🏃‍♀️). The fake disk uses Flysystem too.
        $media = seedUuidMedia("I made QT sprint \u{1F3C3}\u{200D}\u{2640}\u{FE0F} #maya", 1774404799, withThumb: false);

        $this->artisan('media:rename-files')->assertSuccessful();

        $media->refresh();
        expect(preg_match('/\p{C}/u', $media->file_name))->toBe(0)
            ->and($media->file_name)->toEndWith('-1774404799.mp4')
            ->and(Storage::disk('public')->exists($media->path))->toBeTrue();
    });

    it('leaves an already-renamed record alone', function () {
        Storage::disk('public')->put('media/Already Named-1749134400.mp4', 'x');
        $media = Media::factory()->create([
            'name' => 'Already Named',
            'file_name' => 'Already Named-1749134400.mp4',
            'path' => 'media/Already Named-1749134400.mp4',
            'thumbnail_path' => null,
        ]);

        $this->artisan('media:rename-files')->assertSuccessful();

        expect($media->refresh()->file_name)->toBe('Already Named-1749134400.mp4');
    });
});
