<?php

use App\Filament\Pages\Logs;
use App\Models\FailedDownload;
use App\Models\FailedUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('logs navigation badge', function () {
    it('is hidden when there are no failed items', function () {
        expect(Logs::getNavigationBadge())->toBeNull();
    });

    it('counts non-resolved downloads and uploads', function () {
        FailedDownload::create(['url' => 'https://a.test/1', 'method' => 'yt-dlp', 'error_message' => 'x', 'status' => 'pending', 'retry_count' => 1]);
        FailedDownload::create(['url' => 'https://a.test/2', 'method' => 'yt-dlp', 'error_message' => 'x', 'status' => 'failed', 'retry_count' => 5]);
        FailedUpload::create(['filename' => 'a.mp4', 'mime_type' => 'video/mp4', 'error_message' => 'x', 'status' => 'pending', 'retry_count' => 1]);

        expect(Logs::getNavigationBadge())->toBe('3');
    });

    it('ignores resolved items (badge disappears once everything is resolved)', function () {
        FailedDownload::create(['url' => 'https://a.test/1', 'method' => 'yt-dlp', 'error_message' => 'x', 'status' => 'resolved', 'retry_count' => 1]);
        FailedUpload::create(['filename' => 'a.mp4', 'mime_type' => 'video/mp4', 'error_message' => 'x', 'status' => 'resolved', 'retry_count' => 1]);

        expect(Logs::getNavigationBadge())->toBeNull();
    });
});

describe('logs clear-all actions', function () {
    it('clears the entire failed downloads log regardless of status', function () {
        foreach (['pending', 'retrying', 'failed', 'resolved'] as $i => $status) {
            FailedDownload::create(['url' => "https://a.test/$i", 'method' => 'yt-dlp', 'error_message' => 'x', 'status' => $status, 'retry_count' => 1]);
        }

        Livewire::test(Logs::class)->call('clearAllDownloads');

        expect(FailedDownload::count())->toBe(0);
    });

    it('clears the entire failed uploads log regardless of status', function () {
        foreach (['pending', 'failed', 'resolved'] as $i => $status) {
            FailedUpload::create(['filename' => "a$i.mp4", 'mime_type' => 'video/mp4', 'error_message' => 'x', 'status' => $status, 'retry_count' => 1]);
        }

        Livewire::test(Logs::class)->call('clearAllUploads');

        expect(FailedUpload::count())->toBe(0);
    });

    it('renders the clear-all buttons in both section headers', function () {
        $html = Livewire::test(Logs::class)->html();

        expect(substr_count($html, 'Clear all'))->toBeGreaterThanOrEqual(2)
            ->and($html)->toContain('clearAllDownloads')
            ->and($html)->toContain('clearAllUploads');
    });
});
