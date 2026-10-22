<?php

use App\Models\Site;
use App\Services\Download\DownloadOptions;
use App\Services\Download\InvalidYtDlpArgumentsException;
use App\Services\Download\YtDlpArgumentLayer;
use App\Services\Download\YtDlpArguments;
use App\Settings\MaintenanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

function saveYtDlpExtraArgs(string $text): void
{
    $settings = app(MaintenanceSettings::class);
    $settings->ytdlp_extra_args = $text;
    $settings->save();
}

function giveSiteCookies(Site $site, string $cookieDomain): void
{
    $site->storeCookies(".{$cookieDomain}\tTRUE\t/\tTRUE\t9999999999\tsession\tx\n");
}

describe('YtDlpArguments cookies for a download', function () {
    beforeEach(function () {
        $this->cookiesDir = useTemporaryCookiesDirectory();
        $this->runDir = storage_path('framework/testing/run_'.str_replace('.', '', uniqid('', true)));
        File::ensureDirectoryExists($this->runDir);
    });

    afterEach(function () {
        File::deleteDirectory($this->cookiesDir);
        File::deleteDirectory($this->runDir);
    });

    it('copies the cookies of the site that matches the URL for the run', function () {
        $reddit = makeSite('Reddit', ['reddit.com']);
        giveSiteCookies($reddit, 'reddit.com');

        $copy = (new YtDlpArguments)->copyCookies('https://old.reddit.com/r/videos/', $this->runDir);

        expect($copy?->path)->not->toBe($reddit->cookiesPath())
            ->and(file_get_contents($copy->path))->toBe(file_get_contents($reddit->cookiesPath()));
    });

    it('has no cookies when no site matches the URL', function () {
        giveSiteCookies(makeSite('Reddit', ['reddit.com']), 'reddit.com');

        expect((new YtDlpArguments)->copyCookies('https://www.youtube.com/watch?v=abc', $this->runDir))->toBeNull();
    });

    it('has no cookies when the matching site has none', function () {
        makeSite('Reddit', ['reddit.com']);

        expect((new YtDlpArguments)->copyCookies('https://www.reddit.com/r/videos/', $this->runDir))->toBeNull();
    });
});

