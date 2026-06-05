<?php

use App\Services\DownloadService;
use App\Services\Maintenance\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ImportService queue operations', function () {
    it('adds items to the queue', function () {
        $service = new ImportService;

        $service->addToQueue('import-1', '/incoming/a.mp4', 'a.mp4');
        $service->addToQueue('import-2', '/incoming/b.mkv', 'b.mkv');

        $queue = $service->getQueue();

        expect($queue)->toHaveCount(2)
            ->and($queue[0]['id'])->toBe('import-1')
            ->and($queue[0]['path'])->toBe('/incoming/a.mp4')
            ->and($queue[0]['filename'])->toBe('a.mp4')
            ->and($queue[0]['status'])->toBe('queued')
            ->and($queue[0])->toHaveKey('added_at')
            ->and($queue[1]['id'])->toBe('import-2');
    });

    it('updates status with extra data', function () {
        $service = new ImportService;

        $service->addToQueue('import-1', '/incoming/a.mp4', 'a.mp4');
        $service->updateStatus('import-1', 'failed', ['error' => 'boom']);

        $queue = $service->getQueue();

        expect($queue[0]['status'])->toBe('failed')
            ->and($queue[0]['error'])->toBe('boom')
            ->and($queue[0]['path'])->toBe('/incoming/a.mp4'); // preserved
    });

    it('removes and clears items', function () {
        $service = new ImportService;

        $service->addToQueue('import-1', '/incoming/a.mp4', 'a.mp4');
        $service->addToQueue('import-2', '/incoming/b.mp4', 'b.mp4');

        $service->removeFromQueue('import-1');
        expect($service->getQueue())->toHaveCount(1)
            ->and($service->getQueue()[0]['id'])->toBe('import-2');

        $service->clearQueue();
        expect($service->getQueue())->toBeEmpty();
    });

    it('is isolated from other queue types in the shared table', function () {
        app(DownloadService::class)->addToQueue('dl-1', 'https://example.com/v.mp4', 'auto');
        (new ImportService)->addToQueue('import-1', '/incoming/a.mp4', 'a.mp4');

        $importQueue = (new ImportService)->getQueue();

        expect($importQueue)->toHaveCount(1)
            ->and($importQueue[0]['id'])->toBe('import-1');

        // Clearing imports must not touch downloads.
        (new ImportService)->clearQueue();
        expect(app(DownloadService::class)->getQueue())->toHaveCount(1);
    });
});
