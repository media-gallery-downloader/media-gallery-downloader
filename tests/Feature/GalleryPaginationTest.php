<?php

use App\Filament\Pages\Home;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Regression: the gallery uses a plain Laravel paginator inside a Livewire
 * component. Opening the edit modal (a Livewire round-trip) must NOT reset the
 * gallery to page 1, and the page links must be relative (?page=N) so they never
 * resolve to the POST-only /livewire/update endpoint (which 405s on a GET).
 */
describe('gallery pagination', function () {
    // 3 items, oldest -> newest. Default sort is "newest first", so with
    // per_page=2: page 1 = [Gamma, Beta], page 2 = [Alpha].
    function seedThree(): array
    {
        $alpha = Media::factory()->create(['name' => 'Alpha Clip', 'created_at' => now()->subDays(3)]);
        $beta = Media::factory()->create(['name' => 'Beta Clip', 'created_at' => now()->subDays(2)]);
        $gamma = Media::factory()->create(['name' => 'Gamma Clip', 'created_at' => now()->subDay()]);

        return compact('alpha', 'beta', 'gamma');
    }

    it('stays on the current page when the edit modal is opened', function () {
        ['alpha' => $alpha] = seedThree();

        Livewire::test(Home::class, ['per_page' => 2, 'page' => 2])
            ->assertSee('Alpha Clip')        // page 2 content
            ->assertDontSee('Gamma Clip')    // page 1 content
            ->mountAction('editMediaInfo', arguments: ['id' => $alpha->id])
            ->assertSet('page', 2)           // did NOT reset to page 1
            ->assertSee('Alpha Clip')        // still showing page 2
            ->assertDontSee('Gamma Clip');
    });

    it('stays on the page and shows the new title after an edit', function () {
        ['alpha' => $alpha] = seedThree();

        Livewire::test(Home::class, ['per_page' => 2, 'page' => 2])
            ->callAction('editMediaInfo', data: ['name' => 'Alpha Renamed', 'tags' => []], arguments: ['id' => $alpha->id])
            ->assertHasNoActionErrors()
            ->assertSet('page', 2)           // still on page 2 after editing
            ->assertSee('Alpha Renamed')     // gallery refreshed with the new title
            ->assertDontSee('Gamma Clip');   // did not snap back to page 1
    });

    it('renders relative page links (prevents the /livewire/update 405)', function () {
        seedThree();

        $html = Livewire::test(Home::class, ['per_page' => 2, 'page' => 1])->html();

        // A page-2 link whose href is relative (starts with "?") — so the browser
        // resolves it against the address bar, never an absolute (Livewire) endpoint.
        expect($html)->toMatch('/href="\?[^"]*page=2/');
    });
});