describe('YtDlpArguments validation', function () {
    it('runs the helper with python3 -I and the arguments as a plain array', function () {
        $commands = fakeYtDlpArgumentHelper(['changed' => ['headers']]);

        app(YtDlpArguments::class)->validate(['--add-headers', 'Referer: https://example.com']);

        expect($commands->getArrayCopy())->toBe([[
            'python3', '-I', resource_path('python/inspect_ytdlp_args.py'),
            '--', '--add-headers', 'Referer: https://example.com',
        ]]);
    });

    it('accepts ordinary options', function () {
        fakeYtDlpArgumentHelper(['changed' => ['concurrent_fragment_downloads', 'headers', 'impersonate']]);

        expect(app(YtDlpArguments::class)->validateText("--impersonate firefox\n--add-headers \"Referer: https://example.com\"\n-N 4"))
            ->toBe(['--impersonate', 'firefox', '--add-headers', 'Referer: https://example.com', '-N', '4']);
    });

    it('does not run the helper when there are no arguments', function () {
        $commands = fakeYtDlpArgumentHelper();

        expect(app(YtDlpArguments::class)->validateText("# nothing yet\n"))->toBe([])
            ->and($commands)->toHaveCount(0);
    });

    it('rejects positional arguments such as an extra URL', function () {
        fakeYtDlpArgumentHelper(['positional' => ['https://evil.example/other']]);

        expect(fn () => app(YtDlpArguments::class)->validate(['https://evil.example/other']))
            ->toThrow(InvalidYtDlpArgumentsException::class, '"https://evil.example/other" is not an option');
    });

    it('rejects denied destinations whatever spelling set them', function () {
        // yt-dlp reads the short-option cluster "-xo/tmp/x" as -x plus -o /tmp/x.
        fakeYtDlpArgumentHelper([
            'changed' => ['extractaudio', 'outtmpl'],
            'used' => [
                ['token' => '-xo/tmp/x', 'option' => '-x', 'dest' => 'extractaudio'],
                ['token' => '-xo/tmp/x', 'option' => '-o', 'dest' => 'outtmpl'],
            ],
        ]);

        expect(fn () => app(YtDlpArguments::class)->validate(['-xo/tmp/x']))
            ->toThrow(InvalidYtDlpArgumentsException::class, '-xo/tmp/x (read by yt-dlp as -o) is not allowed: it changes where files are saved');
    });

    it('names a canonical option plainly and lists every offending argument', function () {
        fakeYtDlpArgumentHelper([
            'changed' => ['exec_cmd', 'ratelimit', 'update_self'],
            'used' => [
                ['token' => '--exec=echo hi', 'option' => '--exec', 'dest' => 'exec_cmd'],
                ['token' => '--limit-rate', 'option' => '--limit-rate', 'dest' => 'ratelimit'],
                ['token' => '-U', 'option' => '-U', 'dest' => 'update_self'],
            ],
        ]);

        expect(fn () => app(YtDlpArguments::class)->validate(['--exec=echo hi', '--limit-rate', '1M', '-U']))
            ->toThrow(InvalidYtDlpArgumentsException::class, '--exec is not allowed: it runs commands; -U is not allowed: it controls yt-dlp self-updates');
    });

    it('refuses options that are new in yt-dlp and have not been reviewed', function () {
        fakeYtDlpArgumentHelper([
            'changed' => ['brand_new_dest'],
            'used' => [['token' => '--brand-new', 'option' => '--brand-new', 'dest' => 'brand_new_dest']],
        ]);

        expect(fn () => app(YtDlpArguments::class)->validate(['--brand-new']))
            ->toThrow(InvalidYtDlpArgumentsException::class, '--brand-new is not allowed yet: it is new in this version of yt-dlp and has not been reviewed');
    });

    it('refuses embedding the info JSON, which carries the site\'s cookies into the saved file', function () {
        fakeYtDlpArgumentHelper([
            'changed' => ['embed_infojson'],
            'used' => [['token' => '--embed-info-json', 'option' => '--embed-info-json', 'dest' => 'embed_infojson']],
        ]);

        expect(fn () => app(YtDlpArguments::class)->validate(['--embed-info-json']))
            ->toThrow(InvalidYtDlpArgumentsException::class, '--embed-info-json is not allowed: it embeds download details, including the site\'s cookies, in the saved file');
    });

    it('rejects alias definitions', function () {
        fakeYtDlpArgumentHelper(['aliases' => ['--get-it']]);

        expect(fn () => app(YtDlpArguments::class)->validate(['--alias', 'get-it', '--exec {0}']))
            ->toThrow(InvalidYtDlpArgumentsException::class, '--alias is not allowed');
    });

    it('reports arguments yt-dlp itself rejects', function () {
        fakeYtDlpArgumentHelper(['ok' => false, 'error_type' => 'invalid_arguments', 'error' => 'no such option: --nope'], 2);

        expect(fn () => app(YtDlpArguments::class)->validate(['--nope']))
            ->toThrow(InvalidYtDlpArgumentsException::class, 'yt-dlp does not accept these arguments: no such option: --nope');
    });

    it('fails closed when the helper cannot check the arguments', function (Closure $fake) {
        $fake();

        expect(fn () => app(YtDlpArguments::class)->validate(['--limit-rate', '1M']))
            ->toThrow(InvalidYtDlpArgumentsException::class, 'Could not check these arguments with yt-dlp');
    })->with([
        'helper crashes' => [fn () => fakeYtDlpArgumentHelper('', 1, "Traceback (most recent call last):\n  ...")],
        'yt-dlp unavailable' => [fn () => fakeYtDlpArgumentHelper(['ok' => false, 'error_type' => 'unavailable', 'error' => 'yt-dlp was not found on PATH'], 1)],
        'garbage output' => [fn () => fakeYtDlpArgumentHelper('not json')],
        'ok but failing exit code' => [fn () => fakeYtDlpArgumentHelper([], 1)],
        'malformed report' => [fn () => fakeYtDlpArgumentHelper(['changed' => 'exec_cmd'])],
        'process cannot start' => [fn () => app()->instance(YtDlpArguments::class, new YtDlpArguments(
            fn (array $command): Process => throw new ProcessStartFailedException(new Process(['python3']), 'python3 not found')
        ))],
    ]);

    it('denies every destination the old flag denylist covered', function () {
        // --exec, --exec-before-download, --config-location(s), --load-info(-json),
        // --batch-file/-a, --output/-o, --paths/-P, --downloader/--external-downloader,
        // --use-postprocessor, --print-to-file, --cookies, --cookies-from-browser.
        expect(YtDlpArguments::DENIED_DESTINATIONS)->toHaveKeys([
            'exec_cmd', 'exec_before_dl_cmd', 'config_locations', 'load_info_filename',
            'batchfile', 'outtmpl', 'paths', 'external_downloader', 'add_postprocessors',
            'print_to_file', 'cookiefile', 'cookiesfrombrowser',
        ]);
    });
});

