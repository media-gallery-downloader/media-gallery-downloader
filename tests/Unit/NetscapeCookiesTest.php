<?php

use App\Services\Sites\NetscapeCookies;

describe('NetscapeCookies::looksLikeNetscape', function () {
    it('accepts a valid Netscape cookies file for any site', function (string $content) {
        expect(NetscapeCookies::looksLikeNetscape($content))->toBeTrue();
    })->with([
        'reddit with header' => ["# Netscape HTTP Cookie File\n.reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\tabc123\n"],
        'youtube no header' => [".youtube.com\tTRUE\t/\tTRUE\t9999999999\tLOGIN_INFO\txyz789\n"],
        'httponly prefix without header' => ["#HttpOnly_.reddit.com\tTRUE\t/\tTRUE\t9999999999\ttoken\tval\n"],
    ]);

    it('rejects content that is not a cookies file', function (string $content) {
        expect(NetscapeCookies::looksLikeNetscape($content))->toBeFalse();
    })->with([
        'html page' => ['<!DOCTYPE html><html><body>not a cookies file</body></html>'],
        'empty' => [''],
        'plain text without tab-delimited cookie lines' => ["just some notes\nno tabs here\n"],
        'only comments' => ["# Netscape-ish\n# but no actual cookie lines\n"],
    ]);
});

describe('NetscapeCookies::parse', function () {
    it('returns each cookie line with its domain, skipping comments and blank lines', function () {
        $content = "# Netscape HTTP Cookie File\r\n"
            ."# comment\r\n"
            ."\r\n"
            ."#HttpOnly_.Reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\tabc\r\n"
            ."www.reddit.com\tFALSE\t/\tTRUE\t9999999999\tcsv\t2\r\n";

        expect(NetscapeCookies::parse($content))->toBe([
            ['domain' => 'reddit.com', 'line' => "#HttpOnly_.Reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\tabc"],
            ['domain' => 'www.reddit.com', 'line' => "www.reddit.com\tFALSE\t/\tTRUE\t9999999999\tcsv\t2"],
        ]);
    });
});

describe('NetscapeCookies::normalize', function () {
    it('puts the Netscape header first and uses LF line endings', function () {
        $content = ".reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\tabc\r\n";

        expect(NetscapeCookies::normalize($content))->toBe(
            "# Netscape HTTP Cookie File\n.reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\tabc\n"
        );
    });
});

describe('NetscapeCookies lines yt-dlp would reject (it echoes them, value included)', function () {
    it('skips lines without exactly seven fields', function () {
        expect(NetscapeCookies::parse(".reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\tabc\textra\n"))->toBe([]);
    });

    it('skips lines whose expiry is not empty or a number', function (string $expiry) {
        expect(NetscapeCookies::parse(".reddit.com\tTRUE\t/\tTRUE\t{$expiry}\treddit_session\tabc\n"))->toBe([]);
    })->with(['negative' => ['-1'], 'text' => ['never']]);

    it('keeps session cookies with an empty expiry', function () {
        expect(NetscapeCookies::parse(".reddit.com\tTRUE\t/\tTRUE\t\tcsv\t2\n"))->toHaveCount(1);
    });

    it('ignores a UTF-8 byte order mark', function () {
        expect(NetscapeCookies::parse("\xEF\xBB\xBF.reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\tabc\n"))
            ->toBe([['domain' => 'reddit.com', 'line' => ".reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\tabc"]]);
    });

    it('makes the include-subdomains flag agree with the leading dot, as yt-dlp requires', function () {
        expect(NetscapeCookies::normalize("www.example.com\tTRUE\t/\tTRUE\t9999999999\ta\t1\n"
            ."#HttpOnly_.example.com\tFALSE\t/\tTRUE\t9999999999\tb\t2\n"))
            ->toBe("# Netscape HTTP Cookie File\n"
                ."www.example.com\tFALSE\t/\tTRUE\t9999999999\ta\t1\n"
                ."#HttpOnly_.example.com\tTRUE\t/\tTRUE\t9999999999\tb\t2\n");
    });
});
