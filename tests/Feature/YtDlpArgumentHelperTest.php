<?php

use App\Services\Download\InvalidYtDlpArgumentsException;
use App\Services\Download\YtDlpArguments;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * Runs the real resources/python/inspect_ytdlp_args.py against the installed
 * yt-dlp. Skipped where python3 or a yt-dlp zipapp isn't on PATH (e.g. CI);
 * the dev container has both.
 */

function ytDlpZipappAvailable(): bool
{
    static $available = null;

    if ($available === null) {
        $finder = new ExecutableFinder;
        $python = $finder->find('python3');
        $ytDlp = $finder->find('yt-dlp');
        $available = false;

        if ($python !== null && $ytDlp !== null) {
            $probe = new Process([$python, '-I', '-c', 'import sys, zipfile; sys.exit(0 if zipfile.is_zipfile(sys.argv[1]) else 1)', $ytDlp]);
            $probe->run();
            $available = $probe->isSuccessful();
        }
    }

    return $available;
}

function runYtDlpArgumentHelper(string ...$args): array
{
    $process = new Process(['python3', '-I', resource_path('python/inspect_ytdlp_args.py'), '--', ...$args]);
    $process->run();

    return ['exit' => $process->getExitCode(), 'report' => json_decode($process->getOutput(), true)];
}

describe('inspect_ytdlp_args.py with the installed yt-dlp', function () {
    beforeEach(fn () => ytDlpZipappAvailable() || $this->markTestSkipped('python3 or the yt-dlp zipapp is not available'));

    it('reports the option destinations the arguments change', function () {
        $result = runYtDlpArgumentHelper('--impersonate', 'firefox', '-N', '4', '--add-headers', 'Referer: https://example.com');

        expect($result['exit'])->toBe(0)
            ->and($result['report']['ok'])->toBeTrue()
            ->and($result['report']['changed'])->toBe(['concurrent_fragment_downloads', 'headers', 'impersonate'])
            ->and($result['report']['positional'])->toBe([])
            ->and($result['report']['aliases'])->toBe([]);
    });

    it('follows yt-dlp\'s own spelling rules', function () {
        // "-xo/tmp/x" is -x plus -o /tmp/x; "--exec-b" abbreviates --exec-before-download.
        $result = runYtDlpArgumentHelper('-xo/tmp/x', '--exec-b', 'touch /tmp/pwned');

        expect($result['report']['changed'])->toBe(['exec_before_dl_cmd', 'extractaudio', 'outtmpl'])
            ->and($result['report']['used'])->toContain(
                ['token' => '-xo/tmp/x', 'option' => '-o', 'dest' => 'outtmpl'],
                ['token' => '--exec-b', 'option' => '--exec-before-download', 'dest' => 'exec_before_dl_cmd'],
            );
    });

    it('reports positional arguments and alias definitions', function () {
        $result = runYtDlpArgumentHelper('--alias', 'get-it', '--exec {0}', 'https://evil.example/other');

        expect($result['report']['positional'])->toBe(['https://evil.example/other'])
            ->and($result['report']['aliases'])->toBe(['--get-it']);
    });

    it('reports arguments yt-dlp rejects', function () {
        $result = runYtDlpArgumentHelper('--definitely-not-an-option');

        expect($result['exit'])->toBe(2)
            ->and($result['report'])->toMatchArray(['ok' => false, 'error_type' => 'invalid_arguments'])
            ->and($result['report']['error'])->toContain('--definitely-not-an-option');
    });
});

