<?php

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

describe('Media Model', function () {
    it('can be created with fillable attributes', function () {
        $media = Media::create([
            'name' => 'Test Video',
            'mime_type' => 'video/mp4',
            'size' => 1024000,
            'file_name' => 'test-video.mp4',
            'path' => 'media/test-video.mp4',
            'url' => '/storage/media/test-video.mp4',
            'source' => 'https://example.com/video.mp4',
        ]);

        expect($media)->toBeInstanceOf(Media::class);
        expect($media->name)->toBe('Test Video');
        expect($media->mime_type)->toBe('video/mp4');
        expect($media->size)->toBe(1024000);
    });

    it('returns null thumbnail url when no thumbnail', function () {
        $media = Media::create([
            'name' => 'Test Video',
            'mime_type' => 'video/mp4',
            'size' => 1024000,
            'file_name' => 'test-video.mp4',
            'path' => 'media/test-video.mp4',
            'url' => '/storage/media/test-video.mp4',
        ]);

        expect($media->thumbnail_url)->toBeNull();
    });

    it('returns thumbnail url when thumbnail exists', function () {
        $media = Media::create([
            'name' => 'Test Video',
            'mime_type' => 'video/mp4',
            'size' => 1024000,
            'file_name' => 'test-video.mp4',
            'path' => 'media/test-video.mp4',
            'url' => '/storage/media/test-video.mp4',
            'thumbnail_path' => 'thumbnails/test-video.jpg',
        ]);

        expect($media->thumbnail_url)->not->toBeNull();
    });

    it('needs thumbnail for video files', function () {
        $media = new Media(['mime_type' => 'video/mp4']);
        expect($media->needsThumbnail())->toBeTrue();

        $media = new Media(['mime_type' => 'video/webm']);
        expect($media->needsThumbnail())->toBeTrue();

        $media = new Media(['mime_type' => 'video/quicktime']);
        expect($media->needsThumbnail())->toBeTrue();
    });

    it('needs thumbnail for gif images', function () {
        $media = new Media(['mime_type' => 'image/gif']);
        expect($media->needsThumbnail())->toBeTrue();
    });

    it('does not need thumbnail for static images', function () {
        $media = new Media(['mime_type' => 'image/jpeg']);
        expect($media->needsThumbnail())->toBeFalse();

        $media = new Media(['mime_type' => 'image/png']);
        expect($media->needsThumbnail())->toBeFalse();
    });

    it('deletes files when model is deleted', function () {
        Storage::disk('public')->put('media/test-video.mp4', 'video content');
        Storage::disk('public')->put('thumbnails/test-video.jpg', 'thumbnail content');

        $media = Media::create([
            'name' => 'Test Video',
            'mime_type' => 'video/mp4',
            'size' => 1024000,
            'file_name' => 'test-video.mp4',
            'path' => 'media/test-video.mp4',
            'url' => '/storage/media/test-video.mp4',
            'thumbnail_path' => 'thumbnails/test-video.jpg',
        ]);

        expect(Storage::disk('public')->exists('media/test-video.mp4'))->toBeTrue();
        expect(Storage::disk('public')->exists('thumbnails/test-video.jpg'))->toBeTrue();

        $media->delete();

        expect(Storage::disk('public')->exists('media/test-video.mp4'))->toBeFalse();
        expect(Storage::disk('public')->exists('thumbnails/test-video.jpg'))->toBeFalse();
    });
});
