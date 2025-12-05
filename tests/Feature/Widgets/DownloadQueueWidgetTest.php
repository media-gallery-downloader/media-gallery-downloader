<?php

use App\Filament\Widgets\DownloadQueueWidget;
use App\Services\DownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('DownloadQueueWidget', function () {
    it('renders successfully', function () {
        Livewire::test(DownloadQueueWidget::class)
            ->assertSuccessful();
    });

    it('returns queue data structure', function () {
        $widget = new DownloadQueueWidget;
        $data = $widget->getQueueData();

        expect($data)->toHaveKey('items');
        expect($data)->toHaveKey('stats');
        expect($data['stats'])->toHaveKey('pending');
        expect($data['stats'])->toHaveKey('downloading');
        expect($data['stats'])->toHaveKey('completed');
        expect($data['stats'])->toHaveKey('failed');
        expect($data['stats'])->toHaveKey('total');
    });

    it('shows empty queue by default', function () {
        $widget = new DownloadQueueWidget;
        $data = $widget->getQueueData();

        expect($data['stats']['total'])->toBe(0);
        expect($data['items'])->toBe([]);
    });

    it('shows queue items when downloads exist', function () {
        $downloadService = app(DownloadService::class);
        $downloadService->addToQueue('test-id-1', 'https://example.com/video1.mp4', 'direct');
        $downloadService->addToQueue('test-id-2', 'https://example.com/video2.mp4', 'direct');

        $widget = new DownloadQueueWidget;
        $data = $widget->getQueueData();

        expect($data['stats']['total'])->toBe(2);
        expect($data['stats']['pending'])->toBe(2);
    });

    it('counts different statuses correctly', function () {
        $downloadService = app(DownloadService::class);
        $downloadService->addToQueue('test-id-1', 'https://example.com/video1.mp4', 'direct');
        $downloadService->addToQueue('test-id-2', 'https://example.com/video2.mp4', 'direct');
        $downloadService->updateStatus('test-id-1', 'downloading');
        $downloadService->updateStatus('test-id-2', 'completed');

        $widget = new DownloadQueueWidget;
        $data = $widget->getQueueData();

        expect($data['stats']['downloading'])->toBe(1);
        expect($data['stats']['completed'])->toBe(1);
    });

    it('limits displayed items to 10', function () {
        $downloadService = app(DownloadService::class);

        // Add 15 items to queue
        for ($i = 1; $i <= 15; $i++) {
            $downloadService->addToQueue("test-id-{$i}", "https://example.com/video{$i}.mp4", 'direct');
        }

        $widget = new DownloadQueueWidget;
        $data = $widget->getQueueData();

        expect($data['stats']['total'])->toBe(15);
        expect(count($data['items']))->toBe(10);
    });

    it('can clear completed downloads', function () {
        $downloadService = app(DownloadService::class);
        $downloadService->addToQueue('test-id-1', 'https://example.com/video1.mp4', 'direct');
        $downloadService->addToQueue('test-id-2', 'https://example.com/video2.mp4', 'direct');
        $downloadService->updateStatus('test-id-1', 'completed');

        Livewire::test(DownloadQueueWidget::class)
            ->call('clearCompleted');

        $queue = $downloadService->getQueue();
        $completedItems = collect($queue)->where('status', 'completed');
        expect($completedItems)->toHaveCount(0);
    });

    it('can retry failed downloads', function () {
        $downloadService = app(DownloadService::class);
        $downloadService->addToQueue('test-id-1', 'https://example.com/video1.mp4', 'direct');
        $downloadService->updateStatus('test-id-1', 'failed', ['error' => 'Connection timeout']);

        // Retry will create a new download job
        Livewire::test(DownloadQueueWidget::class)
            ->call('retryFailed', 'test-id-1');

        // The original failed item should be removed from queue
        $queue = $downloadService->getQueue();
        $originalItem = collect($queue)->firstWhere('id', 'test-id-1');
        expect($originalItem)->toBeNull();
    });

    it('does not retry non-failed downloads', function () {
        $downloadService = app(DownloadService::class);
        $downloadService->addToQueue('test-id-1', 'https://example.com/video1.mp4', 'direct');
        $downloadService->updateStatus('test-id-1', 'downloading');

        $widget = new DownloadQueueWidget;
        $widget->retryFailed('test-id-1');

        // Item should still exist as downloading
        $queue = $downloadService->getQueue();
        $item = collect($queue)->firstWhere('id', 'test-id-1');
        expect($item['status'])->toBe('downloading');
    });

    it('handles retry for non-existent downloads', function () {
        $widget = new DownloadQueueWidget;

        // Should not throw an error
        $widget->retryFailed('non-existent-id');

        expect(true)->toBeTrue(); // Test passed if no exception
    });

    it('preserves queued items when clearing completed', function () {
        $downloadService = app(DownloadService::class);
        $downloadService->addToQueue('test-id-1', 'https://example.com/video1.mp4', 'direct');
        $downloadService->addToQueue('test-id-2', 'https://example.com/video2.mp4', 'direct');
        $downloadService->updateStatus('test-id-1', 'completed');

        Livewire::test(DownloadQueueWidget::class)
            ->call('clearCompleted');

        $queue = $downloadService->getQueue();
        expect(count($queue))->toBe(1);
        expect($queue[0]['id'])->toBe('test-id-2');
        expect($queue[0]['status'])->toBe('queued');
    });
});
