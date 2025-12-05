<?php

use App\Services\UploadService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::forget('upload_queue');
});

describe('UploadService static methods', function () {
    describe('getSupportedArchiveFormats', function () {
        it('returns array of supported archive formats', function () {
            $formats = UploadService::getSupportedArchiveFormats();

            expect($formats)->toBeArray();
            expect($formats)->toContain('zip');
            expect($formats)->toContain('tar');
            expect($formats)->toContain('tar.gz');
            expect($formats)->toContain('tgz');
            expect($formats)->toContain('tar.bz2');
            expect($formats)->toContain('tbz2');
            expect($formats)->toContain('7z');
            expect($formats)->toContain('rar');
        });
    });

    describe('getSupportedVideoExtensions', function () {
        it('returns array of supported video extensions', function () {
            $extensions = UploadService::getSupportedVideoExtensions();

            expect($extensions)->toBeArray();
            expect($extensions)->toContain('mp4');
            expect($extensions)->toContain('mkv');
            expect($extensions)->toContain('avi');
            expect($extensions)->toContain('mov');
            expect($extensions)->toContain('wmv');
            expect($extensions)->toContain('flv');
            expect($extensions)->toContain('webm');
            expect($extensions)->toContain('m4v');
            expect($extensions)->toContain('mpg');
            expect($extensions)->toContain('mpeg');
            expect($extensions)->toContain('3gp');
            expect($extensions)->toContain('ogv');
        });
    });

    describe('isArchive', function () {
        it('returns true for zip files', function () {
            expect(UploadService::isArchive('video.zip'))->toBeTrue();
            expect(UploadService::isArchive('archive.ZIP'))->toBeTrue();
        });

        it('returns true for tar files', function () {
            expect(UploadService::isArchive('archive.tar'))->toBeTrue();
        });

        it('returns true for tar.gz files', function () {
            expect(UploadService::isArchive('archive.tar.gz'))->toBeTrue();
            expect(UploadService::isArchive('archive.tgz'))->toBeTrue();
        });

        it('returns true for tar.bz2 files', function () {
            expect(UploadService::isArchive('archive.tar.bz2'))->toBeTrue();
            expect(UploadService::isArchive('archive.tbz2'))->toBeTrue();
        });

        it('returns true for 7z files', function () {
            expect(UploadService::isArchive('archive.7z'))->toBeTrue();
        });

        it('returns true for rar files', function () {
            expect(UploadService::isArchive('archive.rar'))->toBeTrue();
        });

        it('returns false for video files', function () {
            expect(UploadService::isArchive('video.mp4'))->toBeFalse();
            expect(UploadService::isArchive('video.mkv'))->toBeFalse();
            expect(UploadService::isArchive('video.avi'))->toBeFalse();
        });

        it('returns false for image files', function () {
            expect(UploadService::isArchive('image.jpg'))->toBeFalse();
            expect(UploadService::isArchive('image.png'))->toBeFalse();
        });

        it('returns false for files with no extension', function () {
            expect(UploadService::isArchive('noextension'))->toBeFalse();
        });
    });

    describe('isVideoFile', function () {
        it('returns true for mp4 files', function () {
            expect(UploadService::isVideoFile('video.mp4'))->toBeTrue();
            expect(UploadService::isVideoFile('video.MP4'))->toBeTrue();
        });

        it('returns true for mkv files', function () {
            expect(UploadService::isVideoFile('video.mkv'))->toBeTrue();
        });

        it('returns true for avi files', function () {
            expect(UploadService::isVideoFile('video.avi'))->toBeTrue();
        });

        it('returns true for mov files', function () {
            expect(UploadService::isVideoFile('video.mov'))->toBeTrue();
        });

        it('returns true for wmv files', function () {
            expect(UploadService::isVideoFile('video.wmv'))->toBeTrue();
        });

        it('returns true for flv files', function () {
            expect(UploadService::isVideoFile('video.flv'))->toBeTrue();
        });

        it('returns true for webm files', function () {
            expect(UploadService::isVideoFile('video.webm'))->toBeTrue();
        });

        it('returns true for m4v files', function () {
            expect(UploadService::isVideoFile('video.m4v'))->toBeTrue();
        });

        it('returns true for mpg and mpeg files', function () {
            expect(UploadService::isVideoFile('video.mpg'))->toBeTrue();
            expect(UploadService::isVideoFile('video.mpeg'))->toBeTrue();
        });

        it('returns true for 3gp files', function () {
            expect(UploadService::isVideoFile('video.3gp'))->toBeTrue();
        });

        it('returns true for ogv files', function () {
            expect(UploadService::isVideoFile('video.ogv'))->toBeTrue();
        });

        it('returns false for archive files', function () {
            expect(UploadService::isVideoFile('archive.zip'))->toBeFalse();
            expect(UploadService::isVideoFile('archive.rar'))->toBeFalse();
        });

        it('returns false for image files', function () {
            expect(UploadService::isVideoFile('image.jpg'))->toBeFalse();
            expect(UploadService::isVideoFile('image.png'))->toBeFalse();
            expect(UploadService::isVideoFile('image.gif'))->toBeFalse();
        });

        it('returns false for audio files', function () {
            expect(UploadService::isVideoFile('audio.mp3'))->toBeFalse();
            expect(UploadService::isVideoFile('audio.wav'))->toBeFalse();
        });

        it('returns false for files with no extension', function () {
            expect(UploadService::isVideoFile('noextension'))->toBeFalse();
        });
    });
});