describe('YtDlpArguments extra arguments for a download', function () {
    it('returns the validated Settings arguments', function () {
        $commands = fakeYtDlpArgumentHelper(['changed' => ['headers', 'impersonate']]);
        saveYtDlpExtraArgs("# reach the site like a browser\n--impersonate firefox\n--add-headers \"Referer: https://example.com\"");

        expect(app(YtDlpArguments::class)->extraArguments('https://example.com/v'))
            ->toBe(['--impersonate', 'firefox', '--add-headers', 'Referer: https://example.com'])
            ->and($commands)->toHaveCount(1);
    });

    it('fails the download when the arguments are not allowed', function () {
        fakeYtDlpArgumentHelper([
            'changed' => ['exec_cmd', 'ratelimit'],
            'used' => [['token' => '--exec', 'option' => '--exec', 'dest' => 'exec_cmd']],
        ]);
        saveYtDlpExtraArgs("--limit-rate 1M\n--exec 'touch /tmp/pwned'");

        expect(fn () => app(YtDlpArguments::class)->extraArguments('https://example.com/v'))
            ->toThrow(InvalidYtDlpArgumentsException::class, '--exec is not allowed');
    });

    it('fails the download when the arguments cannot be checked', function () {
        fakeYtDlpArgumentHelper('', 1, 'boom');
        saveYtDlpExtraArgs('--limit-rate 1M');

        expect(fn () => app(YtDlpArguments::class)->extraArguments('https://example.com/v'))
            ->toThrow(InvalidYtDlpArgumentsException::class, 'Could not check these arguments with yt-dlp');
    });

    it('fails the download when the Settings text cannot be parsed, naming the field', function () {
        $commands = fakeYtDlpArgumentHelper();
        saveYtDlpExtraArgs('--add-headers "Referer: x');

        expect(fn () => app(YtDlpArguments::class)->extraArguments('https://example.com/v'))
            ->toThrow(InvalidYtDlpArgumentsException::class, 'Settings → yt-dlp Extra Arguments: Line 1: missing closing double quote')
            ->and($commands)->toHaveCount(0);
    });

    it('returns nothing without running the helper when no arguments are set', function () {
        $commands = fakeYtDlpArgumentHelper();
        saveYtDlpExtraArgs('');

        expect(app(YtDlpArguments::class)->extraArguments('https://example.com/v'))->toBe([])
            ->and($commands)->toHaveCount(0);
    });
});

