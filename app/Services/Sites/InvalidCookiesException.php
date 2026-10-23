<?php

namespace App\Services\Sites;

use RuntimeException;

/**
 * Cookies that can't be stored for a site. The message is shown to the user.
 */
final class InvalidCookiesException extends RuntimeException
{
    public static function noCookies(): self
    {
        return new self('No cookies found. Export them in Netscape cookies.txt format (e.g. with the "Get cookies.txt LOCALLY" browser extension) and paste or upload the result.');
    }

    /**
     * @param  list<string>  $cookieDomains
     * @param  list<string>  $siteDomains
     */
    public static function wrongSite(array $cookieDomains, array $siteDomains): self
    {
        return new self(sprintf(
            'None of these cookies are for %s. They are for: %s.',
            implode(', ', $siteDomains),
            implode(', ', $cookieDomains),
        ));
    }
}
