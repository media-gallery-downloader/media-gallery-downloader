<?php

use App\Helpers\FormatHelper;

describe('FormatHelper', function () {
    describe('formatBytes', function () {
        it('formats bytes correctly', function () {
            expect(FormatHelper::formatBytes(0))->toBe('0 B');
            expect(FormatHelper::formatBytes(500))->toBe('500 B');
            expect(FormatHelper::formatBytes(1023))->toBe('1023 B');
        });

        it('formats kilobytes correctly', function () {
            expect(FormatHelper::formatBytes(1024))->toBe('1 KB');
            expect(FormatHelper::formatBytes(1536))->toBe('1.5 KB');
            expect(FormatHelper::formatBytes(10240))->toBe('10 KB');
        });

        it('formats megabytes correctly', function () {
            expect(FormatHelper::formatBytes(1048576))->toBe('1 MB');
            expect(FormatHelper::formatBytes(1572864))->toBe('1.5 MB');
            expect(FormatHelper::formatBytes(104857600))->toBe('100 MB');
        });

        it('formats gigabytes correctly', function () {
            expect(FormatHelper::formatBytes(1073741824))->toBe('1 GB');
            expect(FormatHelper::formatBytes(5368709120))->toBe('5 GB');
        });

        it('formats terabytes correctly', function () {
            expect(FormatHelper::formatBytes(1099511627776))->toBe('1 TB');
            expect(FormatHelper::formatBytes(2199023255552))->toBe('2 TB');
        });

        it('respects precision parameter', function () {
            expect(FormatHelper::formatBytes(1536, 0))->toBe('2 KB');
            expect(FormatHelper::formatBytes(1536, 1))->toBe('1.5 KB');
            expect(FormatHelper::formatBytes(1536, 3))->toBe('1.5 KB');
        });

        it('handles float input', function () {
            expect(FormatHelper::formatBytes(1024.5))->toBe('1 KB');
            expect(FormatHelper::formatBytes(1048576.0))->toBe('1 MB');
        });
    });

    describe('parseBytes', function () {
        it('parses bytes correctly', function () {
            expect(FormatHelper::parseBytes('500 B'))->toBe(500);
            expect(FormatHelper::parseBytes('1023B'))->toBe(1023);
            expect(FormatHelper::parseBytes('0 B'))->toBe(0);
        });

        it('parses kilobytes correctly', function () {
            expect(FormatHelper::parseBytes('1 KB'))->toBe(1024);
            expect(FormatHelper::parseBytes('10KB'))->toBe(10240);
        });

        it('parses megabytes correctly', function () {
            expect(FormatHelper::parseBytes('1 MB'))->toBe(1048576);
            expect(FormatHelper::parseBytes('100 MB'))->toBe(104857600);
        });

        it('parses gigabytes correctly', function () {
            expect(FormatHelper::parseBytes('1 GB'))->toBe(1073741824);
            expect(FormatHelper::parseBytes('5 GB'))->toBe(5368709120);
        });

        it('parses terabytes correctly', function () {
            expect(FormatHelper::parseBytes('1 TB'))->toBe(1099511627776);
        });

        it('handles lowercase units', function () {
            expect(FormatHelper::parseBytes('1 kb'))->toBe(1024);
            expect(FormatHelper::parseBytes('1 mb'))->toBe(1048576);
            expect(FormatHelper::parseBytes('1 gb'))->toBe(1073741824);
        });

        it('handles numeric string without unit', function () {
            expect(FormatHelper::parseBytes('1024'))->toBe(1024);
            expect(FormatHelper::parseBytes('0'))->toBe(0);
        });

        it('handles whitespace', function () {
            expect(FormatHelper::parseBytes('  1 KB  '))->toBe(1024);
            expect(FormatHelper::parseBytes('1  MB'))->toBe(1048576);
        });
    });
});