describe('YtDlpArguments layers for a download', function () {
    beforeEach(function () {
        fakeYtDlpArgumentHelper(['changed' => ['ratelimit', 'impersonate', 'concurrent_fragment_downloads']]);
        saveYtDlpExtraArgs('--limit-rate 1M');
    });

    it('adds the matching site\'s arguments after the global ones', function () {
        makeSite('Reddit', ['reddit.com'], ['extra_args' => '--impersonate firefox']);

        expect(app(YtDlpArguments::class)->extraArguments('https://www.reddit.com/r/videos/'))
            ->toBe(['--limit-rate', '1M', '--impersonate', 'firefox']);
    });

    it('replaces the global arguments when the site overrides them', function () {
        makeSite('Reddit', ['reddit.com'], ['extra_args' => '--impersonate firefox', 'combine_args' => false]);

        expect(app(YtDlpArguments::class)->extraArguments('https://www.reddit.com/r/videos/'))
            ->toBe(['--impersonate', 'firefox']);
    });

    it('ignores the arguments of sites that do not match the URL', function () {
        makeSite('Reddit', ['reddit.com'], ['extra_args' => '--impersonate firefox']);

        expect(app(YtDlpArguments::class)->extraArguments('https://www.youtube.com/watch?v=abc'))
            ->toBe(['--limit-rate', '1M']);
    });

    it('adds one-off arguments last', function () {
        makeSite('Reddit', ['reddit.com'], ['extra_args' => '--impersonate firefox']);

        expect(app(YtDlpArguments::class)->extraArguments('https://www.reddit.com/r/x/', new DownloadOptions('-N 4', true)))
            ->toBe(['--limit-rate', '1M', '--impersonate', 'firefox', '-N', '4']);
    });

    it('lets one-off arguments replace everything from Settings', function () {
        makeSite('Reddit', ['reddit.com'], ['extra_args' => '--impersonate firefox']);

        expect(app(YtDlpArguments::class)->extraArguments('https://www.reddit.com/r/x/', new DownloadOptions('-N 4', false)))
            ->toBe(['-N', '4']);
    });

    it('runs without extra arguments when empty one-off arguments replace Settings', function () {
        $commands = fakeYtDlpArgumentHelper();
        makeSite('Reddit', ['reddit.com'], ['extra_args' => '--impersonate firefox']);

        expect(app(YtDlpArguments::class)->extraArguments('https://www.reddit.com/r/x/', new DownloadOptions('', false)))
            ->toBe([])
            ->and($commands)->toHaveCount(0);
    });

    it('ignores empty one-off arguments that combine with Settings', function () {
        expect(app(YtDlpArguments::class)->extraArguments('https://example.com/v', new DownloadOptions('', true)))
            ->toBe(['--limit-rate', '1M']);
    });

    it('checks the combined arguments once, as yt-dlp will read them', function () {
        $commands = fakeYtDlpArgumentHelper(['changed' => ['ratelimit', 'impersonate', 'concurrent_fragment_downloads']]);
        makeSite('Reddit', ['reddit.com'], ['extra_args' => '--impersonate firefox']);

        app(YtDlpArguments::class)->extraArguments('https://www.reddit.com/r/x/', new DownloadOptions('-N 4', true));

        expect($commands->getArrayCopy())->toBe([[
            'python3', '-I', resource_path('python/inspect_ytdlp_args.py'),
            '--', '--limit-rate', '1M', '--impersonate', 'firefox', '-N', '4',
        ]]);
    });

    it('names the site whose arguments cannot be parsed', function () {
        makeSite('Reddit', ['reddit.com'], ['extra_args' => '--referer "x']);

        expect(fn () => app(YtDlpArguments::class)->extraArguments('https://www.reddit.com/r/x/'))
            ->toThrow(InvalidYtDlpArgumentsException::class, 'Site "Reddit": Line 1: missing closing double quote');
    });

    it('names the one-off arguments when they cannot be parsed', function () {
        expect(fn () => app(YtDlpArguments::class)->extraArguments('https://example.com/v', new DownloadOptions('--referer "x', true)))
            ->toThrow(InvalidYtDlpArgumentsException::class, 'This download\'s extra arguments: Line 1: missing closing double quote');
    });
});

describe('YtDlpArguments layers', function () {
    it('appends combining layers in order', function () {
        expect(YtDlpArguments::merge([
            YtDlpArgumentLayer::combine('global', '--limit-rate 1M'),
            YtDlpArgumentLayer::combine('domain', '--impersonate firefox'),
        ]))->toBe(['--limit-rate', '1M', '--impersonate', 'firefox']);
    });

    it('lets an overriding layer replace the layers before it', function () {
        expect(YtDlpArguments::merge([
            YtDlpArgumentLayer::combine('global', '--limit-rate 1M'),
            YtDlpArgumentLayer::override('download', '-N 4'),
            YtDlpArgumentLayer::combine('later', '--force-ipv4'),
        ]))->toBe(['-N', '4', '--force-ipv4']);
    });

    it('names the layer whose text cannot be parsed', function () {
        expect(fn () => YtDlpArguments::merge([YtDlpArgumentLayer::combine('Per-domain arguments', '--referer "x')]))
            ->toThrow(InvalidYtDlpArgumentsException::class, 'Per-domain arguments: Line 1: missing closing double quote');
    });
});
