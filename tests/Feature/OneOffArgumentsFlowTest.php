<?php

use App\Filament\Pages\Logs;
use App\Filament\Widgets\DownloadQueueWidget;
use App\Filament\Widgets\FailedDownloadsWidget;
use App\Jobs\ProcessDownloadJob;
use App\Models\FailedDownload;
use App\Models\Media;
use App\Models\QueueItem;
use App\Services\Download\DownloadOptions;
use App\Services\Download\YtDlpDownloadHandler;
use App\Services\DownloadService;
use App\Services\MaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function failedRedditDownload(array $attributes = []): FailedDownload
{
    return FailedDownload::create([
        'url' => 'https://www.reddit.com/r/videos/comments/abc/x/',
        'method' => 'yt-dlp',
        'error_message' => 'boom',
        'status' => 'pending',
        'retry_count' => 1,
        'extra_args' => '--impersonate firefox',
        'combine_args' => false,
        ...$attributes,
    ]);
}

function recordJobFailure(ProcessDownloadJob $job, string $message): void
{
    (new ReflectionMethod($job, 'recordFailure'))->invoke($job, $message);
}

function assertDownloadQueuedWith(string $url, DownloadOptions $options): void
{
    Queue::assertPushed(ProcessDownloadJob::class, fn (ProcessDownloadJob $job): bool => $job->url === $url
        && $job->extraArgs === $options->extraArgs
        && $job->combineArgs === $options->combineArgs);
}

describe('one-off arguments in the download pipeline', function () {
    beforeEach(fn () => Queue::fake());

    it('dispatches the job with the download options and keeps them on the queue item', function () {
        app(DownloadService::class)->downloadFromUrl('https://example.com/v', 'dl-1', new DownloadOptions('-N 4', false));

        assertDownloadQueuedWith('https://example.com/v', new DownloadOptions('-N 4', false));
        expect(QueueItem::where('queue_id', 'dl-1')->sole()->meta)->toMatchArray(['extra_args' => '-N 4', 'combine_args' => false]);
    });

    it('dispatches with no one-off arguments by default', function () {
        app(DownloadService::class)->downloadFromUrl('https://example.com/v', 'dl-1');

        assertDownloadQueuedWith('https://example.com/v', DownloadOptions::none());
    });

    it('hands the download options to the yt-dlp handler', function () {
        $captured = new ArrayObject;
        app()->instance(YtDlpDownloadHandler::class, new class($captured) extends YtDlpDownloadHandler
        {
            public function __construct(private ArrayObject $captured)
            {
                parent::__construct();
            }

            public function withOptions(DownloadOptions $options): static
            {
                $this->captured['options'] = $options;

                return $this;
            }

            public function download(string $url, string $downloadId, ?callable $progressCallback = null): Media
            {
                throw new RuntimeException('stop before running yt-dlp');
            }
        });

        // A YouTube URL: no direct-download fallback after the yt-dlp failure.
        (new ProcessDownloadJob('https://www.youtube.com/watch?v=abc', 'dl-1', '-N 4', false))->handle();

        expect($captured['options'])->toEqual(new DownloadOptions('-N 4', false));
    });

    it('fails instead of falling back to a direct download when its arguments are not allowed', function () {
        fakeYtDlpArgumentHelper([
            'changed' => ['exec_cmd'],
            'used' => [['token' => '--exec', 'option' => '--exec', 'dest' => 'exec_cmd']],
        ]);

        // A loopback URL: the direct handler would refuse it at once, so no
        // network is involved either way.
        (new ProcessDownloadJob('http://127.0.0.1:9/video.mp4', 'dl-1', '--exec ls', true))->handle();

        $failed = FailedDownload::sole();
        expect($failed->method)->toBe('yt-dlp')
            ->and($failed->error_message)->toContain('--exec is not allowed')
            ->and($failed->error_message)->not->toContain('direct-download fallback');
    });

    it('records the download options on the failed-download row', function () {
        recordJobFailure(new ProcessDownloadJob('https://example.com/v', 'dl-1', '-N 4', false), 'boom');

        expect(FailedDownload::sole()->downloadOptions())->toEqual(new DownloadOptions('-N 4', false));
    });

    it('keeps the options current when an open failure row fails again', function () {
        failedRedditDownload(['url' => 'https://example.com/v', 'extra_args' => '--old', 'combine_args' => true, 'status' => 'retrying']);

        recordJobFailure(new ProcessDownloadJob('https://example.com/v', 'dl-2', '-N 4', false), 'again');

        expect(FailedDownload::sole()->downloadOptions())->toEqual(new DownloadOptions('-N 4', false));
    });

    it('retries from the Logs page with the saved options', function () {
        $failed = failedRedditDownload();

        Livewire::test(Logs::class)->call('retryDownload', $failed->id);

        assertDownloadQueuedWith($failed->url, new DownloadOptions('--impersonate firefox', false));
    });

    it('retries from the failed-downloads widget with the saved options', function () {
        $failed = failedRedditDownload();

        Livewire::test(FailedDownloadsWidget::class)->call('retryDownload', $failed->id);

        assertDownloadQueuedWith($failed->url, new DownloadOptions('--impersonate firefox', false));
    });

    it('retries pending failures automatically with the saved options', function () {
        $failed = failedRedditDownload(['next_retry_at' => null]);

        app(MaintenanceService::class)->retryFailedDownloads();

        assertDownloadQueuedWith($failed->url, new DownloadOptions('--impersonate firefox', false));
    });

    it('retries a failed queue item with the options it was queued with', function () {
        app(DownloadService::class)->downloadFromUrl('https://example.com/v', 'dl-1', new DownloadOptions('-N 4', false));
        app(DownloadService::class)->updateStatus('dl-1', 'failed');
        Queue::fake(); // forget the first dispatch

        Livewire::test(DownloadQueueWidget::class)->call('retryFailed', 'dl-1');

        assertDownloadQueuedWith('https://example.com/v', new DownloadOptions('-N 4', false));
    });

    it('retries a failure saved before one-off arguments existed with none', function () {
        $failed = FailedDownload::create([
            'url' => 'https://example.com/legacy',
            'method' => 'yt-dlp',
            'error_message' => 'boom',
            'status' => 'pending',
            'retry_count' => 1,
        ]);

        Livewire::test(Logs::class)->call('retryDownload', $failed->id);

        assertDownloadQueuedWith('https://example.com/legacy', DownloadOptions::none());
    });
});
