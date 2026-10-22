<?php

namespace App\Services\Download;

use InvalidArgumentException;

/**
 * User-supplied yt-dlp arguments that must not be used: they can't be parsed,
 * yt-dlp rejects them, they include something that isn't allowed, or they
 * couldn't be checked at all. The message is written for the person who
 * entered the arguments.
 */
final class InvalidYtDlpArgumentsException extends InvalidArgumentException
{
    public static function syntax(int $line, string $problem): self
    {
        return new self(sprintf('Line %d: %s.', $line, $problem));
    }

    public static function inSource(string $source, self $error): self
    {
        return new self($source.': '.$error->getMessage(), 0, $error);
    }

    public static function rejectedByYtDlp(string $error): self
    {
        return new self('yt-dlp does not accept these arguments: '.$error);
    }

    /**
     * @param  non-empty-list<string>  $violations
     */
    public static function notAllowed(array $violations): self
    {
        return new self(ucfirst(implode('; ', $violations)).'.');
    }

    public static function uncheckable(string $reason): self
    {
        return new self('Could not check these arguments with yt-dlp: '.$reason);
    }
}
