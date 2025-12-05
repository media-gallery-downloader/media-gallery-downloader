<?php

use App\Jobs\ProcessDownloadJob;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

        expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
    });

    it('uses expected traits', function () {
        $job = new ProcessDownloadJob('https://example.com/video.mp4', 'download-123');

        $uses = class_uses_recursive(get_class($job));

        expect($uses)->toContain(\Illuminate\Bus\Queueable::class);
        expect($uses)->toContain(\Illuminate\Queue\SerializesModels::class);
        expect($uses)->toContain(\Illuminate\Queue\InteractsWithQueue::class);
        expect($uses)->toContain(\Illuminate\Foundation\Bus\Dispatchable::class);
    });
});
