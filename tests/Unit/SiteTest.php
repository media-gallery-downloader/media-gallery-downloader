<?php

use App\Models\Site;
use App\Services\Sites\InvalidCookiesException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->cookiesDir = useTemporaryCookiesDirectory();
});

afterEach(function () {
    File::deleteDirectory($this->cookiesDir);
});

describe('Site::normalizeDomain', function () {
    it('normalizes what people type or paste into a bare hostname', function (string $input, ?string $expected) {
        expect(Site::normalizeDomain($input))->toBe($expected);
    })->with([
        'plain' => ['reddit.com', 'reddit.com'],
        'case and spaces' => ['  Reddit.COM  ', 'reddit.com'],
        'pasted URL' => ['https://www.reddit.com/r/videos/', 'reddit.com'],
        'www. prefix, so youtube.com/shorts and m.youtube.com still match' => ['www.youtube.com', 'youtube.com'],
        'wildcard' => ['*.redd.it', 'redd.it'],
        'cookie-style leading dot' => ['.youtube.com', 'youtube.com'],
        'trailing dot' => ['youtube.com.', 'youtube.com'],
        'userinfo, port and path without a scheme' => ['user:pass@example.com:8080/path', 'example.com'],
        'spaces inside' => ['bad domain', null],
        'empty' => ['', null],
    ]);
});

describe('Site domains', function () {
    it('stores normalized, unique domains and drops invalid ones', function () {
        $site = makeSite('Reddit', ['Reddit.com', 'https://old.reddit.com/r/x', 'reddit.com', 'bad domain']);

        expect($site->fresh()->domains)->toBe(['reddit.com', 'old.reddit.com']);
    });
});

describe('Site cookie file', function () {
    it('keeps each site\'s cookies in its own file under the cookies directory', function () {
        $site = makeSite('Reddit', ['reddit.com']);

        expect($site->cookiesPath())->toBe($this->cookiesDir.'/site-'.$site->id.'.txt');
    });

    it('reports whether the site has a cookie file', function () {
        $site = makeSite('Reddit', ['reddit.com']);
        expect($site->hasCookies())->toBeFalse();

        File::ensureDirectoryExists($this->cookiesDir);
        file_put_contents($site->cookiesPath(), "# Netscape HTTP Cookie File\n");

        expect($site->hasCookies())->toBeTrue();
    });

    it('deletes the cookie file when the site is deleted', function () {
        $site = makeSite('Reddit', ['reddit.com']);
        File::ensureDirectoryExists($this->cookiesDir);
        file_put_contents($site->cookiesPath(), "# Netscape HTTP Cookie File\n");

        $site->delete();

        expect(file_exists($site->cookiesPath()))->toBeFalse();
    });
});

describe('Site::storeCookies', function () {
    it('stores pasted cookies with the Netscape header and LF line endings', function () {
        $site = makeSite('Reddit', ['reddit.com']);

        $site->storeCookies("#HttpOnly_.reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\tabc\r\n"
            ."www.reddit.com\tFALSE\t/\tTRUE\t9999999999\tcsv\t2\r\n");

        expect(file_get_contents($site->cookiesPath()))->toBe("# Netscape HTTP Cookie File\n"
            ."#HttpOnly_.reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\tabc\n"
            ."www.reddit.com\tFALSE\t/\tTRUE\t9999999999\tcsv\t2\n")
            ->and($site->fresh()->cookies_updated_at)->not->toBeNull();
    });

    it('keeps the cookie file readable by the app user only', function () {
        $site = makeSite('Reddit', ['reddit.com']);

        $site->storeCookies(".reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\tabc\n");

        clearstatcache();
        expect(fileperms($site->cookiesPath()) & 0777)->toBe(0600)
            ->and(fileperms($this->cookiesDir) & 0777)->toBe(0700);
    });

    it('refuses content without any cookie lines', function () {
        $site = makeSite('Reddit', ['reddit.com']);

        expect(fn () => $site->storeCookies('<!DOCTYPE html><html></html>'))
            ->toThrow(InvalidCookiesException::class);
        expect($site->hasCookies())->toBeFalse();
    });

    it('refuses cookies for other domains and names the domains it found', function () {
        $site = makeSite('Reddit', ['reddit.com']);

        expect(fn () => $site->storeCookies(".youtube.com\tTRUE\t/\tTRUE\t9999999999\tLOGIN_INFO\tx\n"
            .".google.com\tTRUE\t/\tTRUE\t9999999999\tSID\ty\n"))
            ->toThrow(InvalidCookiesException::class, 'youtube.com, google.com');
        expect($site->hasCookies())->toBeFalse();
    });

    it('accepts cookies set on a parent of the site domain', function () {
        $site = makeSite('Google accounts', ['accounts.google.com']);

        $site->storeCookies(".google.com\tTRUE\t/\tTRUE\t9999999999\tSID\ty\n");

        expect($site->hasCookies())->toBeTrue();
    });
});

describe('Site::clearCookies', function () {
    it('removes the cookie file and its timestamp', function () {
        $site = makeSite('Reddit', ['reddit.com']);
        $site->storeCookies(".reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\tabc\n");

        $site->clearCookies();

        expect($site->hasCookies())->toBeFalse()
            ->and($site->fresh()->cookies_updated_at)->toBeNull();
    });
});

describe('Site::cookieSummary', function () {
    it('counts the stored cookies and lists their domains', function () {
        $site = makeSite('Reddit', ['reddit.com']);
        $site->storeCookies(".reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\tabc\n"
            ."#HttpOnly_.reddit.com\tTRUE\t/\tTRUE\t9999999999\ttoken_v2\tdef\n"
            ."www.reddit.com\tFALSE\t/\tTRUE\t9999999999\tcsv\t2\n");

        expect($site->cookieSummary())->toBe(['count' => 3, 'domains' => ['reddit.com', 'www.reddit.com']]);
    });

    it('is null when the site has no cookies', function () {
        expect(makeSite('Reddit', ['reddit.com'])->cookieSummary())->toBeNull();
    });
});
