<?php

use App\Models\Site;
use App\Services\Sites\LegacyCookieImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->cookiesDir = useTemporaryCookiesDirectory();
    $this->legacyFile = storage_path('framework/testing/legacy_cookies_'.str_replace('.', '', uniqid('', true)).'.txt');
});

afterEach(function () {
    File::deleteDirectory($this->cookiesDir);
    File::delete($this->legacyFile);
});

it('splits the old cookies file into one site per domain, folding in subdomains', function () {
    $reddit = ".reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\tr1";
    $youtube = "#HttpOnly_.youtube.com\tTRUE\t/\tTRUE\t9999999999\tLOGIN_INFO\ty1";
    $youtubeWww = "www.youtube.com\tFALSE\t/\tTRUE\t9999999999\tPREF\ty2";
    $googleAccounts = "accounts.google.com\tFALSE\t/\tTRUE\t9999999999\tLSID\tg1";
    $google = ".google.com\tTRUE\t/\tTRUE\t9999999999\tSID\tg2";
    file_put_contents($this->legacyFile, "# Netscape HTTP Cookie File\n"
        .implode("\n", [$reddit, $youtube, $youtubeWww, $googleAccounts, $google])."\n");

    app(LegacyCookieImporter::class)->import($this->legacyFile);

    $sites = Site::orderBy('name')->get();
    expect($sites->pluck('name')->all())->toBe(['google.com', 'reddit.com', 'youtube.com'])
        ->and($sites->pluck('domains')->all())->toBe([['google.com'], ['reddit.com', 'redd.it'], ['youtube.com', 'youtu.be', 'youtube-nocookie.com']]);

    $cookieFile = fn (string $name): string => file_get_contents($sites->firstWhere('name', $name)->cookiesPath());
    expect($cookieFile('google.com'))->toBe("# Netscape HTTP Cookie File\n{$googleAccounts}\n{$google}\n")
        ->and($cookieFile('reddit.com'))->toBe("# Netscape HTTP Cookie File\n{$reddit}\n")
        ->and($cookieFile('youtube.com'))->toBe("# Netscape HTTP Cookie File\n{$youtube}\n{$youtubeWww}\n")
        ->and(file_exists($this->legacyFile))->toBeFalse();
});

it('does nothing when there is no old cookies file', function () {
    expect(app(LegacyCookieImporter::class)->import($this->legacyFile))->toBe([])
        ->and(Site::count())->toBe(0);
});

it('keeps an old file that contains no cookies', function () {
    file_put_contents($this->legacyFile, '<!DOCTYPE html><html></html>');

    expect(app(LegacyCookieImporter::class)->import($this->legacyFile))->toBe([])
        ->and(Site::count())->toBe(0)
        ->and(file_exists($this->legacyFile))->toBeTrue();
});

it('keeps the old file and creates no site when cookies cannot be written', function () {
    config(['mgd.cookies_path' => '/proc/mgd-cookies-not-writable']);
    file_put_contents($this->legacyFile, ".reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\tr1\n");

    expect(app(LegacyCookieImporter::class)->import($this->legacyFile))->toBe([])
        ->and(Site::count())->toBe(0)
        ->and(file_exists($this->legacyFile))->toBeTrue();
});

describe('LegacyCookieImporter on a file it cannot change', function () {
    beforeEach(function () {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root ignores file permissions');
        }

        // The old file in a directory the app may not write to (like a :ro or
        // single-file bind mount): it can be read but not deleted.
        $this->lockedDir = storage_path('framework/testing/locked_'.str_replace('.', '', uniqid('', true)));
        File::ensureDirectoryExists($this->lockedDir);
        $this->lockedFile = $this->lockedDir.'/cookies.txt';
        file_put_contents($this->lockedFile, ".reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\tr1\n");
        chmod($this->lockedDir, 0555);
    });

    afterEach(function () {
        if (isset($this->lockedDir)) {
            chmod($this->lockedDir, 0755);
            File::deleteDirectory($this->lockedDir);
        }
    });

    it('imports the cookies and keeps going when the old file cannot be deleted', function () {
        $sites = app(LegacyCookieImporter::class)->import($this->lockedFile);

        expect(array_map(fn (Site $site) => $site->name, $sites))->toBe(['reddit.com'])
            ->and(file_exists($this->lockedFile))->toBeTrue();
    });

    it('does not create the same sites again on a later start', function () {
        app(LegacyCookieImporter::class)->import($this->lockedFile);
        app(LegacyCookieImporter::class)->import($this->lockedFile);

        expect(Site::pluck('name')->all())->toBe(['reddit.com']);
    });

    it('keeps going when the old file cannot be read', function () {
        chmod($this->lockedFile, 0000);

        expect(app(LegacyCookieImporter::class)->import($this->lockedFile))->toBe([])
            ->and(Site::count())->toBe(0);
    });
});

describe('LegacyCookieImporter short-link domains', function () {
    it('gives YouTube and Reddit their short-link domains, which used to get the same cookies', function () {
        file_put_contents($this->legacyFile, ".youtube.com\tTRUE\t/\tTRUE\t9999999999\tLOGIN_INFO\ty1\n"
            .".reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\tr1\n");

        app(LegacyCookieImporter::class)->import($this->legacyFile);

        expect(Site::where('name', 'youtube.com')->sole()->domains)->toBe(['youtube.com', 'youtu.be', 'youtube-nocookie.com'])
            ->and(Site::where('name', 'reddit.com')->sole()->domains)->toBe(['reddit.com', 'redd.it']);
    });

    it('leaves a short-link domain to its own site when the file has cookies for it', function () {
        file_put_contents($this->legacyFile, ".reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\tr1\n"
            .".redd.it\tTRUE\t/\tTRUE\t9999999999\tloid\tx1\n");

        app(LegacyCookieImporter::class)->import($this->legacyFile);

        expect(Site::where('name', 'reddit.com')->sole()->domains)->toBe(['reddit.com'])
            ->and(Site::where('name', 'redd.it')->sole()->domains)->toBe(['redd.it']);
    });
});
