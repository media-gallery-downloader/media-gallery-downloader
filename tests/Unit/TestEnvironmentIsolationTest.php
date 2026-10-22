<?php

// No RefreshDatabase here on purpose: this must pass without running any
// migration, because the legacy-cookie import migration deletes the file it
// imports. If it fails, phpunit.xml no longer isolates cookie paths and a test
// run would import and delete the real storage/app/cookies.txt.

it('keeps the legacy cookie import away from the real cookies file', function () {
    expect(env('YTDLP_COOKIES_FILE'))->toBe('storage/framework/testing/no-legacy-cookies.txt');
});

it('keeps per-site cookie files out of the real storage directory', function () {
    expect(config('mgd.cookies_path'))->toBe('storage/framework/testing/cookies');
});
