<?php

use App\Services\Download\DownloadOptions;
use App\Services\Download\InvalidYtDlpArgumentsException;
use App\Services\Download\YtDlpDownloadHandler;
use App\Services\Sites\SiteCookieCopy;
use App\Settings\MaintenanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

function callYtDlpHandler(string $method, mixed ...$args): mixed
{
    return callYtDlpHandlerOn(new YtDlpDownloadHandler, $method, ...$args);
}

function callYtDlpHandlerOn(YtDlpDownloadHandler $handler, string $method, mixed ...$args): mixed
{
    return (new ReflectionMethod($handler, $method))->invoke($handler, ...$args);
}

function parseYtDlpError(string $output): string
{
    return callYtDlpHandler('parseError', $output);
}

describe('YtDlpDownloadHandler commands', function () {
    beforeEach(function () {
        $this->cookiesDir = useTemporaryCookiesDirectory();
        $this->runDir = storage_path('framework/testing/run_'.str_replace('.', '', uniqid('', true)));
        File::ensureDirectoryExists($this->runDir);
        $this->site = makeSite('Example', ['example.com']);
        $this->site->storeCookies(".example.com\tTRUE\t/\tTRUE\t9999999999\tsession\tx\n");
        $this->copy = SiteCookieCopy::create($this->site, $this->runDir);

        $settings = app(MaintenanceSettings::class);
        $settings->ytdlp_extra_args = "--impersonate firefox\n--add-headers \"Referer: https://example.com\"";
        $settings->save();
    });

    afterEach(function () {
        File::deleteDirectory($this->cookiesDir);
        File::deleteDirectory($this->runDir);
    });

    it('gives the metadata command the run\'s copy of the site cookies and the extra arguments', function () {
        $helper = fakeYtDlpArgumentHelper(['changed' => ['headers', 'impersonate']]);

        expect(callYtDlpHandler('buildMetadataCommand', 'https://example.com/watch?v=1', $this->copy))->toBe([
            'yt-dlp', '--no-playlist', '--no-warnings', '--dump-json',
            '--cookies', $this->copy->path,
            '--impersonate', 'firefox', '--add-headers', 'Referer: https://example.com',
            '--', 'https://example.com/watch?v=1',
        ])->and($helper)->toHaveCount(1);
    });

    it('gives the download command the extra arguments right before the URL', function () {
        fakeYtDlpArgumentHelper(['changed' => ['headers', 'impersonate']]);

        $command = callYtDlpHandler('buildDownloadCommand', 'https://example.com/watch?v=1', '/tmp/x/%(title)s.%(ext)s', $this->copy);

        expect($command)->toContain('--cookies', $this->copy->path)
            ->and(array_slice($command, -6))->toBe([
                '--impersonate', 'firefox', '--add-headers', 'Referer: https://example.com',
                '--', 'https://example.com/watch?v=1',
            ]);
    });

    it('sends no cookies when the run has none', function () {
        fakeYtDlpArgumentHelper(['changed' => ['headers', 'impersonate']]);

        expect(callYtDlpHandler('buildMetadataCommand', 'https://other.example.net/v', null))->not->toContain('--cookies');
    });

    it('runs yt-dlp on a private copy of the site cookies and keeps what it refreshed, even when the run fails', function () {
        $handler = new class extends YtDlpDownloadHandler
        {
            public ?string $cookieFileUsed = null;

            protected function fetchMetadata(string $url, ?string $workingDirectory = null, ?SiteCookieCopy $cookies = null): array
            {
                $this->cookieFileUsed = $cookies?->path;
                // What yt-dlp does on exit: rewrite its cookie file in place.
                file_put_contents((string) $cookies?->path, ".example.com\tTRUE\t/\tTRUE\t9999999999\tsession\tREFRESHED\n");

                throw new RuntimeException('stop before the download');
            }
        };

        expect(fn () => $handler->download('https://www.example.com/v', 'dl-1'))->toThrow(RuntimeException::class, 'stop before the download');

        expect($handler->cookieFileUsed)->not->toBeNull()->not->toBe($this->site->cookiesPath())
            ->and(file_exists((string) $handler->cookieFileUsed))->toBeFalse()
            ->and(file_get_contents($this->site->cookiesPath()))->toContain('REFRESHED');
    });

    it('adds the download\'s one-off arguments last, right before the URL', function () {
        fakeYtDlpArgumentHelper(['changed' => ['headers', 'impersonate', 'concurrent_fragment_downloads']]);
        $handler = (new YtDlpDownloadHandler)->withOptions(new DownloadOptions('-N 4', true));

        expect(array_slice(callYtDlpHandlerOn($handler, 'buildMetadataCommand', 'https://example.com/v', $this->copy), -4))
            ->toBe(['-N', '4', '--', 'https://example.com/v'])
            ->and(array_slice(callYtDlpHandlerOn($handler, 'buildDownloadCommand', 'https://example.com/v', '/tmp/x/%(title)s.%(ext)s', $this->copy), -4))
            ->toBe(['-N', '4', '--', 'https://example.com/v']);
    });

    it('validates the extra arguments again before each command', function () {
        $helper = fakeYtDlpArgumentHelper(['changed' => ['headers', 'impersonate']]);

        callYtDlpHandler('buildMetadataCommand', 'https://example.com/v');
        callYtDlpHandler('buildDownloadCommand', 'https://example.com/v', '/tmp/x/%(title)s.%(ext)s');

        expect($helper)->toHaveCount(2);
    });

    it('fails before running yt-dlp when the extra arguments are rejected', function () {
        fakeYtDlpArgumentHelper([
            'changed' => ['exec_cmd'],
            'used' => [['token' => '--exec', 'option' => '--exec', 'dest' => 'exec_cmd']],
        ]);

        expect(fn () => callYtDlpHandler('buildMetadataCommand', 'https://example.com/v'))
            ->toThrow(InvalidYtDlpArgumentsException::class, '--exec is not allowed');
    });
});

