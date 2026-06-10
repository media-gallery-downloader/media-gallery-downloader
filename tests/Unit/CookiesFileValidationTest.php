<?php

use App\Filament\Pages\Settings;

function looksLikeNetscapeCookies(string $content): bool
{
    $method = new ReflectionMethod(Settings::class, 'looksLikeNetscapeCookies');
    $method->setAccessible(true);

    return $method->invoke(null, $content);
}

describe('cookies.txt validation', function () {
    it('accepts a valid Netscape cookies file for any site', function (string $content) {
        expect(looksLikeNetscapeCookies($content))->toBeTrue();
    })->with([
        'reddit with header' => ["# Netscape HTTP Cookie File\n.reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\tabc123\n"],
        'youtube no header' => [".youtube.com\tTRUE\t/\tTRUE\t9999999999\tLOGIN_INFO\txyz789\n"],
        'httponly prefix without header' => ["#HttpOnly_.reddit.com\tTRUE\t/\tTRUE\t9999999999\ttoken\tval\n"],
    ]);

    it('rejects content that is not a cookies file', function (string $content) {
        expect(looksLikeNetscapeCookies($content))->toBeFalse();
    })->with([
        'html page' => ['<!DOCTYPE html><html><body>not a cookies file</body></html>'],
        'empty' => [''],
        'plain text without tab-delimited cookie lines' => ["just some notes\nno tabs here\n"],
        'only comments' => ["# Netscape-ish\n# but no actual cookie lines\n"],
    ]);
});