describe('UploadService queue operations', function () {
    it('adds items to the queue', function () {
        $service = new UploadService;

        $service->addToQueue('upload-id-1', 'video1.mp4', 'video/mp4');
        $service->addToQueue('upload-id-2', 'video2.webm', 'video/webm');

        $queue = $service->getQueue();

        expect($queue)->toHaveCount(2);
        expect($queue[0]['id'])->toBe('upload-id-1');
        expect($queue[0]['filename'])->toBe('video1.mp4');
        expect($queue[0]['mime_type'])->toBe('video/mp4');
        expect($queue[0]['status'])->toBe('queued');
        expect($queue[1]['id'])->toBe('upload-id-2');
    });

    it('updates queue item status', function () {
        $service = new UploadService;

        $service->addToQueue('upload-id', 'video.mp4', 'video/mp4');
        $service->updateStatus('upload-id', 'processing', ['progress' => 75]);

        $queue = $service->getQueue();

        expect($queue[0]['status'])->toBe('processing');
        expect($queue[0]['progress'])->toBe(75);
    });

    it('removes items from the queue', function () {
        $service = new UploadService;

        $service->addToQueue('upload-id-1', 'video1.mp4', 'video/mp4');
        $service->addToQueue('upload-id-2', 'video2.mp4', 'video/mp4');

        $service->removeFromQueue('upload-id-1');

        $queue = $service->getQueue();

        expect($queue)->toHaveCount(1);
        expect($queue[0]['id'])->toBe('upload-id-2');
    });

    it('clears the entire queue', function () {
        $service = new UploadService;

        $service->addToQueue('upload-id-1', 'video1.mp4', 'video/mp4');
        $service->addToQueue('upload-id-2', 'video2.mp4', 'video/mp4');

        $service->clearQueue();

        expect($service->getQueue())->toBeEmpty();
    });

    it('returns empty array when queue is empty', function () {
        $service = new UploadService;

        expect($service->getQueue())->toBeArray();
        expect($service->getQueue())->toBeEmpty();
    });

    it('preserves queue item data when updating status', function () {
        $service = new UploadService;

        $service->addToQueue('upload-id', 'test-video.mp4', 'video/mp4');
        $service->updateStatus('upload-id', 'processing', ['progress' => 25]);

        $queue = $service->getQueue();

        expect($queue[0]['filename'])->toBe('test-video.mp4');
        expect($queue[0]['mime_type'])->toBe('video/mp4');
        expect($queue[0]['status'])->toBe('processing');
        expect($queue[0]['progress'])->toBe(25);
    });
});
