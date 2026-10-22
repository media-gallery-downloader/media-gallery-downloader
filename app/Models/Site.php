<?php

namespace App\Models;

use App\Services\Sites\InvalidCookiesException;
use App\Services\Sites\NetscapeCookies;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Download settings for one site: the domains it covers (a URL matches when
 * its host is one of them or a subdomain of one), its yt-dlp extra
 * arguments, and its cookies. Cookies live in a file (cookiesPath()) because
 * yt-dlp reads them from, and writes refreshed ones back to, a file.
 *
 * @property int $id
 * @property string $name
 * @property list<string> $domains
 * @property string|null $extra_args
 * @property bool $combine_args
 * @property Carbon|null $cookies_updated_at
 */
class Site extends Model
{
    protected $fillable = [
        'name',
        'domains',
        'extra_args',
        'combine_args',
    ];

    protected $attributes = [
        'domains' => '[]',
        'combine_args' => true,
    ];

    protected $casts = [
        'combine_args' => 'boolean',
        'cookies_updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleted(function (Site $site): void {
            // No cookies directory means no cookie file to remove.
            if (is_dir(dirname($site->cookiesPath()))) {
                self::withCookieLock(fn () => File::delete($site->cookiesPath()));
            }
        });
    }

    /**
     * Reduce what someone typed or pasted (a domain, a URL, "*.example.com",
     * a cookie-style ".example.com") to a lowercase hostname, or null when
     * it isn't one.
     */
    public static function normalizeDomain(string $input): ?string
    {
        $value = strtolower(trim($input));

        if (str_contains($value, '://')) {
            $value = (string) parse_url($value, PHP_URL_HOST);
        } else {
            $value = (string) preg_replace(['~[/?#].*$~', '~^.*@~', '~:\d+$~'], '', $value);
        }

        // A leading "www." is dropped: a site's domains cover their
        // subdomains, so "youtube.com" also matches www., m. and music.
        $value = rtrim((string) preg_replace('~^(\*\.|\.|www\.)+~', '', $value), '.');

        $label = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';

        return preg_match("~^(?=.{1,253}$){$label}(?:\.{$label})*$~", $value) ? $value : null;
    }

    /**
     * @return Attribute<list<string>, iterable<mixed>>
     */
    protected function domains(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => array_values(json_decode($value ?? '[]', true) ?: []),
            set: function (iterable $value): string {
                $domains = [];
                foreach ($value as $domain) {
                    $normalized = is_string($domain) ? self::normalizeDomain($domain) : null;
                    if ($normalized !== null && ! in_array($normalized, $domains, true)) {
                        $domains[] = $normalized;
                    }
                }

                return (string) json_encode($domains);
            },
        );
    }

    public function cookiesPath(): string
    {
        return rtrim((string) config('mgd.cookies_path'), '/').'/site-'.$this->getKey().'.txt';
    }

    public function hasCookies(): bool
    {
        return is_file($this->cookiesPath());
    }

    /**
     * Replace this site's cookies with a pasted or uploaded cookies.txt.
     *
     * @throws InvalidCookiesException when there are no cookies, or none for this site's domains
     */
    public function storeCookies(string $content): void
    {
        $this->validateCookies($content);

        self::withCookieLock(fn () => $this->replaceCookieFile(NetscapeCookies::normalize($content)));

        $this->cookies_updated_at = now();
        $this->save();
    }

    /**
     * Run $callback holding the lock every change to a cookie file takes
     * (paste, clear, delete, and the write-back after a yt-dlp run; see
     * SiteCookieCopy), so none of them can undo another.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withCookieLock(callable $callback): mixed
    {
        $directory = rtrim((string) config('mgd.cookies_path'), '/');
        File::ensureDirectoryExists($directory, 0700);
        chmod($directory, 0700);

        $lock = fopen($directory.'/.lock', 'c');
        if ($lock === false) {
            throw new \RuntimeException('Could not open the cookie lock file');
        }

        try {
            flock($lock, LOCK_EX);

            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Atomically replace the cookie file, which only the app user can read:
     * the directory is 0700, so the new file is never readable by others,
     * even before its chmod to 0600. Call it holding withCookieLock().
     *
     * @internal
     */
    public function replaceCookieFile(string $content): void
    {
        $temporary = $this->cookiesPath().'.'.bin2hex(random_bytes(6)).'.tmp';

        file_put_contents($temporary, $content);
        chmod($temporary, 0600);
        rename($temporary, $this->cookiesPath());
    }

    /**
     * Check cookies before storing them (also for a site not saved yet,
     * e.g. while its form is being filled in).
     *
     * @throws InvalidCookiesException when there are no cookies, or none for this site's domains
     */
    public function validateCookies(string $content): void
    {
        $cookieDomains = array_values(array_unique(array_column(NetscapeCookies::parse($content), 'domain')));

        if ($cookieDomains === []) {
            throw InvalidCookiesException::noCookies();
        }

        $forThisSite = array_filter($cookieDomains, fn (string $cookieDomain): bool => $this->coversCookieDomain($cookieDomain));
        if ($forThisSite === []) {
            throw InvalidCookiesException::wrongSite($cookieDomains, $this->domains);
        }
    }

    public function clearCookies(): void
    {
        self::withCookieLock(fn () => File::delete($this->cookiesPath()));

        $this->cookies_updated_at = null;
        $this->save();
    }

    /**
     * How many cookies are stored and for which domains (never their values).
     *
     * @return array{count: int, domains: list<string>}|null
     */
    public function cookieSummary(): ?array
    {
        if (! $this->hasCookies()) {
            return null;
        }

        $cookies = NetscapeCookies::parse((string) file_get_contents($this->cookiesPath()));

        return [
            'count' => count($cookies),
            'domains' => array_values(array_unique(array_column($cookies, 'domain'))),
        ];
    }

    /**
     * Whether a browser would send a cookie set on $cookieDomain to this
     * site: the same domain, a subdomain of a site domain, or a parent of one.
     */
    private function coversCookieDomain(string $cookieDomain): bool
    {
        foreach ($this->domains as $domain) {
            if ($cookieDomain === $domain
                || str_ends_with($cookieDomain, '.'.$domain)
                || str_ends_with($domain, '.'.$cookieDomain)) {
                return true;
            }
        }

        return false;
    }
}
