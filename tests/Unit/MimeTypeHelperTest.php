<?php

use App\Helpers\MimeTypeHelper;

describe('MimeTypeHelper', function () {
    describe('getExtensionFromMimeType', function () {
        it('returns extension for video/mp4', function () {
            expect(MimeTypeHelper::getExtensionFromMimeType('video/mp4'))->toBe('mp4');
        });

        it('returns extension for video/webm', function () {
            expect(MimeTypeHelper::getExtensionFromMimeType('video/webm'))->toBe('webm');
        });

        it('returns extension for video/quicktime', function () {
            expect(MimeTypeHelper::getExtensionFromMimeType('video/quicktime'))->toBe('mov');
        });

        it('returns extension for image/jpeg', function () {
            expect(MimeTypeHelper::getExtensionFromMimeType('image/jpeg'))->toBe('jpg');
        });

        it('returns extension for image/png', function () {
            expect(MimeTypeHelper::getExtensionFromMimeType('image/png'))->toBe('png');
        });

        it('returns extension for image/gif', function () {
            expect(MimeTypeHelper::getExtensionFromMimeType('image/gif'))->toBe('gif');
        });

        it('returns extension for audio/mpeg', function () {
            expect(MimeTypeHelper::getExtensionFromMimeType('audio/mpeg'))->toBe('mp3');
        });

        it('returns empty string for unknown mime type', function () {
            expect(MimeTypeHelper::getExtensionFromMimeType('application/unknown-type-xyz'))->toBe('');
        });
    });

    describe('getMimeTypeFromExtension', function () {
        it('returns mime type for mp4', function () {
            expect(MimeTypeHelper::getMimeTypeFromExtension('mp4'))->toBe('video/mp4');
        });

        it('returns mime type for webm', function () {
            expect(MimeTypeHelper::getMimeTypeFromExtension('webm'))->toBe('video/webm');
        });

        it('returns mime type for mov', function () {
            expect(MimeTypeHelper::getMimeTypeFromExtension('mov'))->toBe('video/quicktime');
        });

        it('returns mime type for mkv', function () {
            expect(MimeTypeHelper::getMimeTypeFromExtension('mkv'))->toBe('video/x-matroska');
        });

        it('returns mime type for jpg', function () {
            expect(MimeTypeHelper::getMimeTypeFromExtension('jpg'))->toBe('image/jpeg');
        });

        it('returns mime type for png', function () {
            expect(MimeTypeHelper::getMimeTypeFromExtension('png'))->toBe('image/png');
        });

        it('returns mime type for gif', function () {
            expect(MimeTypeHelper::getMimeTypeFromExtension('gif'))->toBe('image/gif');
        });

        it('returns mime type for mp3', function () {
            expect(MimeTypeHelper::getMimeTypeFromExtension('mp3'))->toBe('audio/mpeg');
        });

        it('returns empty string for unknown extension', function () {
            expect(MimeTypeHelper::getMimeTypeFromExtension('xyz123'))->toBe('');
        });
    });

    describe('isMedia', function () {
        it('returns true for video mime types', function () {
            expect(MimeTypeHelper::isMedia('video/mp4'))->toBeTrue();
            expect(MimeTypeHelper::isMedia('video/webm'))->toBeTrue();
            expect(MimeTypeHelper::isMedia('video/quicktime'))->toBeTrue();
            expect(MimeTypeHelper::isMedia('video/x-matroska'))->toBeTrue();
        });

        it('returns true for image mime types', function () {
            expect(MimeTypeHelper::isMedia('image/jpeg'))->toBeTrue();
            expect(MimeTypeHelper::isMedia('image/png'))->toBeTrue();
            expect(MimeTypeHelper::isMedia('image/gif'))->toBeTrue();
            expect(MimeTypeHelper::isMedia('image/webp'))->toBeTrue();
        });

        it('returns true for audio mime types', function () {
            expect(MimeTypeHelper::isMedia('audio/mpeg'))->toBeTrue();
            expect(MimeTypeHelper::isMedia('audio/wav'))->toBeTrue();
            expect(MimeTypeHelper::isMedia('audio/ogg'))->toBeTrue();
        });

        it('returns false for non-media mime types', function () {
            expect(MimeTypeHelper::isMedia('application/pdf'))->toBeFalse();
            expect(MimeTypeHelper::isMedia('text/html'))->toBeFalse();
            expect(MimeTypeHelper::isMedia('application/json'))->toBeFalse();
            expect(MimeTypeHelper::isMedia('application/zip'))->toBeFalse();
        });

        it('returns false for empty mime type', function () {
            expect(MimeTypeHelper::isMedia(''))->toBeFalse();
        });

        it('returns false for null-like values', function () {
            expect(MimeTypeHelper::isMedia(''))->toBeFalse();
        });
    });
});
