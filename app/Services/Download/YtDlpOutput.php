<?php

namespace App\Services\Download;

/**
 * Making yt-dlp's error output safe to show and log.
 */
final class YtDlpOutput
{
    /**
     * yt-dlp quotes a cookie line it cannot load, value included. Those lines
     * never reach messages, the Logs page or the log files: a skipped line is
     * dropped, and an unreadable cookie file becomes a hint.
     */
    public static function withoutCookieLines(string $output): string
    {
        $lines = [];

        foreach (preg_split('/\r\n|\r|\n/', $output) ?: [] as $line) {
            if (str_contains($line, 'skipping cookie file entry')) {
                continue;
            }

            $lines[] = str_contains($line, 'invalid Netscape format cookies file')
                ? 'ERROR: yt-dlp could not read this site\'s stored cookies; paste them again in Settings → Sites.'
                : $line;
        }

        return implode("\n", $lines);
    }
}
