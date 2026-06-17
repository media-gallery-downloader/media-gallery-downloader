<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds human-readable, filesystem- and URL-safe media filenames of the form
 * `<sanitized-title>-<unix-seconds>.<ext>` and the public URLs that serve them.
 *
 * Single source of truth for the naming rules used by every ingestion path and
 * by the `media:rename-files` migration command.
 */
class MediaFilename
{
    /** Linux NAME_MAX. Filenames must not exceed this many bytes. */
    public const MAX_BYTES = 255;

    /**
     * Clean a title into a safe filename fragment: strip path separators,
     * Windows-reserved characters and control characters; collapse whitespace;
     * trim surrounding spaces/dots. Unicode letters, digits and emoji are kept.
     */
    public static function sanitizeTitle(string $title): string
    {
        // Path separators, Windows-reserved chars, and ASCII control chars -> space.
        $title = preg_replace('#[/\\\\:*?"<>|\x00-\x1F\x7F]#u', ' ', $title) ?? '';
        // Remove any remaining Unicode control/format characters (e.g. the
        // zero-width joiner inside emoji like 🏃‍♀️). League Flysystem rejects
        // paths matching \p{C}, so these must never survive into a filename.
        $title = preg_replace('#\p{C}#u', '', $title) ?? '';
        // Collapse any run of whitespace to a single space.
        $title = preg_replace('/\s+/u', ' ', $title) ?? '';
        // Windows strips trailing dots/spaces; trim both ends of either.
        $title = trim($title, ' .');

        return $title === '' ? 'video' : $title;
    }

    /**
     * Build `<title>-<timestamp>.<ext>`, capping the total at MAX_BYTES bytes
     * without splitting a multibyte character.
     */
    public static function build(string $title, int $timestamp, string $extension): string
    {
        $title = self::sanitizeTitle($title);
        $suffix = self::suffix($timestamp, $extension);

        $title = self::capToBytes($title, self::MAX_BYTES - strlen($suffix));

        return $title.$suffix;
    }

    /**
     * Build a filename that does not already exist in `$directory` on `$disk`,
     * appending `-2`, `-3`, … (re-capped to MAX_BYTES) on collision.
     */
    public static function generate(
        string $title,
        int $timestamp,
        string $extension,
        string $disk = 'public',
        string $directory = 'media'
    ): string {
        $base = self::build($title, $timestamp, $extension);
        $disposable = Storage::disk($disk);

        if (! $disposable->exists($directory.'/'.$base)) {
            return $base;
        }

        $extPart = self::extPart($extension);
        $stem = ($extPart !== '' && str_ends_with($base, $extPart))
            ? substr($base, 0, -strlen($extPart))
            : $base;

        for ($n = 2; $n <= 1000; $n++) {
            $numSuffix = '-'.$n;
            $cappedStem = self::capToBytes($stem, self::MAX_BYTES - strlen($numSuffix) - strlen($extPart));
            $candidate = $cappedStem.$numSuffix.$extPart;

            if (! $disposable->exists($directory.'/'.$candidate)) {
                return $candidate;
            }
        }

        // Pathological fallback: guaranteed-unique short suffix.
        return $stem.'-'.substr(Str::uuid()->toString(), 0, 8).$extPart;
    }

    /**
     * The thumbnail filename for a given video filename: `<base>_thumb.jpg`.
     */
    public static function thumbnailName(string $videoFileName): string
    {
        return pathinfo($videoFileName, PATHINFO_FILENAME).'_thumb.jpg';
    }

    /**
     * Public URL for a stored path, with each path segment URL-encoded so that
     * spaces and special characters are safe in `src`/`href` attributes. The
     * underlying `path`/`file_name` stay raw for filesystem operations.
     */
    public static function urlFor(string $path): string
    {
        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));

        return Storage::url($encoded);
    }

    private static function suffix(int $timestamp, string $extension): string
    {
        return '-'.$timestamp.self::extPart($extension);
    }

    private static function extPart(string $extension): string
    {
        $ext = ltrim(strtolower($extension), '.');

        return $ext !== '' ? '.'.$ext : '';
    }

    private static function capToBytes(string $value, int $maxBytes): string
    {
        if ($maxBytes <= 0) {
            return 'video';
        }

        if (strlen($value) > $maxBytes) {
            $value = rtrim(mb_strcut($value, 0, $maxBytes, 'UTF-8'), ' .');
        }

        return $value === '' ? 'video' : $value;
    }
}
