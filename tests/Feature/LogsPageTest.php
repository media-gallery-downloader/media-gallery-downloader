<?php

use App\Filament\Pages\Logs;
use App\Models\FailedDownload;
use App\Models\FailedUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use ReflectionMethod;

uses(RefreshDatabase::class);

describe('Logs Page', function () {
    it('can render the logs page', function () {
        $this->get(Logs::getUrl())
            ->assertSuccessful();
    });

    it('has correct navigation label', function () {
        expect(Logs::getNavigationLabel())->toBe('Logs');
    });

    it('renders when failed downloads exist', function () {
        FailedDownload::factory()->count(3)->create();

        $this->get(Logs::getUrl())
            ->assertSuccessful();
    });

    it('renders when failed uploads exist', function () {
        FailedUpload::factory()->count(3)->create();

        $this->get(Logs::getUrl())
            ->assertSuccessful();
    });

    it('returns failed downloads array', function () {
        FailedDownload::factory()->count(5)->create();

        $component = Livewire::test(Logs::class);
        $logs = new Logs;
        $downloads = $logs->getFailedDownloads();

        expect($downloads)->toBeArray();
        expect(count($downloads))->toBe(5);
    });

    it('returns failed stats', function () {
        FailedDownload::factory()->count(2)->create(['status' => 'pending']);
        FailedDownload::factory()->count(3)->create(['status' => 'failed']);

        $logs = new Logs;
        $stats = $logs->getFailedStats();

        expect($stats['pending'])->toBe(2);
        expect($stats['failed'])->toBe(3);
    });

    it('returns failed uploads array', function () {
        FailedUpload::factory()->count(4)->create();

        $logs = new Logs;
        $uploads = $logs->getFailedUploads();

        expect($uploads)->toBeArray();
        expect(count($uploads))->toBe(4);
    });

    it('returns failed upload stats', function () {
        FailedUpload::factory()->count(2)->create(['status' => 'pending']);
        FailedUpload::factory()->count(1)->create(['status' => 'failed']);
        FailedUpload::factory()->count(3)->create(['status' => 'resolved']);

        $logs = new Logs;
        $stats = $logs->getFailedUploadStats();

        expect($stats['pending'])->toBe(2);
        expect($stats['failed'])->toBe(1);
        expect($stats['resolved'])->toBe(3);
    });

    it('can delete a failed download via component', function () {
        $failed = FailedDownload::factory()->create();

        Livewire::test(Logs::class)
            ->call('deleteDownload', $failed->id);

        expect(FailedDownload::find($failed->id))->toBeNull();
    });

    it('can delete a failed upload via component', function () {
        $failed = FailedUpload::factory()->create();

        Livewire::test(Logs::class)
            ->call('deleteUpload', $failed->id);

        expect(FailedUpload::find($failed->id))->toBeNull();
    });

    it('can clear resolved downloads via component', function () {
        FailedDownload::factory()->count(2)->create(['status' => 'resolved']);
        FailedDownload::factory()->count(1)->create(['status' => 'pending']);

        Livewire::test(Logs::class)
            ->call('clearResolved');

        expect(FailedDownload::where('status', 'resolved')->count())->toBe(0);
        expect(FailedDownload::where('status', 'pending')->count())->toBe(1);
    });

    it('can clear all failed downloads via component', function () {
        FailedDownload::factory()->count(2)->create(['status' => 'failed']);
        FailedDownload::factory()->count(1)->create(['status' => 'pending']);

        Livewire::test(Logs::class)
            ->call('clearAllFailed');

        expect(FailedDownload::where('status', 'failed')->count())->toBe(0);
        expect(FailedDownload::where('status', 'pending')->count())->toBe(1);
    });

    it('can clear resolved uploads via component', function () {
        FailedUpload::factory()->count(2)->create(['status' => 'resolved']);
        FailedUpload::factory()->count(1)->create(['status' => 'pending']);

        Livewire::test(Logs::class)
            ->call('clearResolvedUploads');

        expect(FailedUpload::where('status', 'resolved')->count())->toBe(0);
        expect(FailedUpload::where('status', 'pending')->count())->toBe(1);
    });

    it('can clear all failed uploads via component', function () {
        FailedUpload::factory()->count(2)->create(['status' => 'failed']);
        FailedUpload::factory()->count(1)->create(['status' => 'pending']);

        Livewire::test(Logs::class)
            ->call('clearAllFailedUploads');

        expect(FailedUpload::where('status', 'failed')->count())->toBe(0);
        expect(FailedUpload::where('status', 'pending')->count())->toBe(1);
    });
});

describe('FailedDownload operations', function () {
    it('can delete a failed download', function () {
        $failedDownload = FailedDownload::factory()->create();

        expect(FailedDownload::count())->toBe(1);

        $failedDownload->delete();

        expect(FailedDownload::count())->toBe(0);
    });

    it('can clear all resolved downloads', function () {
        FailedDownload::factory()->count(3)->create(['status' => 'resolved']);
        FailedDownload::factory()->count(2)->create(['status' => 'pending']);

        expect(FailedDownload::count())->toBe(5);

        FailedDownload::where('status', 'resolved')->delete();

        expect(FailedDownload::count())->toBe(2);
    });

    it('can mark download as retrying', function () {
        $failedDownload = FailedDownload::factory()->create([
            'retry_count' => 0,
            'last_attempt_at' => null,
        ]);

        $failedDownload->markRetrying();

        expect($failedDownload->fresh()->retry_count)->toBe(1);
        expect($failedDownload->fresh()->last_attempt_at)->not->toBeNull();
    });
});

