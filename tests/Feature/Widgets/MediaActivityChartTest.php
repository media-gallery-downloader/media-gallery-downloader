<?php

use App\Filament\Widgets\MediaActivityChart;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('MediaActivityChart', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    it('renders successfully', function () {
        Livewire::test(MediaActivityChart::class)
            ->assertSuccessful();
    });

    it('returns chart type as line', function () {
        $widget = new MediaActivityChart;
        $reflection = new ReflectionMethod($widget, 'getType');
        $reflection->setAccessible(true);

        expect($reflection->invoke($widget))->toBe('line');
    });

    it('returns 30 days of data', function () {
        $widget = new MediaActivityChart;
        $reflection = new ReflectionMethod($widget, 'getData');
        $reflection->setAccessible(true);

        $data = $reflection->invoke($widget);

        expect($data)->toHaveKey('datasets');
        expect($data)->toHaveKey('labels');
        expect(count($data['labels']))->toBe(30);
    });

    it('counts media for each day', function () {
        // Create media for today
        Media::factory()->count(3)->create([
            'created_at' => now(),
        ]);

        // Create media for yesterday
        Media::factory()->count(2)->create([
            'created_at' => now()->subDay(),
        ]);

        $widget = new MediaActivityChart;
        $reflection = new ReflectionMethod($widget, 'getData');
        $reflection->setAccessible(true);

        $data = $reflection->invoke($widget);
        $counts = $data['datasets'][0]['data'];

        // Last element (today) should be 3
        expect(end($counts))->toBe(3);
        // Second to last (yesterday) should be 2
        expect($counts[count($counts) - 2])->toBe(2);
    });

    it('shows zero for days with no media', function () {
        $widget = new MediaActivityChart;
        $reflection = new ReflectionMethod($widget, 'getData');
        $reflection->setAccessible(true);

        $data = $reflection->invoke($widget);
        $counts = $data['datasets'][0]['data'];

        // All counts should be zero when no media exists
        foreach ($counts as $count) {
            expect($count)->toBe(0);
        }
    });

    it('excludes media older than the 30-day window', function () {
        Media::factory()->create(['created_at' => now()]);
        Media::factory()->create(['created_at' => now()->subDays(40)]);

        $widget = new MediaActivityChart;
        $reflection = new ReflectionMethod($widget, 'getData');
        $reflection->setAccessible(true);

        $counts = $reflection->invoke($widget)['datasets'][0]['data'];

        expect(array_sum($counts))->toBe(1);
    });

    it('uses a single grouped query, not one count per day', function () {
        Media::factory()->count(3)->create();

        DB::enableQueryLog();
        $widget = new MediaActivityChart;
        $reflection = new ReflectionMethod($widget, 'getData');
        $reflection->setAccessible(true);
        $reflection->invoke($widget);
        $mediaQueries = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'media'));
        DB::disableQueryLog();

        expect($mediaQueries)->toHaveCount(1);
    });

    it('has correct dataset structure', function () {
        $widget = new MediaActivityChart;
        $reflection = new ReflectionMethod($widget, 'getData');
        $reflection->setAccessible(true);

        $data = $reflection->invoke($widget);

        expect($data['datasets'][0])->toHaveKey('label');
        expect($data['datasets'][0])->toHaveKey('data');
        expect($data['datasets'][0])->toHaveKey('borderColor');
        expect($data['datasets'][0])->toHaveKey('backgroundColor');
        expect($data['datasets'][0]['label'])->toBe('Videos Added');
    });
});
