<?php

use App\Jobs\ProcessDownloadJob;
use App\Models\FailedDownload;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Regression: failed downloads must back off exponentially and stop for good
 * after 5 attempts. Previously each job failure reset its row to a fresh
 * 'pending' with no next_retry_at, so the hourly retry task re-queued it
 * immediately — a permanent retry loop.
 */
describe('download retry flow', function () {
    function invokeRecordFailure(ProcessDownloadJob $job, string $message): void
    {
        $m = new ReflectionMethod($job, 'recordFailure');
        $m->setAccessible(true);
        $m->invoke($job, $message);
    }

    it('first failure creates a row with backoff scheduled', function () {
        $job = new ProcessDownloadJob('https://example.com/a.mp4', 'dl-1');

        invokeRecordFailure($job, 'boom');

        $row = FailedDownload::sole();
        expect($row->status)->toBe('pending')
            ->and($row->retry_count)->toBe(1)
            ->and($row->next_retry_at)->not->toBeNull()
            ->and($row->next_retry_at->isFuture())->toBeTrue();
    });

    it('repeat failures advance the backoff on the SAME row', function () {
        FailedDownload::create([
            'url' => 'https://example.com/a.mp4',
            'method' => 'yt-dlp',
            'error_message' => 'first',
            'status' => 'retrying',
            'retry_count' => 1,
        ]);

        $job = new ProcessDownloadJob('https://example.com/a.mp4', 'dl-2');
        invokeRecordFailure($job, 'second failure');

        $row = FailedDownload::sole(); // still exactly one row
        expect($row->retry_count)->toBe(2)
            ->and($row->status)->toBe('pending')
            // pow(2,2)*5 = 20 minutes
            ->and(now()->diffInMinutes($row->next_retry_at))->toBeGreaterThan(18)
            ->and(now()->diffInMinutes($row->next_retry_at))->toBeLessThan(22);
    });

    it('the fifth failure marks the row permanently failed', function () {
        FailedDownload::create([
            'url' => 'https://example.com/a.mp4',
            'method' => 'yt-dlp',
            'error_message' => 'fourth',
            'status' => 'retrying',
            'retry_count' => 4,
        ]);

        $job = new ProcessDownloadJob('https://example.com/a.mp4', 'dl-3');
        invokeRecordFailure($job, 'fifth failure');

        $row = FailedDownload::sole();
        expect($row->status)->toBe('failed')
            ->and($row->retry_count)->toBe(5)
            ->and($row->next_retry_at)->toBeNull();
    });

    it('a successful download resolves the open failure row', function () {
        FailedDownload::create([
            'url' => 'https://example.com/a.mp4',
            'method' => 'yt-dlp',
            'error_message' => 'earlier failure',
            'status' => 'retrying',
            'retry_count' => 2,
        ]);

        $job = new ProcessDownloadJob('https://example.com/a.mp4', 'dl-4');
        $m = new ReflectionMethod($job, 'resolveFailureRecords');
        $m->setAccessible(true);
        $m->invoke($job);

        expect(FailedDownload::sole()->status)->toBe('resolved');
    });
});
