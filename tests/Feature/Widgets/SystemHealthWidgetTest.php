<?php

use App\Filament\Widgets\SystemHealthWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('SystemHealthWidget', function () {
    it('renders successfully', function () {
        Livewire::test(SystemHealthWidget::class)
            ->assertSuccessful();
    });

    it('returns health data structure', function () {
        $widget = new SystemHealthWidget;
        $data = $widget->getHealthData();

        expect($data)->toBeArray();
        expect($data)->toHaveKey('app');
        expect($data)->toHaveKey('ytdlp');
        expect($data)->toHaveKey('ffmpeg');
        expect($data)->toHaveKey('deno');
        expect($data)->toHaveKey('php');
        expect($data)->toHaveKey('disk');
    });

    it('returns app info structure', function () {
        $widget = new SystemHealthWidget;
        $data = $widget->getHealthData();

        expect($data['app'])->toHaveKey('name');
        expect($data['app'])->toHaveKey('version');
        expect($data['app'])->toHaveKey('latest_version');
        expect($data['app'])->toHaveKey('is_up_to_date');
        expect($data['app'])->toHaveKey('repository');
        expect($data['app']['version'])->toBe(config('app.version'));
        expect($data['app']['repository'])->toBe(config('app.repository'));
    });

    it('returns ytdlp info structure', function () {
        $widget = new SystemHealthWidget;
        $data = $widget->getHealthData();

        expect($data['ytdlp'])->toHaveKey('installed');
        expect($data['ytdlp'])->toHaveKey('current_version');
    });

    it('returns ffmpeg info structure', function () {
        $widget = new SystemHealthWidget;
        $data = $widget->getHealthData();

        expect($data['ffmpeg'])->toHaveKey('installed');
        expect($data['ffmpeg'])->toHaveKey('version');
    });

    it('returns deno info structure', function () {
        $widget = new SystemHealthWidget;
        $data = $widget->getHealthData();

        expect($data['deno'])->toHaveKey('installed');
        expect($data['deno'])->toHaveKey('current_version');
        expect($data['deno'])->toHaveKey('latest_version');
        expect($data['deno'])->toHaveKey('is_up_to_date');
    });

    it('returns php info structure', function () {
        $widget = new SystemHealthWidget;
        $data = $widget->getHealthData();

        expect($data['php'])->toHaveKey('version');
        expect($data['php']['version'])->toBe(PHP_VERSION);
    });

    it('returns disk info structure', function () {
        $widget = new SystemHealthWidget;
        $data = $widget->getHealthData();

        expect($data['disk'])->toHaveKey('used');
        expect($data['disk'])->toHaveKey('free');
        expect($data['disk'])->toHaveKey('total');
        expect($data['disk'])->toHaveKey('percent_used');
    });
});
