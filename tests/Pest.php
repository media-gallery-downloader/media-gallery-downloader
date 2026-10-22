<?php

use App\Models\Site;
use App\Services\Download\YtDlpArguments;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function something()
{
    // ..
}

/**
 * Bind a YtDlpArguments whose yt-dlp option helper is faked: instead of
 * running python3 + yt-dlp, each check runs a PHP one-liner that prints the
 * given report (an array, merged over an "everything is fine" report) or raw
 * stdout (a string), writes $stderr and exits with $exitCode.
 *
 * Returns the list of commands the real helper would have been run with.
 *
 * @param  array<string, mixed>|string  $report
 * @return ArrayObject<int, list<string>>
 */
function fakeYtDlpArgumentHelper(array|string $report = [], int $exitCode = 0, string $stderr = ''): ArrayObject
{
    $stdout = is_array($report)
        ? (string) json_encode(array_merge([
            'ok' => true,
            'version' => 'test',
            'changed' => [],
            'positional' => [],
            'aliases' => [],
            'used' => [],
        ], $report))
        : $report;

    $commands = new ArrayObject;

    app()->instance(YtDlpArguments::class, new YtDlpArguments(
        function (array $command) use ($commands, $stdout, $stderr, $exitCode): Process {
            $commands[] = $command;

            return new Process(
                [PHP_BINARY, '-r', 'fwrite(STDOUT, (string) getenv("FAKE_STDOUT")); fwrite(STDERR, (string) getenv("FAKE_STDERR")); exit((int) getenv("FAKE_EXIT"));'],
                null,
                ['FAKE_STDOUT' => $stdout, 'FAKE_STDERR' => $stderr, 'FAKE_EXIT' => (string) $exitCode],
            );
        }
    ));

    return $commands;
}

/**
 * Point per-site cookie files at a throwaway directory for this test, so
 * tests never touch the app's real storage/app/cookies (in the dev container
 * that is the live, bind-mounted storage). Returns the directory; remove it
 * with File::deleteDirectory() in afterEach.
 */
function useTemporaryCookiesDirectory(): string
{
    $dir = storage_path('framework/testing/cookies_'.str_replace('.', '', uniqid('', true)));
    config(['mgd.cookies_path' => $dir]);

    return $dir;
}

/**
 * @param  list<string>  $domains
 * @param  array<string, mixed>  $attributes
 */
function makeSite(string $name, array $domains, array $attributes = []): Site
{
    return Site::create(['name' => $name, 'domains' => $domains, ...$attributes]);
}
