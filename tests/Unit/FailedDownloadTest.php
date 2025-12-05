<?php

use App\Models\FailedDownload;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('FailedDownload Model', function () {
    it('can be created with fillable attributes', function () {
        $failedDownload = FailedDownload::create([
            'url' => 'https://example.com/video.mp4',
            'method' => 'yt-dlp',
            'error_message' => 'Download failed',
            'retry_count' => 0,
            'status' => 'pending',
        ]);

        expect($failedDownload)->toBeInstanceOf(FailedDownload::class);
        expect($failedDownload->url)->toBe('https://example.com/video.mp4');
        expect($failedDownload->method)->toBe('yt-dlp');
        expect($failedDownload->status)->toBe('pending');
    });

    it('marks as retrying and increments retry count', function () {
        $failedDownload = FailedDownload::create([
            'url' => 'https://example.com/video.mp4',
            'method' => 'yt-dlp',
            'retry_count' => 0,
            'status' => 'pending',
        ]);

        $failedDownload->markRetrying();

        expect($failedDownload->status)->toBe('retrying');
        expect($failedDownload->retry_count)->toBe(1);
        expect($failedDownload->last_attempt_at)->not->toBeNull();
    });

    it('marks as failed after max retries', function () {
        $failedDownload = FailedDownload::create([
            'url' => 'https://example.com/video.mp4',
            'method' => 'yt-dlp',
            'retry_count' => 4,
            'status' => 'pending',
        ]);

        $failedDownload->markFailed('Max retries exceeded', 5);

        expect($failedDownload->status)->toBe('failed');
        expect($failedDownload->retry_count)->toBe(5);
        expect($failedDownload->next_retry_at)->toBeNull();
    });

    it('remains pending with exponential backoff before max retries', function () {
        $failedDownload = FailedDownload::create([
            'url' => 'https://example.com/video.mp4',
            'method' => 'yt-dlp',
            'retry_count' => 1,
            'status' => 'pending',
        ]);

        $failedDownload->markFailed('Temporary error', 5);

        expect($failedDownload->status)->toBe('pending');
        expect($failedDownload->retry_count)->toBe(2);
        expect($failedDownload->next_retry_at)->not->toBeNull();
    });

    it('marks as resolved', function () {
        $failedDownload = FailedDownload::create([
            'url' => 'https://example.com/video.mp4',
            'method' => 'yt-dlp',
            'retry_count' => 2,
            'status' => 'pending',
            'next_retry_at' => now()->addMinutes(30),
        ]);

        $failedDownload->markResolved();

        expect($failedDownload->status)->toBe('resolved');
        expect($failedDownload->next_retry_at)->toBeNull();
    });

    it('scopes pending retries correctly', function () {
        FailedDownload::create([
            'url' => 'https://example.com/video1.mp4',
            'status' => 'pending',
            'next_retry_at' => now()->subMinutes(5),
        ]);

        FailedDownload::create([
            'url' => 'https://example.com/video2.mp4',
            'status' => 'pending',
            'next_retry_at' => null,
        ]);

        FailedDownload::create([
            'url' => 'https://example.com/video3.mp4',
            'status' => 'pending',
            'next_retry_at' => now()->addMinutes(30),
        ]);

        FailedDownload::create([
            'url' => 'https://example.com/video4.mp4',
            'status' => 'failed',
        ]);

        $pendingRetries = FailedDownload::pendingRetry()->get();

        expect($pendingRetries)->toHaveCount(2);
    });

    it('scopes permanently failed correctly', function () {
        FailedDownload::create([
            'url' => 'https://example.com/video1.mp4',
            'status' => 'pending',
        ]);

        FailedDownload::create([
            'url' => 'https://example.com/video2.mp4',
            'status' => 'failed',
        ]);

        FailedDownload::create([
            'url' => 'https://example.com/video3.mp4',
            'status' => 'failed',
        ]);

        $failed = FailedDownload::permanentlyFailed()->get();

        expect($failed)->toHaveCount(2);
    });
});
