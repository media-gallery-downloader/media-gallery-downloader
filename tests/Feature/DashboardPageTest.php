<?php

use App\Filament\Pages\Dashboard;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Dashboard (Stats) Page', function () {
    it('can render the dashboard page', function () {
        $this->get(Dashboard::getUrl())
            ->assertSuccessful();
    });

    it('shows stats navigation label', function () {
        $this->get(Dashboard::getUrl())
            ->assertSuccessful();

        // The navigation label should be "Stats"
        expect(Dashboard::getNavigationLabel())->toBe('Stats');
    });

    it('has correct route path', function () {
        // Dashboard should be at root
        expect(Dashboard::getRoutePath())->toBe('/');
    });

    it('renders without errors when media exists', function () {
        Media::factory()->count(5)->create();

        $this->get(Dashboard::getUrl())
            ->assertSuccessful();
    });
});
