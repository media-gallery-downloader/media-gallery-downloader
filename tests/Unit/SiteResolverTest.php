<?php

use App\Services\Sites\SiteResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('SiteResolver::forUrl', function () {
    it('matches a site by its exact domain', function () {
        $reddit = makeSite('Reddit', ['reddit.com']);

        expect(app(SiteResolver::class)->forUrl('https://reddit.com/r/videos/comments/abc/x/')?->id)->toBe($reddit->id);
    });

    it('matches subdomains of a site domain', function () {
        $reddit = makeSite('Reddit', ['reddit.com']);

        expect(app(SiteResolver::class)->forUrl('https://old.reddit.com/r/videos/')?->id)->toBe($reddit->id);
    });

    it('normalizes the URL host before matching', function () {
        $reddit = makeSite('Reddit', ['reddit.com']);

        expect(app(SiteResolver::class)->forUrl('https://WWW.Reddit.com.:443/r/videos/')?->id)->toBe($reddit->id);
    });

    it('does not match a host that only ends with the same letters', function () {
        makeSite('Reddit', ['reddit.com']);

        expect(app(SiteResolver::class)->forUrl('https://notreddit.com/r/videos/'))->toBeNull();
    });

    it('returns null when no site matches', function () {
        makeSite('Reddit', ['reddit.com']);

        expect(app(SiteResolver::class)->forUrl('https://www.youtube.com/watch?v=abc'))->toBeNull();
    });

    it('returns null for a URL without a host', function () {
        makeSite('Reddit', ['reddit.com']);

        expect(app(SiteResolver::class)->forUrl('not a url'))->toBeNull();
    });

    it('prefers the site with the most specific matching domain', function () {
        $google = makeSite('Google', ['google.com']);
        $accounts = makeSite('Google accounts', ['accounts.google.com']);

        $resolver = app(SiteResolver::class);

        expect($resolver->forUrl('https://accounts.google.com/signin')?->id)->toBe($accounts->id)
            ->and($resolver->forUrl('https://mail.google.com/')?->id)->toBe($google->id);
    });

    it('matches any of a site\'s domains', function () {
        $reddit = makeSite('Reddit', ['reddit.com', 'redd.it']);

        expect(app(SiteResolver::class)->forUrl('https://v.redd.it/abc123')?->id)->toBe($reddit->id);
    });
});

describe('SiteResolver::domainOwner', function () {
    it('finds the site that lists a domain, ignoring case', function () {
        $reddit = makeSite('Reddit', ['reddit.com']);

        expect(app(SiteResolver::class)->domainOwner('REDDIT.com')?->id)->toBe($reddit->id);
    });

    it('ignores the site being edited', function () {
        $reddit = makeSite('Reddit', ['reddit.com']);

        expect(app(SiteResolver::class)->domainOwner('reddit.com', $reddit->id))->toBeNull();
    });

    it('does not treat a subdomain as owned by its parent domain', function () {
        makeSite('Reddit', ['reddit.com']);

        expect(app(SiteResolver::class)->domainOwner('old.reddit.com'))->toBeNull();
    });
});
