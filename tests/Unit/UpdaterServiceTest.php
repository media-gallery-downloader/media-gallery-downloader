<?php

use App\Services\UpdaterService;

describe('UpdaterService', function () {
    describe('checkAndUpdateYtdlp', function () {
        it('returns a boolean', function () {
            $service = new UpdaterService;

            // This will check the actual yt-dlp installation
            $result = $service->checkAndUpdateYtdlp();

            // Result depends on whether yt-dlp is installed and has network
            expect($result)->toBeBool();
        });
    });

    describe('installBinaryInPlace', function () {
        it('overwrites an existing binary in place instead of replacing the file', function () {
            // Regression guard for the deno (and yt-dlp) update permission bug:
            // the install MUST overwrite the existing file's contents (same
            // inode) rather than mv/replace it, because the parent directory is
            // not writable by the app user in production.
            $dir = sys_get_temp_dir().'/mgd_updater_'.uniqid();
            mkdir($dir, 0755, true);

            $source = $dir.'/new-binary';
            $dest = $dir.'/installed-binary';
            file_put_contents($source, "#!/bin/sh\necho new\n");
            file_put_contents($dest, "#!/bin/sh\necho old\n");
            chmod($dest, 0755);

            $originalInode = fileinode($dest);

            $service = new UpdaterService;
            $method = new ReflectionMethod($service, 'installBinaryInPlace');
            $method->setAccessible(true);

            $result = $method->invoke($service, $source, $dest);

            expect($result)->toBeTrue()
                ->and(file_get_contents($dest))->toBe("#!/bin/sh\necho new\n")
                ->and(fileinode($dest))->toBe($originalInode) // overwritten in place, not mv'd
                ->and(is_executable($dest))->toBeTrue();

            @unlink($source);
            @unlink($dest);
            @rmdir($dir);
        })->skip(! str_contains(strtolower(PHP_OS), 'linux') && PHP_OS !== 'Darwin', 'Requires a POSIX filesystem');
    });
});
