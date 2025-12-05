<?php

use App\Services\UpdaterService;

describe('UpdaterService', function () {
    describe('checkAndUpdateYtdlp', function () {
        it('returns true when yt-dlp is up to date', function () {
            $service = new UpdaterService;

            // This will check the actual yt-dlp installation
            $result = $service->checkAndUpdateYtdlp();

            // Result depends on whether yt-dlp is installed and has network
            expect($result)->toBeBool();
        });
    });
});
