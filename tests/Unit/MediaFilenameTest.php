<?php

use App\Support\MediaFilename;
use Illuminate\Support\Facades\Storage;

describe('MediaFilename::sanitizeTitle', function () {
    it('keeps a normal title with spaces unchanged', function () {
        expect(MediaFilename::sanitizeTitle('My Day at the Zoo'))->toBe('My Day at the Zoo');
    });

    it('replaces path separators and reserved chars with spaces', function () {
        expect(MediaFilename::sanitizeTitle('a/b\\c:d*e?f"g<h>i|j'))->toBe('a b c d e f g h i j');
    });

    it('strips control characters and collapses whitespace', function () {
        expect(MediaFilename::sanitizeTitle("a\tb\n\nc   d"))->toBe('a b c d');
    });

    it('trims leading/trailing spaces and dots', function () {
        expect(MediaFilename::sanitizeTitle('  ...Hello World...  '))->toBe('Hello World');
    });

    it('preserves unicode letters and emoji', function () {
        expect(MediaFilename::sanitizeTitle('café 日本 🎬'))->toBe('café 日本 🎬');
    });

    it('falls back to "video" when nothing usable remains', function () {
        expect(MediaFilename::sanitizeTitle('///'))->toBe('video')
            ->and(MediaFilename::sanitizeTitle(''))->toBe('video');
    });

    it('strips zero-width / unicode control characters so paths are Flysystem-safe', function () {
        // 🏃‍♀️ = runner + ZWJ (U+200D) + female sign + VS16 (U+FE0F).
        $title = "I made QT sprint for this video \u{1F3C3}\u{200D}\u{2640}\u{FE0F} #maya #beach";

        $clean = MediaFilename::sanitizeTitle($title);

        // Flysystem rejects any path containing \p{C}; none must survive.
        expect(preg_match('/\p{C}/u', $clean))->toBe(0)
            ->and($clean)->toContain('QT sprint')
            ->and($clean)->toContain('#maya');
    });
});

describe('MediaFilename::build', function () {
    it('produces <title>-<timestamp>.<ext>', function () {
        expect(MediaFilename::build('My Day at the Zoo', 1749134400, 'mkv'))
            ->toBe('My Day at the Zoo-1749134400.mkv');
    });

    it('normalises the extension (lowercase, no leading dot)', function () {
        expect(MediaFilename::build('Clip', 1749134400, '.MP4'))->toBe('Clip-1749134400.mp4');
    });

    it('uses the fallback title for empty input', function () {
        expect(MediaFilename::build('', 1749134400, 'mkv'))->toBe('video-1749134400.mkv');
    });

    it('caps the total filename at 255 bytes without splitting a multibyte char', function () {
        $title = str_repeat('あ', 300); // 3 bytes each = 900 bytes
        $name = MediaFilename::build($title, 1749134400, 'mkv');

        expect(strlen($name))->toBeLessThanOrEqual(255)
            ->and(mb_check_encoding($name, 'UTF-8'))->toBeTrue()
            ->and(str_ends_with($name, '-1749134400.mkv'))->toBeTrue();
    });
});

describe('MediaFilename::thumbnailName', function () {
    it('derives <base>_thumb.jpg from the video filename', function () {
        expect(MediaFilename::thumbnailName('My Day at the Zoo-1749134400.mkv'))
            ->toBe('My Day at the Zoo-1749134400_thumb.jpg');
    });
});

describe('MediaFilename::urlFor', function () {
    it('url-encodes the filename component but preserves path separators', function () {
        Storage::fake('public');

        $url = MediaFilename::urlFor('media/My Day at the Zoo-1749134400.mkv');

        expect($url)->toContain('media/My%20Day%20at%20the%20Zoo-1749134400.mkv')
            ->and($url)->not->toContain(' '); // no raw spaces in the served URL
    });
});

describe('MediaFilename::generate', function () {
    it('returns the plain name when nothing exists', function () {
        Storage::fake('public');

        expect(MediaFilename::generate('My Day at the Zoo', 1749134400, 'mkv'))
            ->toBe('My Day at the Zoo-1749134400.mkv');
    });

    it('appends -2, -3 on collision', function () {
        Storage::fake('public');
        Storage::disk('public')->put('media/Clip-1749134400.mkv', 'x');

        expect(MediaFilename::generate('Clip', 1749134400, 'mkv'))->toBe('Clip-1749134400-2.mkv');

        Storage::disk('public')->put('media/Clip-1749134400-2.mkv', 'x');
        expect(MediaFilename::generate('Clip', 1749134400, 'mkv'))->toBe('Clip-1749134400-3.mkv');
    });
});
