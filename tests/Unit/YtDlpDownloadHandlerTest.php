<?php

use App\Services\Download\YtDlpDownloadHandler;

function findDisallowedFlag(array $tokens): ?string
{
    $handler = new YtDlpDownloadHandler;
    $method = new ReflectionMethod($handler, 'findDisallowedFlag');
    $method->setAccessible(true);

    return $method->invoke($handler, $tokens);
}

function parseYtDlpError(string $output): string
{
    $handler = new YtDlpDownloadHandler;
    $method = new ReflectionMethod($handler, 'parseError');
    $method->setAccessible(true);

    return $method->invoke($handler, $output);
}

describe('YtDlpDownloadHandler extra-arg validation', function () {
    it('flags dangerous arguments', function (array $tokens, string $expected) {
        expect(findDisallowedFlag($tokens))->toBe($expected);
    })->with([
        'exec' => [['--exec', 'rm -rf /'], '--exec'],
        'short output' => [['-o', '/etc/cron.d/x'], '-o'],
        'paths' => [['-P', '/etc'], '-P'],
        'config-location with =' => [['--config-location=/tmp/evil'], '--config-location'],
        'long flag case-insensitive' => [['--OUTPUT', 'x'], '--OUTPUT'],
        'external downloader' => [['--external-downloader', 'curl'], '--external-downloader'],
    ]);

    it('allows safe arguments', function (array $tokens) {
        expect(findDisallowedFlag($tokens))->toBeNull();
    })->with([
        'rate limit' => [['--limit-rate', '2M']],
        'write subs' => [['--write-subs', '--sub-langs', 'en']],
        'empty' => [[]],
        'lowercase -p is not -P' => [['-p', 'somepassword']],
    ]);
});

describe('YtDlpDownloadHandler error parsing', function () {
    it('maps "authentication is required" to a cookies hint', function () {
        $raw = 'ERROR: [Reddit] 1txiqwi: Account authentication is required. Use --cookies, ...';

        expect(parseYtDlpError($raw))
            ->toContain('requires you to be logged in')
            ->and(parseYtDlpError($raw))->toContain('cookies.txt');
    });

    it('maps expired-cookie errors to a friendly message', function () {
        expect(parseYtDlpError('The provided YouTube account cookies are no longer valid'))
            ->toContain('cookies have expired');
    });

    it('falls back to the raw yt-dlp error otherwise', function () {
        expect(parseYtDlpError('Some unexpected failure'))
            ->toBe('yt-dlp error: Some unexpected failure');
    });
});
