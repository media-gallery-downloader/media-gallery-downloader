<?php

use App\Services\UpdaterService;
use Illuminate\Support\Facades\Log;

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

    // The image ships for linux/amd64 and linux/arm64, so the in-app Deno
    // updater must fetch the build matching the machine it runs on.
    describe('denoArchitecture', function () {
        it('maps the machine name to the Deno release architecture', function (string $machine, string $expected) {
            expect((new UpdaterService)->denoArchitecture($machine))->toBe($expected);
        })->with([
            'x86_64' => ['x86_64', 'x86_64'],
            'amd64' => ['amd64', 'x86_64'],
            'AMD64' => ['AMD64', 'x86_64'],
            'aarch64' => ['aarch64', 'aarch64'],
            'arm64' => ['arm64', 'aarch64'],
            'padded aarch64' => [" aarch64\n", 'aarch64'],
        ]);

        it('returns null when Deno has no Linux build for the machine', function (string $machine) {
            expect((new UpdaterService)->denoArchitecture($machine))->toBeNull();
        })->with(['armv7l', 'i686', 'riscv64', 's390x', 'ppc64le', '']);

        it('defaults to the machine PHP is running on', function () {
            $service = new UpdaterService;

            expect($service->denoArchitecture())->toBe($service->denoArchitecture(php_uname('m')));
        });
    });

    describe('denoDownloadUrl', function () {
        it('points at the latest release asset for the architecture', function (string $machine, string $asset) {
            expect((new UpdaterService)->denoDownloadUrl($machine))
                ->toBe('https://github.com/denoland/deno/releases/latest/download/'.$asset);
        })->with([
            'amd64' => ['x86_64', 'deno-x86_64-unknown-linux-gnu.zip'],
            'arm64' => ['aarch64', 'deno-aarch64-unknown-linux-gnu.zip'],
        ]);

        it('returns null when Deno has no Linux build for the machine', function () {
            expect((new UpdaterService)->denoDownloadUrl('armv7l'))->toBeNull();
        });
    });

    describe('downloadAndInstallDeno', function () {
        it('fails without downloading anything on an unsupported architecture', function () {
            Log::spy();

            $service = new class extends UpdaterService
            {
                public function denoArchitecture(?string $machine = null): ?string
                {
                    return null;
                }
            };

            expect($service->downloadAndInstallDeno())->toBeFalse();

            Log::shouldHaveReceived('warning')
                ->once()
                ->withArgs(fn (string $message) => str_contains($message, 'no Linux release build for machine architecture'));
        });
    });
});
