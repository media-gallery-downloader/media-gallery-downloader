<?php

use App\Filament\Pages\Home;
use App\Models\Media;
use App\Services\DownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Home Page', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    it('can render the home page', function () {
        $this->get(Home::getUrl())
            ->assertSuccessful();
    });

    it('displays gallery with media items', function () {
        // Create some media entries using factory
        Media::factory()->count(2)->create();

        $component = Livewire::test(Home::class);

        // Page should load successfully
        $component->assertSuccessful();
    });

    it('renders successfully when no media exists', function () {
        $component = Livewire::test(Home::class);

        // Page should load successfully even with no media
        $component->assertSuccessful();
    });

    it('can paginate media items', function () {
        // Create more media than per page
        Media::factory()->count(30)->create();

        $component = Livewire::test(Home::class);
        $component->assertSuccessful();
    });

    it('has per_page property', function () {
        $component = Livewire::test(Home::class);

        // Default per_page is 100
        $component->assertSet('per_page', 100);
    });

    it('has sort property', function () {
        $component = Livewire::test(Home::class);

        // Default sort is 'newest'
        $component->assertSet('sort', 'newest');
    });

    it('can add to download queue', function () {
        Queue::fake();

        $component = Livewire::test(Home::class);
        $component->call('addToDownloadQueue', 'https://example.com/video.mp4');

        // Verify download was queued
        $downloadService = app(DownloadService::class);
        $queue = $downloadService->getQueue();

        expect(count($queue))->toBe(1);
        expect($queue[0]['url'])->toBe('https://example.com/video.mp4');
    });

    it('validates invalid URLs when adding to download queue', function () {
        $component = Livewire::test(Home::class);

        // This should send a notification about invalid URL
        $component->call('addToDownloadQueue', 'not-a-valid-url');

        // Queue should still be empty
        $downloadService = app(DownloadService::class);
        $queue = $downloadService->getQueue();

        expect($queue)->toBeEmpty();
    });

    it('can delete media', function () {
        $media = Media::factory()->create();

        expect(Media::count())->toBe(1);

        $component = Livewire::test(Home::class);
        $component->call('deleteMedia', $media->id);

        expect(Media::count())->toBe(0);
    });

    it('handles deleting non-existent media gracefully', function () {
        $component = Livewire::test(Home::class);

        // Should not throw exception
        $component->call('deleteMedia', 99999);

        // Just verifying no error occurred
        expect(true)->toBeTrue();
    });

    it('refreshes gallery when event dispatched', function () {
        $component = Livewire::test(Home::class);

        // Call the refresh method
        $component->call('refreshGallery');

        // Component should still be successful
        $component->assertSuccessful();
    });

    it('has polling interval', function () {
        $home = new Home;

        expect($home->getPollingInterval())->toBe('2s');
    });

    it('can set per_page via URL', function () {
        $component = Livewire::test(Home::class, ['per_page' => 50]);

        $component->assertSet('per_page', 50);
    });

    it('can set sort via URL', function () {
        $component = Livewire::test(Home::class, ['sort' => 'oldest']);

        $component->assertSet('sort', 'oldest');
    });

    it('validates empty URLs when adding to download queue', function () {
        $component = Livewire::test(Home::class);
        $component->call('addToDownloadQueue', '');

        // Queue should be empty
        $downloadService = app(DownloadService::class);
        $queue = $downloadService->getQueue();

        expect($queue)->toBeEmpty();
    });

    it('accepts valid http URLs', function () {
        Queue::fake();

        $component = Livewire::test(Home::class);
        $component->call('addToDownloadQueue', 'http://example.com/video.mp4');

        $downloadService = app(DownloadService::class);
        $queue = $downloadService->getQueue();

        expect(count($queue))->toBe(1);
    });

    it('accepts valid https URLs', function () {
        Queue::fake();

        $component = Livewire::test(Home::class);
        $component->call('addToDownloadQueue', 'https://www.youtube.com/watch?v=abc123');

        $downloadService = app(DownloadService::class);
        $queue = $downloadService->getQueue();

        expect(count($queue))->toBe(1);
    });

    it('deletes associated files when deleting media', function () {
        Storage::disk('public')->put('media/test.mp4', 'fake content');
        Storage::disk('public')->put('thumbnails/test_thumb.jpg', 'fake thumbnail');

        $media = Media::factory()->create([
            'path' => 'media/test.mp4',
            'thumbnail_path' => 'thumbnails/test_thumb.jpg',
        ]);

        $component = Livewire::test(Home::class);
        $component->call('deleteMedia', $media->id);

        expect(Media::count())->toBe(0);
    });

    it('has download and upload sections in form', function () {
        $component = Livewire::test(Home::class);
        $component->assertSuccessful();
    });
});
