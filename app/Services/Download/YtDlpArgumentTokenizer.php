<?php

namespace App\Services\Download;

/**
 * Splits user-entered yt-dlp arguments (e.g. the Settings "yt-dlp Extra
 * Arguments" field) into an argument list, using POSIX shell quoting rules
 * without any of the shell's expansions:
 *
 *  - Arguments are separated by spaces or tabs; put one or several on a line.
 *  - Blank lines are skipped. A "#" at the start of an argument begins a
 *    comment that runs to the end of the line ("a#b" and "#" inside quotes
 *    are ordinary text).
 *  - 'Single quotes' keep everything between them literally.
 *  - "Double quotes" keep spaces; inside them a backslash escapes only
 *    ", \, $ and ` (any other backslash is kept).
 *  - Outside quotes a backslash escapes the next character ("a\ b" is one
 *    argument).
 *  - Quoted and unquoted text next to each other form one argument
 *    (--impersonate="" is "--impersonate="), and "" or '' is an empty one.
 *  - A quote must be closed on the line where it opens.
 */
final class YtDlpArgumentTokenizer
{
    /**
     * @return list<string>
     *
     * @throws InvalidYtDlpArgumentsException
     */
    public static function tokenize(string $text): array
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            throw new InvalidYtDlpArgumentsException('The arguments are not valid UTF-8 text.');
        }

        $args = [];

        foreach (preg_split('/\r\n|\n|\r/', $text) ?: [] as $index => $line) {
            array_push($args, ...self::tokenizeLine($line, $index + 1));
        }

        return $args;
    }

    /**
     * @return list<string>
     *
     * @throws InvalidYtDlpArgumentsException
     */
    private static function tokenizeLine(string $line, int $lineNumber): array
    {
        if (str_contains($line, "\0")) {
            throw InvalidYtDlpArgumentsException::syntax($lineNumber, 'contains a NUL character');
        }

        $args = [];
        $current = null; // the argument being built; null between arguments
        $length = strlen($line);

        // Byte-wise is safe for UTF-8: every special character is ASCII, and
        // ASCII bytes never occur inside a multibyte character.
        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];

            if ($char === ' ' || $char === "\t" || $char === "\v" || $char === "\f") {
                if ($current !== null) {
                    $args[] = $current;
                    $current = null;
                }

                continue;
            }

            if ($char === '#' && $current === null) {
                break; // comment
            }

            if ($char === "'") {
                $end = strpos($line, "'", $i + 1);

                if ($end === false) {
                    throw InvalidYtDlpArgumentsException::syntax($lineNumber, "missing closing single quote (')");
                }

                $current = ($current ?? '').substr($line, $i + 1, $end - $i - 1);
                $i = $end;

                continue;
            }

            if ($char === '"') {
                $current ??= '';

                for ($i++; $i < $length && $line[$i] !== '"'; $i++) {
                    if ($line[$i] === '\\' && $i + 1 < $length && str_contains('"\\$`', $line[$i + 1])) {
                        $i++;
                    }

                    $current .= $line[$i];
                }

                if ($i >= $length) {
                    throw InvalidYtDlpArgumentsException::syntax($lineNumber, 'missing closing double quote (")');
                }

                continue;
            }

            if ($char === '\\') {
                if ($i + 1 >= $length) {
                    throw InvalidYtDlpArgumentsException::syntax($lineNumber, 'nothing follows the backslash (\\) at the end of the line');
                }

                $current = ($current ?? '').$line[++$i];

                continue;
            }

            $current = ($current ?? '').$char;
        }

        if ($current !== null) {
            $args[] = $current;
        }

        return $args;
    }
}
