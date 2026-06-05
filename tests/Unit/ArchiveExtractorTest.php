<?php

use App\Services\Upload\ArchiveExtractor;

describe('ArchiveExtractor size guard', function () {
    beforeEach(function () {
        $this->workDir = sys_get_temp_dir().'/mgd_archive_'.uniqid();
        mkdir($this->workDir, 0755, true);
    });

    afterEach(function () {
        if (is_dir($this->workDir)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->workDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) {
                $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
            }
            @rmdir($this->workDir);
        }
    });

    it('rejects a zip whose uncompressed contents exceed the cap', function () {
        $zipPath = $this->workDir.'/big.zip';
        $extractDir = $this->workDir.'/out';
        mkdir($extractDir);

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('big.bin', str_repeat('A', 50_000)); // 50 KB uncompressed
        $zip->close();

        config(['mgd.downloads.max_archive_bytes' => 1_000]); // 1 KB cap

        expect(fn () => (new ArchiveExtractor)->extract($zipPath, $extractDir))
            ->toThrow(Exception::class, 'maximum allowed uncompressed size');
    })->skip(! class_exists(ZipArchive::class), 'ext-zip not available');

    it('allows a zip within the cap (or when unlimited)', function () {
        $zipPath = $this->workDir.'/ok.zip';
        $extractDir = $this->workDir.'/out';
        mkdir($extractDir);

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('small.txt', 'hello');
        $zip->close();

        config(['mgd.downloads.max_archive_bytes' => 0]); // unlimited

        (new ArchiveExtractor)->extract($zipPath, $extractDir);

        expect(file_exists($extractDir.'/small.txt'))->toBeTrue();
    })->skip(! class_exists(ZipArchive::class), 'ext-zip not available');
});
