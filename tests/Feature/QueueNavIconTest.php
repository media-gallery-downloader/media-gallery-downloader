<?php

use App\Livewire\QueueNavIcon;
use App\Services\DownloadService;
use App\Services\UploadService;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    Cache::forget('download_queue');
    Cache::forget('upload_queue');
});

describe('QueueNavIcon', function () {
    it('renders successfully', function () {
        Livewire::test(QueueNavIcon::class)
            ->assertSuccessful();
    });

    it('shows inactive state when no active downloads', function () {
        $component = Livewire::test(QueueNavIcon::class);

        $component->assertSet('wasActive', false);
    });

    it('shows active state when downloads are processing', function () {
        $downloadService = app(DownloadService::class);
        $downloadService->addToQueue('test-id', 'https://example.com/video.mp4', 'direct');
        $downloadService->updateStatus('test-id', 'downloading');

        $component = Livewire::test(QueueNavIcon::class);

        $component->assertSet('wasActive', true);
    });

    it('shows active state when uploads are processing', function () {
        $uploadService = app(UploadService::class);
        $uploadService->addToQueue('upload-id', 'video.mp4', 'video/mp4');
        $uploadService->updateStatus('upload-id', 'processing');

        $component = Livewire::test(QueueNavIcon::class);

        $component->assertSet('wasActive', true);
    });

    it('returns queue data via checkQueue method', function () {
        $downloadService = app(DownloadService::class);
        $downloadService->addToQueue('test-id', 'https://example.com/video.mp4', 'direct');

        $icon = new QueueNavIcon;
        $queue = $icon->checkQueue();

        expect($queue)->toHaveCount(1);
        expect($queue->first()['url'])->toBe('https://example.com/video.mp4');
    });

    it('combines download and upload queues', function () {
        $downloadService = app(DownloadService::class);
        $downloadService->addToQueue('download-id', 'https://example.com/video.mp4', 'direct');

        $uploadService = app(UploadService::class);
        $uploadService->addToQueue('upload-id', 'video.mp4', 'video/mp4');

        $icon = new QueueNavIcon;
        $queue = $icon->checkQueue();

        expect($queue)->toHaveCount(2);
    });

    it('removes failed downloads from queue on render', function () {
        $downloadService = app(DownloadService::class);
        $downloadService->addToQueue('failed-id', 'https://example.com/video.mp4', 'direct');
        $downloadService->updateStatus('failed-id', 'failed', ['error' => 'Test error']);

        // Render the component - it should remove failed items
        Livewire::test(QueueNavIcon::class);

        $queue = $downloadService->getQueue();
        $failedItems = collect($queue)->where('status', 'failed');
        expect($failedItems)->toHaveCount(0);
    });

    it('handles empty queues gracefully', function () {
        $icon = new QueueNavIcon;
        $queue = $icon->checkQueue();

        expect($queue)->toBeEmpty();
    });
});
