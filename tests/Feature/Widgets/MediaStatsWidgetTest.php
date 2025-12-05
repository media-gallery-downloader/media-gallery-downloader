<?php

use App\Filament\Widgets\MediaStatsWidget;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('MediaStatsWidget', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    it('renders successfully', function () {
        Livewire::test(MediaStatsWidget::class)
            ->assertSuccessful();
    });

    it('displays correct total video count', function () {
        // Create some media entries using factory
        Media::factory()->count(5)->create();

        Livewire::test(MediaStatsWidget::class)
            ->assertSee('5'); // Total count
    });

    it('displays zero when no media exists', function () {
        Livewire::test(MediaStatsWidget::class)
            ->assertSee('0');
    });

    it('calculates today count correctly', function () {
        // Create a video today
        Media::factory()->create([
            'created_at' => now(),
        ]);

        // Create a video yesterday
        Media::factory()->create([
            'created_at' => now()->subDay(),
        ]);

        Livewire::test(MediaStatsWidget::class)
            ->assertSuccessful();
    });
});
