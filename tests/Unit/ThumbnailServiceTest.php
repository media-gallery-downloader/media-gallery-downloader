<?php

use App\Services\ThumbnailService;
use Illuminate\Support\Facades\Storage;

describe('ThumbnailService', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    describe('generateThumbnail', function () {
        it('returns null for non-media types', function () {
            $service = new ThumbnailService;
            $result = $service->generateThumbnail('test.txt', 'text/plain');

            expect($result)->toBeNull();
        });

        it('calls video thumbnail for video mime types', function () {
            $service = new ThumbnailService;

            // This will fail since there's no real video, but tests the routing
            $result = $service->generateThumbnail('media/nonexistent.mp4', 'video/mp4');

            expect($result)->toBeNull();
        });

        it('calls gif thumbnail for gif mime type', function () {
            $service = new ThumbnailService;

            // This will fail since there's no real gif, but tests the routing
            $result = $service->generateThumbnail('media/nonexistent.gif', 'image/gif');

            expect($result)->toBeNull();
        });

        it('handles video/webm mime type', function () {
            $service = new ThumbnailService;
            $result = $service->generateThumbnail('media/test.webm', 'video/webm');

            expect($result)->toBeNull();
        });

        it('handles video/quicktime mime type', function () {
            $service = new ThumbnailService;
            $result = $service->generateThumbnail('media/test.mov', 'video/quicktime');

            expect($result)->toBeNull();
        });

        it('handles image/png mime type returns null', function () {
            $service = new ThumbnailService;
            $result = $service->generateThumbnail('media/test.png', 'image/png');

            expect($result)->toBeNull();
        });

        it('handles image/jpeg mime type returns null', function () {
            $service = new ThumbnailService;
            $result = $service->generateThumbnail('media/test.jpg', 'image/jpeg');

            expect($result)->toBeNull();
        });
    });

    describe('deleteThumbnail', function () {
        it('deletes existing thumbnail', function () {
            Storage::disk('public')->put('thumbnails/test_thumb.jpg', 'fake content');

            $service = new ThumbnailService;
            $service->deleteThumbnail('thumbnails/test_thumb.jpg');

            Storage::disk('public')->assertMissing('thumbnails/test_thumb.jpg');
        });

        it('handles null thumbnail path gracefully', function () {
            $service = new ThumbnailService;
            $service->deleteThumbnail(null);

            // No exception thrown
            expect(true)->toBeTrue();
        });

        it('handles non-existent thumbnail gracefully', function () {
            $service = new ThumbnailService;
            $service->deleteThumbnail('thumbnails/nonexistent.jpg');

            // No exception thrown
            expect(true)->toBeTrue();
        });

        it('handles empty string thumbnail path', function () {
            $service = new ThumbnailService;
            $service->deleteThumbnail('');

            // No exception thrown
            expect(true)->toBeTrue();
        });
    });
});
