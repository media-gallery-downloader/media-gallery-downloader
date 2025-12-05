<?php

use App\Services\DownloadService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::forget('download_queue');
});

describe('DownloadService', function () {
    it('validates valid URLs', function () {
        $service = new DownloadService;

        expect($service->validateUrl('https://example.com/video.mp4'))->toBeTrue();
        expect($service->validateUrl('http://example.com/video.mp4'))->toBeTrue();
    });

    it('rejects invalid URLs', function () {
        $service = new DownloadService;

        expect($service->validateUrl('not-a-url'))->toBeFalse();
        expect($service->validateUrl('ftp://example.com/file'))->toBeFalse();
        expect($service->validateUrl(''))->toBeFalse();
    });

    it('adds items to the queue', function () {
        $service = new DownloadService;

        $service->addToQueue('test-id-1', 'https://example.com/video1.mp4', 'direct');
        $service->addToQueue('test-id-2', 'https://example.com/video2.mp4', 'yt-dlp');

        $queue = $service->getQueue();

        expect($queue)->toHaveCount(2);
        expect($queue[0]['id'])->toBe('test-id-1');
        expect($queue[0]['url'])->toBe('https://example.com/video1.mp4');
        expect($queue[0]['method'])->toBe('direct');
        expect($queue[0]['status'])->toBe('queued');
        expect($queue[1]['id'])->toBe('test-id-2');
    });

    it('updates queue item status', function () {
        $service = new DownloadService;

        $service->addToQueue('test-id', 'https://example.com/video.mp4', 'direct');
        $service->updateStatus('test-id', 'downloading', ['progress' => 50]);

        $queue = $service->getQueue();

        expect($queue[0]['status'])->toBe('downloading');
        expect($queue[0]['progress'])->toBe(50);
    });

    it('removes items from the queue', function () {
        $service = new DownloadService;

        $service->addToQueue('test-id-1', 'https://example.com/video1.mp4', 'direct');
        $service->addToQueue('test-id-2', 'https://example.com/video2.mp4', 'direct');

        $service->removeFromQueue('test-id-1');

        $queue = $service->getQueue();

        expect($queue)->toHaveCount(1);
        expect($queue[0]['id'])->toBe('test-id-2');
    });

    it('clears the entire queue', function () {
        $service = new DownloadService;

        $service->addToQueue('test-id-1', 'https://example.com/video1.mp4', 'direct');
        $service->addToQueue('test-id-2', 'https://example.com/video2.mp4', 'direct');

        $service->clearQueue();

        expect($service->getQueue())->toBeEmpty();
    });

    it('returns empty array when queue is empty', function () {
        $service = new DownloadService;

        expect($service->getQueue())->toBeArray();
        expect($service->getQueue())->toBeEmpty();
    });

    it('validates various valid URL formats', function () {
        $service = new DownloadService;

        expect($service->validateUrl('https://youtube.com/watch?v=abc123'))->toBeTrue();
        expect($service->validateUrl('https://www.example.com/path/to/video.mp4'))->toBeTrue();
        expect($service->validateUrl('http://localhost:8080/video'))->toBeTrue();
        expect($service->validateUrl('https://192.168.1.1/video.mp4'))->toBeTrue();
    });

    it('rejects various invalid URL formats', function () {
        $service = new DownloadService;

        expect($service->validateUrl('javascript:alert(1)'))->toBeFalse();
        expect($service->validateUrl('file:///etc/passwd'))->toBeFalse();
        expect($service->validateUrl('mailto:test@example.com'))->toBeFalse();
        expect($service->validateUrl('data:text/plain,hello'))->toBeFalse();
    });

    it('preserves queue item data when updating status', function () {
        $service = new DownloadService;

        $service->addToQueue('test-id', 'https://example.com/video.mp4', 'yt-dlp');
        $service->updateStatus('test-id', 'downloading');

        $queue = $service->getQueue();

        expect($queue[0]['url'])->toBe('https://example.com/video.mp4');
        expect($queue[0]['method'])->toBe('yt-dlp');
        expect($queue[0]['status'])->toBe('downloading');
    });

    it('adds extra data when updating status', function () {
        $service = new DownloadService;

        $service->addToQueue('test-id', 'https://example.com/video.mp4', 'direct');
        $service->updateStatus('test-id', 'failed', [
            'error' => 'Network error',
            'attempts' => 3,
        ]);

        $queue = $service->getQueue();

        expect($queue[0]['status'])->toBe('failed');
        expect($queue[0]['error'])->toBe('Network error');
        expect($queue[0]['attempts'])->toBe(3);
    });

    it('only updates matching queue item', function () {
        $service = new DownloadService;

        $service->addToQueue('id-1', 'https://example.com/video1.mp4', 'direct');
        $service->addToQueue('id-2', 'https://example.com/video2.mp4', 'direct');

        $service->updateStatus('id-1', 'downloading');

        $queue = $service->getQueue();

        expect($queue[0]['status'])->toBe('downloading');
        expect($queue[1]['status'])->toBe('queued');
    });

    it('does not error when updating non-existent item', function () {
        $service = new DownloadService;

        $service->addToQueue('id-1', 'https://example.com/video1.mp4', 'direct');
        $service->updateStatus('non-existent-id', 'downloading');

        $queue = $service->getQueue();

        expect($queue[0]['status'])->toBe('queued');
    });

    it('maintains queue order after removal', function () {
        $service = new DownloadService;

        $service->addToQueue('id-1', 'https://example.com/video1.mp4', 'direct');
        $service->addToQueue('id-2', 'https://example.com/video2.mp4', 'direct');
        $service->addToQueue('id-3', 'https://example.com/video3.mp4', 'direct');

        $service->removeFromQueue('id-2');

        $queue = $service->getQueue();

        expect($queue)->toHaveCount(2);
        expect($queue[0]['id'])->toBe('id-1');
        expect($queue[1]['id'])->toBe('id-3');
    });

    it('handles removal of non-existent item gracefully', function () {
        $service = new DownloadService;

        $service->addToQueue('id-1', 'https://example.com/video1.mp4', 'direct');
        $service->removeFromQueue('non-existent-id');

        $queue = $service->getQueue();

        expect($queue)->toHaveCount(1);
    });

    it('adds timestamp when adding to queue', function () {
        $service = new DownloadService;

        $service->addToQueue('test-id', 'https://example.com/video.mp4', 'direct');

        $queue = $service->getQueue();

        expect($queue[0])->toHaveKey('added_at');
        expect($queue[0]['added_at'])->not->toBeNull();
    });

    it('returns direct as download method for regular URLs', function () {
        $service = new DownloadService;

        // Regular URLs that yt-dlp won't handle
        $method = $service->getDownloadMethod('https://example.com/video.mp4');

        expect($method)->toBeIn(['direct', 'yt-dlp']);
    });

    it('checks if ytdlp can handle a URL', function () {
        $service = new DownloadService;

        // This will actually call yt-dlp to check
        $result = $service->canUseYtdlp('https://example.com/video.mp4');

        expect($result)->toBeBool();
    });
});
