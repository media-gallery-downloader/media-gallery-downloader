<?php

namespace App\Services\Sites;

use App\Models\FailedDownload;
use App\Models\Media;
use App\Models\QueueItem;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One-time upgrade step: before per-site cookies, one cookies.txt held the
 * cookies for every site. Split it into one Site for each domain something
 * was downloaded from, so the downloads that relied on it keep working.
 * Cookies for other domains (a full browser export also holds banks, shops
 * and ad networks) are dropped.
 */
class LegacyCookieImporter
{
    /**
     * Short-link domains that used to get these sites' cookies too (the old
     * file was sent for every URL), so links like youtu.be keep working.
     */
    private const SHORT_LINK_DOMAINS = [
        'youtube.com' => ['youtu.be', 'youtube-nocookie.com'],
        'reddit.com' => ['redd.it'],
    ];

    /**
     * Create a site for each root domain in $path that something was
     * downloaded from (a domain that is a subdomain of another domain in the
     * file is folded into that one), holding that domain's cookie lines;
     * cookies for other domains are dropped. The file is deleted once every
     * site was stored; on any failure, including download history that can't
     * be read, it is kept so nothing is lost. Never throws: it runs during
     * container start-up.
     *
     * @return list<Site>
     */
    public function import(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            Log::warning('Legacy cookies file could not be read; left in place', ['path' => $path]);

            return [];
        }

        $groups = $this->groupByRootDomain(NetscapeCookies::parse($content));

        if ($groups === []) {
            Log::warning('Legacy cookies file has no cookie lines; left in place', ['path' => $path]);

            return [];
        }

        try {
            $hosts = $this->downloadHosts();
        } catch (Throwable $e) {
            Log::warning('Download history could not be read; legacy cookies file left in place', ['path' => $path, 'error' => $e->getMessage()]);

            return [];
        }

        $dropped = array_values(array_filter(
            array_keys($groups),
            fn (string $root): bool => ! $this->downloadedFrom($root, $hosts),
        ));
        $groups = array_diff_key($groups, array_flip($dropped));
        if ($dropped !== []) {
            Log::info('Dropped legacy cookies for domains nothing was downloaded from', ['domains' => $dropped]);
        }

        $resolver = app(SiteResolver::class);
        $sites = [];
        $failed = false;

        foreach ($groups as $root => $lines) {
            // Already imported (e.g. on an earlier start that could not delete
            // the file): importing again would only duplicate it.
            if ($resolver->domainOwner($root) !== null) {
                continue;
            }

            $site = null;
            try {
                $site = Site::create(['name' => $root, 'domains' => [$root, ...$this->shortLinkDomains($root, array_keys($groups))]]);
                $site->storeCookies(implode("\n", $lines)."\n");
                $sites[] = $site;
            } catch (Throwable $e) {
                $failed = true;
                try {
                    $site?->delete();
                } catch (Throwable) {
                    // Best effort: the import must never stop start-up.
                }
                Log::error('Could not import legacy cookies for a site', ['domain' => $root, 'error' => $e->getMessage()]);
            }
        }

        if ($failed) {
            Log::warning('Legacy cookies file kept because some sites could not be imported', ['path' => $path]);
        } elseif (! File::delete($path)) {
            Log::warning('Legacy cookies file imported but could not be deleted; it is no longer used', ['path' => $path]);
        }

        if ($sites !== []) {
            Log::info('Imported legacy cookies file into sites', ['sites' => array_map(fn (Site $site) => $site->name, $sites)]);
        }

        return $sites;
    }

    /**
     * Hosts of the URLs downloads came from: the library's sources and the
     * failed and queued downloads.
     *
     * @return list<string>
     */
    private function downloadHosts(): array
    {
        return Media::query()->whereNotNull('source')->pluck('source')
            ->merge(FailedDownload::query()->pluck('url'))
            ->merge(QueueItem::query()->whereNotNull('url')->pluck('url'))
            ->map(fn (mixed $url): ?string => is_string($url) ? Site::normalizeDomain($url) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Whether something was downloaded from $root: one of $hosts is $root,
     * one of its subdomains or one of its short-link domains (a youtu.be
     * link used the YouTube cookies).
     *
     * @param  list<string>  $hosts
     */
    private function downloadedFrom(string $root, array $hosts): bool
    {
        $domains = [Site::normalizeDomain($root) ?? $root, ...(self::SHORT_LINK_DOMAINS[$root] ?? [])];

        foreach ($hosts as $host) {
            foreach ($domains as $domain) {
                if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * $root's short-link domains, except those with cookies of their own in
     * the file (they get their own site) or already on a site.
     *
     * @param  list<string>  $roots
     * @return list<string>
     */
    private function shortLinkDomains(string $root, array $roots): array
    {
        $resolver = app(SiteResolver::class);

        return array_values(array_filter(
            self::SHORT_LINK_DOMAINS[$root] ?? [],
            fn (string $domain): bool => ! in_array($domain, $roots, true) && $resolver->domainOwner($domain) === null,
        ));
    }

    /**
     * @param  list<array{domain: string, line: string}>  $cookies
     * @return array<string, list<string>> root domain => cookie lines, in file order
     */
    private function groupByRootDomain(array $cookies): array
    {
        $domains = array_values(array_unique(array_column($cookies, 'domain')));
        // Parents before their subdomains, so each subdomain finds its root.
        usort($domains, fn (string $a, string $b): int => substr_count($a, '.') <=> substr_count($b, '.') ?: strcmp($a, $b));

        $rootOf = [];
        foreach ($domains as $domain) {
            $rootOf[$domain] = $domain;
            foreach (array_unique($rootOf) as $root) {
                if (str_ends_with($domain, '.'.$root)) {
                    $rootOf[$domain] = $root;
                    break;
                }
            }
        }

        $groups = [];
        foreach ($cookies as $cookie) {
            $groups[$rootOf[$cookie['domain']]][] = $cookie['line'];
        }

        return $groups;
    }
}
