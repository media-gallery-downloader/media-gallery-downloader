<?php

use App\Events\DownloadCompleted;
use App\Events\DownloadFailed;
use App\Listeners\HandleDownloadCompleted;
use App\Listeners\HandleDownloadFailed;
use Illuminate\Support\Facades\Cache;

describe('HandleDownloadCompleted Listener', function () {
    beforeEach(function () {
        Cache::flush();
    });

    it('stores download completed data in cache', function () {
        $event = new DownloadCompleted('download-123', 456);
        $listener = new HandleDownloadCompleted;

        $listener->handle($event);

        $cached = Cache::get('download_completed_download-123');
        expect($cached)->toBeArray();
        expect($cached['downloadId'])->toBe('download-123');
        expect($cached['mediaId'])->toBe(456);
        expect($cached)->toHaveKey('timestamp');
    });

    it('clears queue pending count cache', function () {
        Cache::put('queue_pending_count', 5);

        $event = new DownloadCompleted('download-123', 456);
        $listener = new HandleDownloadCompleted;

        $listener->handle($event);

        expect(Cache::has('queue_pending_count'))->toBeFalse();
    });
});

describe('HandleDownloadFailed Listener', function () {
    beforeEach(function () {
        Cache::flush();
    });

    it('stores download failed data in cache', function () {
        $event = new DownloadFailed('download-123', 'Connection timeout');
        $listener = new HandleDownloadFailed;

        $listener->handle($event);

        $cached = Cache::get('download_failed_download-123');
        expect($cached)->toBeArray();
        expect($cached['downloadId'])->toBe('download-123');
        expect($cached['error'])->toBe('Connection timeout');
        expect($cached)->toHaveKey('timestamp');
    });

    it('clears queue pending count cache', function () {
        Cache::put('queue_pending_count', 5);

        $event = new DownloadFailed('download-123', 'Error');
        $listener = new HandleDownloadFailed;

        $listener->handle($event);

        expect(Cache::has('queue_pending_count'))->toBeFalse();
    });
});
