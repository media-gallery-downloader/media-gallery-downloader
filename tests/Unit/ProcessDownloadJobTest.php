<?php

use App\Jobs\ProcessDownloadJob;
use App\Models\FailedDownload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

uses(RefreshDatabase::class);

describe('ProcessDownloadJob', function () {
    it('can be instantiated with url and downloadId', function () {
        $job = new ProcessDownloadJob('https://example.com/video.mp4', 'download-123');

        expect($job->url)->toBe('https://example.com/video.mp4');
        expect($job->downloadId)->toBe('download-123');
    });

    it('has a 5 minute timeout', function () {
        $job = new ProcessDownloadJob('https://example.com/video.mp4', 'download-123');

        expect($job->timeout)->toBe(300);
    });

    it('implements ShouldQueue interface', function () {
        $job = new ProcessDownloadJob('https://example.com/video.mp4', 'download-123');

        expect($job)->toBeInstanceOf(ShouldQueue::class);
    });

    it('uses expected traits', function () {
        $job = new ProcessDownloadJob('https://example.com/video.mp4', 'download-123');

        $uses = class_uses_recursive(get_class($job));

        expect($uses)->toContain(Queueable::class);
        expect($uses)->toContain(SerializesModels::class);
        expect($uses)->toContain(InteractsWithQueue::class);
        expect($uses)->toContain(Dispatchable::class);
    });

    it('reuses an existing unresolved failed-download row instead of duplicating', function () {
        $job = new ProcessDownloadJob('https://example.com/video.mp4', 'download-123');

        $record = new ReflectionMethod($job, 'recordFailure');
        $record->setAccessible(true);

        $record->invoke($job, 'first error');
        $record->invoke($job, 'second error');

        $rows = FailedDownload::where('url', 'https://example.com/video.mp4')->get();

        expect($rows)->toHaveCount(1)
            ->and($rows->first()->error_message)->toBe('second error');
    });
});
