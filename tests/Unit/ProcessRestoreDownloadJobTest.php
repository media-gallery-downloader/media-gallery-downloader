<?php

use App\Jobs\ProcessRestoreDownloadJob;
use App\Services\Download\InvalidYtDlpArgumentsException;
use App\Services\Sites\SiteCookieCopy;
use App\Settings\MaintenanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

function restoreJobCommand(string $url = 'https://example.com/watch?v=1', ?SiteCookieCopy $cookies = null): array
{
    $job = new ProcessRestoreDownloadJob(1);

    return (new ReflectionMethod($job, 'buildCommand'))->invoke($job, $url, '/tmp/restore/%(title)s.%(ext)s', $cookies);
}

describe('ProcessRestoreDownloadJob yt-dlp command', function () {
    beforeEach(function () {
        $this->cookiesDir = useTemporaryCookiesDirectory();
        $this->runDir = storage_path('framework/testing/run_'.str_replace('.', '', uniqid('', true)));
        File::ensureDirectoryExists($this->runDir);
        $this->site = makeSite('Example', ['example.com']);
        $this->site->storeCookies(".example.com\tTRUE\t/\tTRUE\t9999999999\tsession\tx\n");

        $settings = app(MaintenanceSettings::class);
        $settings->ytdlp_extra_args = '--add-headers "Referer: https://example.com"';
        $settings->save();
    });

    afterEach(function () {
        File::deleteDirectory($this->cookiesDir);
        File::deleteDirectory($this->runDir);
    });

    it('gives yt-dlp the run\'s copy of the site cookies', function () {
        fakeYtDlpArgumentHelper(['changed' => ['headers']]);
        $copy = SiteCookieCopy::create($this->site, $this->runDir);

        expect(restoreJobCommand('https://example.com/watch?v=1', $copy))->toContain('--cookies', $copy->path)
            ->and(restoreJobCommand('https://other.example.net/v'))->not->toContain('--cookies');
    });

    it('adds the validated extra arguments right before the URL', function () {
        $helper = fakeYtDlpArgumentHelper(['changed' => ['headers']]);

        expect(array_slice(restoreJobCommand(), -4))->toBe([
            '--add-headers', 'Referer: https://example.com', '--', 'https://example.com/watch?v=1',
        ])->and($helper)->toHaveCount(1);
    });

    it('fails the restore download when the extra arguments are rejected', function () {
        fakeYtDlpArgumentHelper([
            'changed' => ['outtmpl'],
            'used' => [['token' => '-o', 'option' => '-o', 'dest' => 'outtmpl']],
        ]);

        expect(fn () => restoreJobCommand())->toThrow(InvalidYtDlpArgumentsException::class, '-o is not allowed');
    });
});
