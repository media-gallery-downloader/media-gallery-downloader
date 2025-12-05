<?php

use App\Http\Controllers\Api\MediaController;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('Api MediaController', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    describe('destroy method', function () {
        it('deletes a media record and its file', function () {
            // Create a fake file
            Storage::disk('public')->put('media/test-video.mp4', 'fake video content');

            // Create a media record
            $media = Media::create([
                'name' => 'Test Video',
                'mime_type' => 'video/mp4',
                'size' => 1000,
                'file_name' => 'test-video.mp4',
                'path' => 'media/test-video.mp4',
                'url' => '/storage/media/test-video.mp4',
            ]);

            // Verify file exists
            Storage::disk('public')->assertExists('media/test-video.mp4');

            // Call controller directly
            $controller = new MediaController;
            $response = $controller->destroy($media);

            expect($response->getData()->success)->toBeTrue();

            // Verify record is deleted
            $this->assertDatabaseMissing('media', ['id' => $media->id]);

            // Verify file is deleted
            Storage::disk('public')->assertMissing('media/test-video.mp4');
        });

        it('deletes a media record even if file does not exist', function () {
            // Create a media record without actual file
            $media = Media::create([
                'name' => 'Test Video',
                'mime_type' => 'video/mp4',
                'size' => 1000,
                'file_name' => 'nonexistent.mp4',
                'path' => 'media/nonexistent.mp4',
                'url' => '/storage/media/nonexistent.mp4',
            ]);

            // Call controller directly
            $controller = new MediaController;
            $response = $controller->destroy($media);

            expect($response->getData()->success)->toBeTrue();

            // Verify record is deleted
            $this->assertDatabaseMissing('media', ['id' => $media->id]);
        });

        it('deletes media with thumbnail', function () {
            // Create fake files
            Storage::disk('public')->put('media/test-video.mp4', 'fake video content');
            Storage::disk('public')->put('thumbnails/test-video_thumb.jpg', 'fake thumbnail');

            // Create a media record with thumbnail
            $media = Media::create([
                'name' => 'Test Video',
                'mime_type' => 'video/mp4',
                'size' => 1000,
                'file_name' => 'test-video.mp4',
                'path' => 'media/test-video.mp4',
                'url' => '/storage/media/test-video.mp4',
                'thumbnail_path' => 'thumbnails/test-video_thumb.jpg',
            ]);

            // Call controller directly
            $controller = new MediaController;
            $response = $controller->destroy($media);

            expect($response->getData()->success)->toBeTrue();

            // Verify record is deleted
            $this->assertDatabaseMissing('media', ['id' => $media->id]);

            // Verify both files are deleted (via model deleting event)
            Storage::disk('public')->assertMissing('media/test-video.mp4');
            Storage::disk('public')->assertMissing('thumbnails/test-video_thumb.jpg');
        });
    });
});
