<?php

use App\Models\Media;
use App\Services\Download\BaseDownloadHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function storingHandler(): BaseDownloadHandler
{
    return new class extends BaseDownloadHandler
    {
        public function getName(): string
        {
            return 'test';
        }

        public function canHandle(string $url): bool
        {
            return true;
        }

        public function download(string $url, string $downloadId, ?callable $progressCallback = null): Media
        {
            throw new RuntimeException('not used in test');
        }

        public function storePublic(string $file, string $name, string $url, ?string $mime): Media
        {
            return $this->storeAndCreateMedia($file, $name, $url, $mime);
        }
    };
}

it('stores a downloaded file with a title-timestamp name and an encoded url', function () {
    Storage::fake('public');
    Queue::fake();

    $src = tempnam(sys_get_temp_dir(), 'mgd');
    file_put_contents($src, 'fake video bytes');
    $mp4 = $src.'.mp4';
    rename($src, $mp4);

    $media = storingHandler()->storePublic($mp4, 'My Day at the Zoo', 'https://example.com/v', 'video/mp4');

    expect($media->file_name)->toMatch('/^My Day at the Zoo-\d{10}\.mp4$/')
        ->and($media->path)->toBe('media/'.$media->file_name)
        ->and($media->url)->toContain('media/My%20Day%20at%20the%20Zoo-')
        ->and($media->url)->not->toContain(' ')
        ->and(Storage::disk('public')->exists($media->path))->toBeTrue();

    @unlink($mp4);
});

it('url-encodes the thumbnail url accessor', function () {
    Storage::fake('public');

    $media = Media::factory()->create(['thumbnail_path' => 'thumbnails/My Day-1749134400_thumb.jpg']);

    expect($media->thumbnail_url)->toContain('thumbnails/My%20Day-1749134400_thumb.jpg')
        ->and($media->thumbnail_url)->not->toContain(' ');
});
