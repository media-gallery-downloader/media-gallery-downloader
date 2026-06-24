<?php

use App\Filament\Pages\Home;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('media info edit action', function () {
    it('updates the title, source and tags', function () {
        $media = Media::factory()->create(['name' => 'Old Title', 'source' => 'local']);

        Livewire::test(Home::class)
            ->callAction('editMediaInfo', data: [
                'name' => 'New Title',
                'source' => 'https://example.com/clip',
                'tags' => ['funny', 'cat'],
            ], arguments: ['id' => $media->id])
            ->assertHasNoActionErrors();

        $media->refresh();
        expect($media->name)->toBe('New Title')
            ->and($media->source)->toBe('https://example.com/clip')
            ->and($media->tags->map(fn ($t) => $t->name)->all())->toEqualCanonicalizing(['funny', 'cat']);
    });

    it('does not rename the file when the title changes', function () {
        $media = Media::factory()->create(['name' => 'A', 'file_name' => 'a-123.mp4', 'path' => 'media/a-123.mp4']);

        Livewire::test(Home::class)
            ->callAction('editMediaInfo', data: ['name' => 'B', 'tags' => []], arguments: ['id' => $media->id]);

        $media->refresh();
        expect($media->name)->toBe('B')
            ->and($media->file_name)->toBe('a-123.mp4')   // filename untouched
            ->and($media->path)->toBe('media/a-123.mp4');
    });

    it('syncs tags (replaces, not appends)', function () {
        $media = Media::factory()->create();
        $media->syncTags(['old']);

        Livewire::test(Home::class)
            ->callAction('editMediaInfo', data: ['name' => $media->name, 'tags' => ['new']], arguments: ['id' => $media->id]);

        expect($media->fresh()->tags->map(fn ($t) => $t->name)->all())->toBe(['new']);
    });
});

describe('gallery tag filter', function () {
    it('shows only media matching all selected tags', function () {
        $cat = Media::factory()->create(['name' => 'Cat Clip']);
        $cat->syncTags(['cat']);
        $dog = Media::factory()->create(['name' => 'Dog Clip']);
        $dog->syncTags(['dog']);

        Livewire::test(Home::class, ['tags' => ['cat']])
            ->assertSee('Cat Clip')
            ->assertDontSee('Dog Clip');
    });

    it('with no tag filter shows everything', function () {
        Media::factory()->create(['name' => 'Alpha Clip'])->syncTags(['a']);
        Media::factory()->create(['name' => 'Beta Clip'])->syncTags(['b']);

        Livewire::test(Home::class)
            ->assertSee('Alpha Clip')
            ->assertSee('Beta Clip');
    });
});
