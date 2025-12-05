<?php

use App\Models\FailedUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('FailedUpload Model', function () {
    it('can be created with fillable attributes', function () {
        $failedUpload = FailedUpload::create([
            'filename' => 'video.mp4',
            'mime_type' => 'video/mp4',
            'error_message' => 'Upload failed',
            'retry_count' => 0,
            'status' => 'pending',
        ]);

        expect($failedUpload)->toBeInstanceOf(FailedUpload::class);
        expect($failedUpload->filename)->toBe('video.mp4');
        expect($failedUpload->mime_type)->toBe('video/mp4');
        expect($failedUpload->status)->toBe('pending');
    });

    it('creates from upload helper method', function () {
        $failedUpload = FailedUpload::createFromUpload(
            'test-video.mp4',
            'video/mp4',
            'File validation failed'
        );

        expect($failedUpload->filename)->toBe('test-video.mp4');
        expect($failedUpload->mime_type)->toBe('video/mp4');
        expect($failedUpload->error_message)->toBe('File validation failed');
        expect($failedUpload->retry_count)->toBe(1);
        expect($failedUpload->status)->toBe('pending');
        expect($failedUpload->last_attempt_at)->not->toBeNull();
    });

    it('marks as failed after max retries', function () {
        $failedUpload = FailedUpload::create([
            'filename' => 'video.mp4',
            'mime_type' => 'video/mp4',
            'retry_count' => 3, // Already at max retries (default is 3)
            'status' => 'pending',
        ]);

        $failedUpload->markFailed('Max retries exceeded');

        expect($failedUpload->status)->toBe('failed');
        expect($failedUpload->retry_count)->toBe(4);
    });

    it('marks as resolved', function () {
        $failedUpload = FailedUpload::create([
            'filename' => 'video.mp4',
            'mime_type' => 'video/mp4',
            'retry_count' => 1,
            'status' => 'pending',
        ]);

        $failedUpload->markResolved();

        expect($failedUpload->status)->toBe('resolved');
    });

    it('scopes pending correctly', function () {
        FailedUpload::create([
            'filename' => 'video1.mp4',
            'status' => 'pending',
        ]);

        FailedUpload::create([
            'filename' => 'video2.mp4',
            'status' => 'failed',
        ]);

        FailedUpload::create([
            'filename' => 'video3.mp4',
            'status' => 'pending',
        ]);

        $pending = FailedUpload::pending()->get();

        expect($pending)->toHaveCount(2);
    });

    it('scopes permanently failed correctly', function () {
        FailedUpload::create([
            'filename' => 'video1.mp4',
            'status' => 'pending',
        ]);

        FailedUpload::create([
            'filename' => 'video2.mp4',
            'status' => 'failed',
        ]);

        FailedUpload::create([
            'filename' => 'video3.mp4',
            'status' => 'resolved',
        ]);

        $failed = FailedUpload::permanentlyFailed()->get();

        expect($failed)->toHaveCount(1);
    });
});
