<?php

use App\Services\FaststartService;
use Symfony\Component\Process\Process;

function writeBoxes(string $path, array $boxes): void
{
    $data = '';
    foreach ($boxes as [$type, $payloadLen]) {
        $data .= pack('N', 8 + $payloadLen).$type.str_repeat("\0", $payloadLen);
    }
    file_put_contents($path, $data);
}

describe('FaststartService::isFaststarted', function () {
    beforeEach(function () {
        $this->dir = sys_get_temp_dir().'/mgd_fs_'.uniqid();
        mkdir($this->dir);
    });

    afterEach(function () {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
    });

    it('reports true when moov precedes mdat', function () {
        $f = $this->dir.'/front.mp4';
        writeBoxes($f, [['ftyp', 8], ['moov', 4], ['mdat', 8]]);

        expect((new FaststartService)->isFaststarted($f))->toBeTrue();
    });

    it('reports false when mdat precedes moov', function () {
        $f = $this->dir.'/back.mp4';
        writeBoxes($f, [['ftyp', 8], ['mdat', 64], ['moov', 4]]);

        expect((new FaststartService)->isFaststarted($f))->toBeFalse();
    });

    it('does not support non-mp4 containers', function () {
        $f = $this->dir.'/clip.webm';
        file_put_contents($f, 'not an mp4');

        expect((new FaststartService)->supports($f))->toBeFalse();
    });
});

describe('FaststartService::optimize', function () {
    it('moves the moov atom to the front and is idempotent', function () {
        $dir = sys_get_temp_dir().'/mgd_fs_real_'.uniqid();
        mkdir($dir);
        $mp4 = $dir.'/clip.mp4';

        // A default mp4 mux writes moov at the END (not faststart).
        $gen = new Process([
            'ffmpeg', '-y', '-f', 'lavfi', '-i', 'testsrc=duration=1:size=128x96:rate=5',
            '-c:v', 'mpeg4', '-pix_fmt', 'yuv420p', $mp4,
        ]);
        $gen->run();

        $svc = new FaststartService;

        expect(is_file($mp4))->toBeTrue()
            ->and($svc->isFaststarted($mp4))->toBeFalse()   // moov at end
            ->and($svc->optimize($mp4))->toBeTrue()          // remuxed
            ->and($svc->isFaststarted($mp4))->toBeTrue()     // moov now at front
            ->and($svc->optimize($mp4))->toBeFalse();        // idempotent no-op

        array_map('unlink', glob($dir.'/*') ?: []);
        @rmdir($dir);
    })->skip(! @is_executable(trim((string) shell_exec('command -v ffmpeg'))), 'ffmpeg not available');
});