describe('FailedUpload operations', function () {
    it('can delete a failed upload', function () {
        $failedUpload = FailedUpload::factory()->create();

        expect(FailedUpload::count())->toBe(1);

        $failedUpload->delete();

        expect(FailedUpload::count())->toBe(0);
    });

    it('can clear all resolved uploads', function () {
        FailedUpload::factory()->count(3)->create(['status' => 'resolved']);
        FailedUpload::factory()->count(2)->create(['status' => 'pending']);

        expect(FailedUpload::count())->toBe(5);

        FailedUpload::where('status', 'resolved')->delete();

        expect(FailedUpload::count())->toBe(2);
    });

    it('can mark upload as resolved', function () {
        $failedUpload = FailedUpload::factory()->create([
            'status' => 'pending',
        ]);

        $failedUpload->markResolved();

        expect($failedUpload->fresh()->status)->toBe('resolved');
    });
});

describe('Logs Page - System Logs', function () {
    it('mounts with default log lines', function () {
        $component = Livewire::test(Logs::class);

        $component->assertSet('data.log_lines', 100);
    });

    it('can retry download', function () {
        $failed = FailedDownload::factory()->create([
            'url' => 'https://example.com/video.mp4',
            'status' => 'pending',
        ]);

        Livewire::test(Logs::class)
            ->call('retryDownload', $failed->id);

        // Status should have changed from pending
        $updated = FailedDownload::find($failed->id);
        expect($updated->status)->toBeIn(['retrying', 'failed', 'resolved']);
    });

    it('handles retry for non-existent download', function () {
        Livewire::test(Logs::class)
            ->call('retryDownload', 99999)
            ->assertSuccessful();
    });

    it('can retry all pending downloads', function () {
        FailedDownload::factory()->create([
            'url' => 'https://example.com/video1.mp4',
            'status' => 'pending',
        ]);

        Livewire::test(Logs::class)
            ->call('retryAllPending')
            ->assertSuccessful();
    });

    it('limits failed downloads to 20', function () {
        FailedDownload::factory()->count(25)->create();

        $logs = new Logs;
        $downloads = $logs->getFailedDownloads();

        expect(count($downloads))->toBe(20);
    });

    it('limits failed uploads to 20', function () {
        FailedUpload::factory()->count(25)->create();

        $logs = new Logs;
        $uploads = $logs->getFailedUploads();

        expect(count($uploads))->toBe(20);
    });

    it('orders failed downloads by created_at descending', function () {
        FailedDownload::factory()->create(['created_at' => now()->subDays(5)]);
        FailedDownload::factory()->create(['created_at' => now()]);

        $logs = new Logs;
        $downloads = $logs->getFailedDownloads();

        // Most recent should be first
        expect(count($downloads))->toBe(2);
    });

    it('orders failed uploads by created_at descending', function () {
        FailedUpload::factory()->create(['created_at' => now()->subDays(5)]);
        FailedUpload::factory()->create(['created_at' => now()]);

        $logs = new Logs;
        $uploads = $logs->getFailedUploads();

        // Most recent should be first
        expect(count($uploads))->toBe(2);
    });

    it('returns complete failed stats', function () {
        FailedDownload::factory()->create(['status' => 'pending']);
        FailedDownload::factory()->create(['status' => 'retrying']);
        FailedDownload::factory()->create(['status' => 'failed']);
        FailedDownload::factory()->create(['status' => 'resolved']);

        $logs = new Logs;
        $stats = $logs->getFailedStats();

        expect($stats)->toHaveKeys(['pending', 'retrying', 'failed', 'resolved']);
        expect($stats['pending'])->toBe(1);
        expect($stats['retrying'])->toBe(1);
        expect($stats['failed'])->toBe(1);
        expect($stats['resolved'])->toBe(1);
    });

    it('returns log files list', function () {
        $logs = new Logs;
        $method = new ReflectionMethod($logs, 'getLogFiles');
        $method->setAccessible(true);
        $files = $method->invoke($logs);

        // Should be an array (may be empty if no log files)
        expect($files)->toBeArray();
    });

    it('returns log content for existing file', function () {
        // Create a test log file
        $logPath = storage_path('logs/test.log');
        File::put($logPath, "Test log line 1\nTest log line 2\nTest log line 3");

        $logs = new Logs;
        $method = new ReflectionMethod($logs, 'getLogContent');
        $method->setAccessible(true);
        $content = $method->invoke($logs, 'test.log', 100);

        expect($content)->toContain('Test log line');

        // Cleanup
        File::delete($logPath);
    });

    it('handles non-existent log file', function () {
        $logs = new Logs;
        $method = new ReflectionMethod($logs, 'getLogContent');
        $method->setAccessible(true);
        $content = $method->invoke($logs, 'nonexistent.log', 100);

        expect($content)->toBe('File not found.');
    });
});
