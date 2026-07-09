<?php

use App\Filament\Pages\Home;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The gallery defaults to 20 items per page, but an explicit choice (the
 * per-page <select> navigates with ?per_page=N) is remembered in a long-lived
 * cookie and restored on later visits — there are no accounts to store it on.
 */
describe('gallery per-page persistence', function () {
    it('defaults to 20 per page', function () {
        Livewire::test(Home::class)->assertSet('per_page', 20);
    });

    it('remembers an explicit per-page choice in a cookie', function () {
        $this->get(Home::getUrl().'?per_page=100')
            ->assertSuccessful()
            ->assertCookie('gallery_per_page', '100');
    });

    // Note: withCookie() (encrypted, the default) mirrors production — the app
    // queues this cookie encrypted, and EncryptCookies strips raw values.

    it('restores the remembered per-page on a bare visit', function () {
        $response = $this->withCookie('gallery_per_page', '100')
            ->get(Home::getUrl());

        $response->assertSuccessful();
        // The per-page <select> renders the remembered option as selected.
        expect($response->getContent())->toContain('value="100" selected');
    });

    it('the URL wins over the cookie and re-persists', function () {
        $response = $this->withCookie('gallery_per_page', '100')
            ->get(Home::getUrl().'?per_page=50');

        $response->assertSuccessful()->assertCookie('gallery_per_page', '50');
        expect($response->getContent())->toContain('value="50" selected');
    });

    it('ignores an out-of-range cookie value', function () {
        $response = $this->withCookie('gallery_per_page', '9999')
            ->get(Home::getUrl());

        $response->assertSuccessful();
        expect($response->getContent())->toContain('value="20" selected');
    });
});