describe('YtDlpArguments with the real helper', function () {
    beforeEach(fn () => ytDlpZipappAvailable() || $this->markTestSkipped('python3 or the yt-dlp zipapp is not available'));

    it('accepts ordinary options', function (string $text) {
        expect((new YtDlpArguments)->validateText($text))->not->toBeEmpty();
    })->with([
        'impersonation' => ['--impersonate firefox'],
        'quoted header' => ['--add-headers "Referer: https://example.com"'],
        'concurrent fragments' => ['-N 4'],
        'rate limit + sleeps' => ['--limit-rate 1M --sleep-interval 5 --max-sleep-interval 30'],
        'proxy' => ['--proxy socks5://127.0.0.1:1080'],
        'IPv4 only' => ['--force-ipv4'],
        'format selector' => ['-f "bestvideo[height<=1080]+bestaudio/best"'],
        'user agent' => ["--user-agent 'Mozilla/5.0 (X11; Linux x86_64)'"],
        'extractor args' => ['--extractor-args "youtube:player_client=web"'],
        'output placeholder (sanitized by yt-dlp)' => ['--output-na-placeholder none'],
        'clearing an option is not changing it' => ['--no-exec --no-cookies'],
    ]);

    it('rejects every flag the old denylist named', function (string $text) {
        expect(fn () => (new YtDlpArguments)->validateText($text))
            ->toThrow(InvalidYtDlpArgumentsException::class, 'is not allowed');
    })->with([
        ['--exec "touch /tmp/pwned"'],
        ['--exec-before-download "touch /tmp/pwned"'],
        ['--config-location /tmp'],
        ['--config-locations /tmp'],
        ['--load-info-json /tmp/info.json'],
        ['--load-info /tmp/info.json'],
        ['--batch-file /etc/passwd'],
        ['-a /etc/passwd'],
        ['--output /tmp/x'],
        ['-o /tmp/x'],
        ['--paths /tmp'],
        ['-P /tmp'],
        ['--downloader curl'],
        ['--external-downloader curl'],
        ['--use-postprocessor Evil'],
        ['--print-to-file title /tmp/x'],
        ['--cookies /tmp/cookies.txt'],
        ['--cookies-from-browser firefox'],
    ]);

    it('rejects other spellings and other dangerous options', function (string $text, string $message) {
        expect(fn () => (new YtDlpArguments)->validateText($text))
            ->toThrow(InvalidYtDlpArgumentsException::class, $message);
    })->with([
        'short option cluster' => ['-xo/tmp/x', '-xo/tmp/x (read by yt-dlp as -o) is not allowed'],
        'attached short value' => ['-P/tmp', '-P/tmp (read by yt-dlp as -P) is not allowed'],
        'long option with =' => ['--output=/tmp/x', '--output is not allowed'],
        'abbreviation' => ['--exec-b "touch /tmp/pwned"', '--exec-b (read by yt-dlp as --exec-before-download) is not allowed'],
        'alias definition' => ['--alias get-it "--exec {0}" --get-it "touch /tmp/pwned"', '--alias is not allowed'],
        'post-processor args' => ['--ppa "ffmpeg:-y /tmp/x"', 'is not allowed'],
        'downloader args' => ['--downloader-args "ffmpeg:-y /tmp/x"', 'is not allowed'],
        'netrc command' => ['--netrc-cmd "touch /tmp/pwned"', 'runs commands'],
        'ffmpeg location' => ['--ffmpeg-location /tmp/evil', 'is not allowed'],
        'JS runtime path' => ['--js-runtimes node:/tmp/evil', 'is not allowed'],
        'plugin dirs' => ['--plugin-dirs /tmp', 'loads plugin code from'],
        'self-update' => ['-U', 'controls yt-dlp self-updates'],
        'update to' => ['--update-to stable', 'controls yt-dlp self-updates'],
        'file URLs' => ['--enable-file-urls', 'is not allowed'],
        'output by id' => ['--id', 'changes where files are saved'],
        'download archive' => ['--download-archive /tmp/archive.txt', 'is not allowed'],
        'cache dir' => ['--cache-dir /tmp', 'is not allowed'],
        'write pages' => ['--write-pages', 'is not allowed'],
        'rewrite metadata' => ['--parse-metadata "title:(?P<url>.+)"', 'is not allowed'],
        'compat options' => ['--compat-options allow-unsafe-ext', 'is not allowed'],
        'netrc location' => ['--netrc-location /tmp', 'is not allowed'],
        'client certificate' => ['--client-certificate /tmp/cert.pem', 'is not allowed'],
        'remote components' => ['--remote-components ejs:github', 'is not allowed'],
        'print traffic' => ['--print-traffic', 'is not allowed'],
        'extra URL' => ['https://evil.example/other', '"https://evil.example/other" is not an option'],
        'URL after --' => ['-- --exec x', '"--exec" is not an option'],
        'unknown option' => ['--OUTPUT /tmp/x', 'yt-dlp does not accept these arguments: no such option: --OUTPUT'],
        'option that exits' => ['--version', 'yt-dlp does not accept these arguments'],
    ]);
});

describe('the reviewed option-destination snapshot', function () {
    beforeEach(fn () => ytDlpZipappAvailable() || $this->markTestSkipped('python3 or the yt-dlp zipapp is not available'));

    it('matches the option destinations of the installed yt-dlp', function () {
        $process = new Process(['python3', '-I', resource_path('python/inspect_ytdlp_args.py'), '--list-destinations']);
        $process->run();
        $report = json_decode($process->getOutput(), true);
        $snapshot = json_decode((string) file_get_contents(resource_path('python/ytdlp_known_destinations.json')), true);

        // On failure: review the new destinations for safety (deny the risky
        // ones in YtDlpArguments::DENIED_DESTINATIONS), then regenerate the
        // snapshot with --list-destinations.
        expect($process->getExitCode())->toBe(0)
            ->and($report['destinations'])->toBe($snapshot['destinations']);
    });
});
