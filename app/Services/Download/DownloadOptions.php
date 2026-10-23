<?php

namespace App\Services\Download;

/**
 * What one download asked for beyond the URL: the one-off yt-dlp arguments
 * entered on the Home page, and whether they combine with the Settings
 * arguments (global + matching site) or replace them. Stored with the queue
 * item and the failed-download row so every retry asks for the same.
 */
final class DownloadOptions
{
    public function __construct(
        public readonly string $extraArgs = '',
        public readonly bool $combineArgs = true,
    ) {}

    public static function none(): self
    {
        return new self;
    }

    /**
     * From stored data (queue item meta, failed-download columns). Missing
     * values, as in rows saved before one-off arguments existed, mean none.
     *
     * @param  array<string, mixed>|null  $data
     */
    public static function fromArray(?array $data): self
    {
        $extraArgs = $data['extra_args'] ?? '';

        return new self(is_string($extraArgs) ? $extraArgs : '', (bool) ($data['combine_args'] ?? true));
    }

    /**
     * @return array{extra_args: string, combine_args: bool}
     */
    public function toArray(): array
    {
        return ['extra_args' => $this->extraArgs, 'combine_args' => $this->combineArgs];
    }
}