describe('YtDlpDownloadHandler error parsing', function () {
    it('maps the Reddit "IP address" refusal to cookie and update advice', function () {
        $message = parseYtDlpError('ERROR: [Reddit] 1txiqwi: Your IP address is unable to access the Reddit API');

        expect($message)
            ->toContain('Reddit refused the request even with the uploaded cookies')
            ->toContain('fresh Reddit cookies')
            ->toContain('Settings → Sites')
            ->toContain('yt-dlp update')
            ->not->toContain('IP address')
            ->not->toMatch('/\b20\d\d\b/'); // no dates
    });

    it('maps "authentication is required" to a hint about the site\'s cookies', function () {
        $raw = 'ERROR: [Reddit] 1txiqwi: Account authentication is required. Use --cookies, ...';

        expect(parseYtDlpError($raw))
            ->toContain('requires you to be logged in')
            ->toContain('Settings → Sites');
    });

    it('maps expired-cookie errors to a friendly message', function () {
        expect(parseYtDlpError('The provided YouTube account cookies are no longer valid'))
            ->toContain('cookies have expired')
            ->toContain('Settings → Sites');
    });

    it('maps age-restriction errors to a cookies hint', function () {
        expect(parseYtDlpError('ERROR: [youtube] x: Sign in to confirm your age'))
            ->toContain('age-restricted')
            ->toContain('Settings → Sites');
    });

    it('falls back to the raw yt-dlp error otherwise', function () {
        expect(parseYtDlpError('Some unexpected failure'))
            ->toBe('yt-dlp error: Some unexpected failure');
    });
});

describe('yt-dlp error output with cookie lines', function () {
    it('never repeats a cookie line yt-dlp echoes back', function () {
        $stderr = "WARNING: skipping cookie file entry due to invalid length 8: '.reddit.com\\tTRUE\\t/\\tTRUE\\t0\\treddit_session\\tSECRET-VALUE\\textra\\n'\n"
            ."ERROR: invalid Netscape format cookies file '/tmp/x/cookies.txt': 'www.example.com\\tTRUE\\t/\\tTRUE\\t0\\tsid\\tOTHER-SECRET\\n'\n"
            ."ERROR: [generic] x: Unable to download webpage: HTTP Error 404: Not Found\n";

        expect(parseYtDlpError($stderr))
            ->not->toContain('SECRET-VALUE')
            ->not->toContain('OTHER-SECRET')
            ->toContain('HTTP Error 404');
    });
});

describe('login errors for a URL no site covers', function () {
    it('names the host that has no site', function () {
        expect(callYtDlpHandler('parseError', 'ERROR: [youtube] x: Sign in to confirm your age', 'youtu.be'))
            ->toContain('No site in Settings → Sites covers youtu.be');
    });

    it('says nothing about coverage when the site\'s cookies were used', function () {
        expect(parseYtDlpError('ERROR: [youtube] x: Sign in to confirm your age'))->not->toContain('covers');
    });
});
