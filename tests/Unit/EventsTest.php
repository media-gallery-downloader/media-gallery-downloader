<?php

use App\Events\DownloadCompleted;
use App\Events\DownloadFailed;
use App\Events\DownloadStarted;

describe('DownloadCompleted Event', function () {
    it('can be instantiated with downloadId and mediaId', function () {
        $event = new DownloadCompleted('download-123', 456);

        expect($event->downloadId)->toBe('download-123');
        expect($event->mediaId)->toBe(456);
    });
});

describe('DownloadFailed Event', function () {
    it('can be instantiated with downloadId and error', function () {
        $event = new DownloadFailed('download-123', 'Connection timeout');

        expect($event->downloadId)->toBe('download-123');
        expect($event->error)->toBe('Connection timeout');
    });
});

describe('DownloadStarted Event', function () {
    it('can be instantiated with downloadId and url', function () {
        $event = new DownloadStarted('download-123', 'https://example.com/video.mp4');

        expect($event->downloadId)->toBe('download-123');
        expect($event->url)->toBe('https://example.com/video.mp4');
    });
});
