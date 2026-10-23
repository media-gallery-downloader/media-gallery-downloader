<?php

namespace App\Services\Sites;

/**
 * The Netscape cookies.txt format that yt-dlp reads (and browser extensions
 * such as "Get cookies.txt LOCALLY" export): one cookie per line, seven
 * TAB-separated fields starting with the domain. Lines starting with "#" are
 * comments, except the "#HttpOnly_" prefix that marks HttpOnly cookies.
 */
final class NetscapeCookies
{
    public const HEADER = '# Netscape HTTP Cookie File';

    private const HTTP_ONLY_PREFIX = '#HttpOnly_';

    /**
     * Heuristic check that content is a cookies.txt, whichever site it is for.
     */
    public static function looksLikeNetscape(string $content): bool
    {
        if (str_contains($content, self::HEADER) || str_contains($content, '# HTTP Cookie File')) {
            return true;
        }

        return self::parse($content) !== [];
    }

    /**
     * The cookie lines yt-dlp will load (without line endings) and each
     * cookie's domain, lowercased and without the leading dot.
     *
     * Lines yt-dlp would reject are skipped rather than stored, because
     * yt-dlp prints a rejected line, value included, into its error output:
     * it needs exactly seven fields and an empty or numeric expiry. The
     * include-subdomains flag is set from the domain's leading dot, which
     * yt-dlp requires to agree (or it refuses the whole file).
     *
     * @return list<array{domain: string, line: string}>
     */
    public static function parse(string $content): array
    {
        $cookies = [];
        $content = str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            $isHttpOnly = str_starts_with($line, self::HTTP_ONLY_PREFIX);
            if (str_starts_with($line, '#') && ! $isHttpOnly) {
                continue;
            }

            $fields = explode("\t", $line);
            if (count($fields) !== 7 || ! preg_match('/^(?:[0-9]+(?:\.[0-9]+)?)?$/', $fields[4])) {
                continue;
            }

            $domain = $isHttpOnly ? substr($fields[0], strlen(self::HTTP_ONLY_PREFIX)) : $fields[0];
            $fields[1] = str_starts_with($domain, '.') ? 'TRUE' : 'FALSE';
            $cookies[] = ['domain' => ltrim(strtolower(trim($domain)), '.'), 'line' => implode("\t", $fields)];
        }

        return $cookies;
    }

    /**
     * The cookie lines under the header yt-dlp requires, with LF endings.
     */
    public static function normalize(string $content): string
    {
        $lines = array_column(self::parse($content), 'line');

        return implode("\n", [self::HEADER, ...$lines])."\n";
    }
}
