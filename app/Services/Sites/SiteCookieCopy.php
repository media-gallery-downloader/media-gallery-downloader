<?php

namespace App\Services\Sites;

use App\Models\Site;

/**
 * One yt-dlp run's private copy of a site's cookies.
 *
 * yt-dlp rewrites its cookie file in place when it exits (refreshed session
 * cookies), whether or not the run succeeded. Pointing it at the site's own
 * file would let a run undo cookies pasted, cleared or deleted meanwhile, and
 * let concurrent runs read each other's half-written files. So each run gets
 * a copy, and afterwards its refreshed cookies replace the site's file only
 * if that file is still exactly the one that was copied.
 */
final class SiteCookieCopy
{
    private function __construct(
        private readonly Site $site,
        public readonly string $path,
        private readonly string $copiedHash,
    ) {}

    /**
     * Copy $site's cookies into $directory (the run's temp directory), or
     * null when the site has none. A dotfile, so it never shows up among the
     * run's downloaded files.
     */
    public static function create(Site $site, string $directory): ?self
    {
        return Site::withCookieLock(function () use ($site, $directory): ?self {
            if (! $site->hasCookies()) {
                return null;
            }

            $content = (string) file_get_contents($site->cookiesPath());
            $path = rtrim($directory, '/').'/.cookies.txt';
            file_put_contents($path, $content);
            chmod($path, 0600);

            return new self($site, $path, hash('sha256', $content));
        });
    }

    /**
     * After the run: keep the cookies yt-dlp refreshed unless the site's
     * cookies were pasted, cleared or deleted meanwhile; then remove the copy.
     */
    public function writeBack(): void
    {
        try {
            Site::withCookieLock(function (): void {
                $live = $this->site->cookiesPath();
                $refreshed = NetscapeCookies::parse(is_file($this->path) ? (string) file_get_contents($this->path) : '');

                if ($refreshed !== [] && is_file($live) && hash_file('sha256', $live) === $this->copiedHash) {
                    $this->site->replaceCookieFile(NetscapeCookies::normalize(implode("\n", array_column($refreshed, 'line'))));
                }
            });
        } finally {
            // Always, even if the write-back failed: temp-dir cleanup skips
            // dotfiles, and the copy holds the cookie values.
            if (is_file($this->path)) {
                unlink($this->path);
            }
        }
    }
}
