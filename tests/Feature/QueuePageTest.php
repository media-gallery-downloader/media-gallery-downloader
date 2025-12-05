<?php

use App\Filament\Pages\Queue;
use App\Services\DownloadService;
use App\Services\UploadService;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    Cache::forget('download_queue');
    Cache::forget('upload_queue');
});

describe('Queue Page', function () {
    it('can render the queue page', function () {
        $this->get(Queue::getUrl())
            ->assertSuccessful();
    });

    it('shows empty queue message when no downloads', function () {
        $this->get(Queue::getUrl())
            ->assertSuccessful()
            ->assertSee('Download queue is empty');
    });

    it('displays download queue items', function () {
        $downloadService = app(DownloadService::class);
        $downloadService->addToQueue('test-id', 'https://youtube.com/watch?v=test', 'yt-dlp');

        $component = Livewire::test(Queue::class);

        expect($component->get('downloadQueue'))->toHaveCount(1);
        expect($component->get('downloadQueue')[0]['url'])->toBe('https://youtube.com/watch?v=test');
    });

    it('can clear the download queue', function () {
        $downloadService = app(DownloadService::class);
        $downloadService->addToQueue('test-id-1', 'https://example.com/video1.mp4', 'direct');
        $downloadService->addToQueue('test-id-2', 'https://example.com/video2.mp4', 'direct');

        Livewire::test(Queue::class)
            ->call('clearQueue');

        expect($downloadService->getQueue())->toBeEmpty();
    });

    it('can cancel a specific download', function () {
        $downloadService = app(DownloadService::class);
        $downloadService->addToQueue('test-id-1', 'https://example.com/video1.mp4', 'direct');
        $downloadService->addToQueue('test-id-2', 'https://example.com/video2.mp4', 'direct');

        Livewire::test(Queue::class)
            ->call('cancelDownload', 'test-id-1');

        $queue = $downloadService->getQueue();
        expect($queue)->toHaveCount(1);
        expect($queue[0]['id'])->toBe('test-id-2');
    });

    it('displays upload queue items', function () {
        $uploadService = app(UploadService::class);
        $uploadService->addToQueue('upload-id', 'video.mp4', 'video/mp4');

        $component = Livewire::test(Queue::class);

        expect($component->get('uploadQueue'))->toHaveCount(1);
        expect($component->get('uploadQueue')[0]['filename'])->toBe('video.mp4');
    });

    it('can clear the upload queue', function () {
        $uploadService = app(UploadService::class);
        $uploadService->addToQueue('upload-id-1', 'video1.mp4', 'video/mp4');
        $uploadService->addToQueue('upload-id-2', 'video2.mp4', 'video/mp4');

        Livewire::test(Queue::class)
            ->call('clearUploadQueue');

        expect($uploadService->getQueue())->toBeEmpty();
    });

    it('refreshes queue data', function () {
        $downloadService = app(DownloadService::class);

        $component = Livewire::test(Queue::class);
        expect($component->get('downloadQueue'))->toBeEmpty();

        $downloadService->addToQueue('test-id', 'https://example.com/video.mp4', 'direct');

        $component->call('refreshQueue');
        expect($component->get('downloadQueue'))->toHaveCount(1);
    });

    it('handles download completed event', function () {
        $component = Livewire::test(Queue::class);

        $component->dispatch('downloadCompleted', downloadId: 'test-id', mediaId: 123);

        $component->assertSuccessful();
    });

    it('handles download failed event', function () {
        $component = Livewire::test(Queue::class);

        $component->dispatch('downloadFailed', downloadId: 'test-id', error: 'Test error');

        $component->assertSuccessful();
    });

    it('tracks current downloading item', function () {
        $downloadService = app(DownloadService::class);
        $downloadService->addToQueue('test-id', 'https://example.com/video.mp4', 'direct');
        $downloadService->updateStatus('test-id', 'downloading');

        $component = Livewire::test(Queue::class);

        expect($component->get('currentDownloadId'))->toBe('test-id');
    });

    it('tracks current uploading item', function () {
        $uploadService = app(UploadService::class);
        $uploadService->addToQueue('upload-id', 'video.mp4', 'video/mp4');
        $uploadService->updateStatus('upload-id', 'processing');

        $component = Livewire::test(Queue::class);

        expect($component->get('currentUploadId'))->toBe('upload-id');
    });

    it('returns polling interval', function () {
        $queue = new Queue;
        expect($queue->getPollingInterval())->toBe('2s');
    });

    it('can cancel a specific upload', function () {
        $uploadService = app(UploadService::class);
        $uploadService->addToQueue('upload-id-1', 'video1.mp4', 'video/mp4');
        $uploadService->addToQueue('upload-id-2', 'video2.mp4', 'video/mp4');

        Livewire::test(Queue::class)
            ->call('cancelUpload', 'upload-id-1');

        $queue = $uploadService->getQueue();
        expect($queue)->toHaveCount(1);
        expect($queue[0]['id'])->toBe('upload-id-2');
    });

    it('initializes queue state on mount', function () {
        $downloadService = app(DownloadService::class);
        $downloadService->addToQueue('test-id', 'https://example.com/video.mp4', 'direct');

        $component = Livewire::test(Queue::class);

        expect($component->get('downloadQueue'))->toHaveCount(1);
    });

    it('rehydrates queue state', function () {
        $component = Livewire::test(Queue::class);

        $downloadService = app(DownloadService::class);
        $downloadService->addToQueue('test-id', 'https://example.com/video.mp4', 'direct');

        // Simulate hydrate by refreshing
        $component->call('refreshQueue');

        expect($component->get('downloadQueue'))->toHaveCount(1);
    });
});
