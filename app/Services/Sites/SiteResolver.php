<?php

namespace App\Services\Sites;

use App\Models\Site;

/**
 * Finds the Site whose settings apply to a URL.
 */
class SiteResolver
{
    /**
     * The site with a domain equal to the URL's host or a parent of it;
     * when several match, the one with the longest (most specific) domain.
     */
    public function forUrl(string $url): ?Site
    {
        $host = Site::normalizeDomain((string) parse_url($url, PHP_URL_HOST));
        if ($host === null) {
            return null;
        }

        $best = null;
        $bestLength = 0;

        foreach (Site::query()->orderBy('id')->get() as $site) {
            foreach ($site->domains as $domain) {
                $matches = $host === $domain || str_ends_with($host, '.'.$domain);
                if ($matches && strlen($domain) > $bestLength) {
                    $best = $site;
                    $bestLength = strlen($domain);
                }
            }
        }

        return $best;
    }

    /**
     * The site that lists exactly this domain (subdomains don't count), for
     * keeping each domain on one site.
     */
    public function domainOwner(string $domain, ?int $exceptSiteId = null): ?Site
    {
        $domain = Site::normalizeDomain($domain);
        if ($domain === null) {
            return null;
        }

        return Site::query()
            ->when($exceptSiteId !== null, fn ($query) => $query->whereKeyNot($exceptSiteId))
            ->orderBy('id')
            ->get()
            ->first(fn (Site $site): bool => in_array($domain, $site->domains, true));
    }
}
