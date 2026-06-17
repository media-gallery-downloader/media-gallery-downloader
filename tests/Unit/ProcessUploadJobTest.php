<?php

use App\Jobs\ProcessUploadJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

uses(RefreshDatabase::class);

describe('ProcessUploadJob', function () {
    it('can be instantiated with required parameters', function () {
        $job = new ProcessUploadJob(
            '/tmp/test.mp4',
            'test_video.mp4',
            'video/mp4',
            'upload-123'
        );

        expect($job->filePath)->toBe('/tmp/test.mp4');
        expect($job->originalName)->toBe('test_video.mp4');
        expect($job->mimeType)->toBe('video/mp4');
        expect($job->uploadId)->toBe('upload-123');
    });

    it('has a 1 hour timeout', function () {
        $job = new ProcessUploadJob(
            '/tmp/test.mp4',
            'test_video.mp4',
            'video/mp4',
            'upload-123'
        );

        expect($job->timeout)->toBe(3600);
    });

    it('implements ShouldQueue interface', function () {
        $job = new ProcessUploadJob(
            '/tmp/test.mp4',
            'test_video.mp4',
            'video/mp4',
            'upload-123'
        );

        expect($job)->toBeInstanceOf(ShouldQueue::class);
    });

    it('uses expected traits', function () {
        $job = new ProcessUploadJob(
            '/tmp/test.mp4',
            'test_video.mp4',
            'video/mp4',
            'upload-123'
        );

        $uses = class_uses_recursive(get_class($job));

        expect($uses)->toContain(Queueable::class);
        expect($uses)->toContain(SerializesModels::class);
        expect($uses)->toContain(InteractsWithQueue::class);
        expect($uses)->toContain(Dispatchable::class);
    });
});
