<?php

use App\Filament\Pages\Home;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The gallery is responsive: a single-column 16:9 "feed" on phones (caption bar
 * below the thumbnail), 3 columns on tablets, 5 on desktop (the original look,
 * where the caption bar becomes the bottom overlay).
 */
describe('gallery responsive layout', function () {
    beforeEach(function () {
        Media::factory()->create(['name' => 'Responsive Clip']);
        $this->html = Livewire::test(Home::class)->html();
    });

    // The Filament panel never loads resources/css/app.css, so the layout ships
    // as scoped CSS inside the component — assert on that, not utility classes.

    it('uses a responsive grid: 1 column on phones, 3 on tablets, 5 on desktop', function () {
        expect($this->html)->toContain('mgd-gallery-grid')
            ->and($this->html)->toContain('grid-template-columns: repeat(3, 1fr)')
            ->and($this->html)->toContain('grid-template-columns: repeat(5, 1fr)');
    });

    it('renders feed cards 16:9 on phones and square from tablet up', function () {
        expect($this->html)->toContain('mgd-card-media')
            ->and($this->html)->toContain('aspect-ratio: 16 / 9')
            ->and($this->html)->toContain('aspect-ratio: 1 / 1');
    });

    it('shows a phone-only caption title under the thumbnail', function () {
        // The caption title node exists per card; scoped CSS hides it from sm up.
        expect($this->html)->toMatch('/mgd-caption-title[^>]*>\s*Responsive Clip/')
            ->and($this->html)->toContain('.mgd-caption-title { display: none; }');
    });

    it('no longer hardcodes the five-across card width', function () {
        expect($this->html)->not->toContain('calc(20% - 8px)');
    });
});
