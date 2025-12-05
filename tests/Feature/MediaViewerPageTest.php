<?php

use App\Filament\Pages\MediaViewer;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('MediaViewer Page', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    it('displays media details when media exists', function () {
        // Create a media entry
        Storage::disk('public')->put('media/test.mp4', 'fake content');

        $media = Media::factory()->create([
            'path' => 'media/test.mp4',
        ]);

        $this->get(MediaViewer::getUrl(['record' => $media->id]))
            ->assertSuccessful();
    });

    it('can render media viewer with record parameter', function () {
        $media = Media::factory()->create();

        Livewire::test(MediaViewer::class, ['record' => $media->id])
            ->assertSuccessful();
    });
});
