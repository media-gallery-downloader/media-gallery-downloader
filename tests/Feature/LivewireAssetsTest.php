<?php

use App\Filament\Pages\Home;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Livewire serves a copy of its JavaScript published to public/vendor/livewire
// in preference to the one bundled with the installed package, and nothing
// republishes that copy when the package is updated. A copy committed in May
// 2025 was still being served in November 2026 (Livewire 3.8.11), in the dev
// stack and in production images alike. Without a published copy, pages load
// the script from Livewire's own route, which always matches the installed
// version, so don't publish it (`livewire:publish --assets`).
it('loads the Livewire script bundled with the installed package', function () {
    Storage::fake('public');

    $this->get(Home::getUrl())
        ->assertSuccessful()
        ->assertSee('src="/livewire/livewire.', false)
        ->assertDontSee('vendor/livewire/', false)
        ->assertDontSee('published Livewire assets are out of date', false);
});
