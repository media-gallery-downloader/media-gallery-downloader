<?php

namespace App\Services\Download;

/**
 * One source of user-supplied yt-dlp arguments (e.g. the Settings field).
 * Layers are applied in order (see YtDlpArguments::layers()); each one either
 * combines with the arguments of the layers before it or overrides them.
 */
final class YtDlpArgumentLayer
{
    private function __construct(
        /** Where the text comes from, for error messages. */
        public readonly string $source,
        public readonly string $text,
        public readonly bool $overrides,
    ) {}

    /** Arguments added after those of the previous layers (for yt-dlp, later options win). */
    public static function combine(string $source, string $text): self
    {
        return new self($source, $text, false);
    }

    /** Arguments that replace those of the previous layers. */
    public static function override(string $source, string $text): self
    {
        return new self($source, $text, true);
    }
}
