<?php

use App\Events\DownloadCompleted;
use App\Events\DownloadFailed;
use App\Providers\EventServiceProvider;

describe('EventServiceProvider', function () {
    it('registers download completed event listener', function () {
        $provider = new EventServiceProvider(app());

        $listen = (new ReflectionClass($provider))->getProperty('listen');
        $listen->setAccessible(true);
        $listeners = $listen->getValue($provider);

        expect($listeners)->toHaveKey(DownloadCompleted::class);
        expect($listeners[DownloadCompleted::class])->toContain(\App\Listeners\HandleDownloadCompleted::class);
    });

    it('registers download failed event listener', function () {
        $provider = new EventServiceProvider(app());

        $listen = (new ReflectionClass($provider))->getProperty('listen');
        $listen->setAccessible(true);
        $listeners = $listen->getValue($provider);

        expect($listeners)->toHaveKey(DownloadFailed::class);
        expect($listeners[DownloadFailed::class])->toContain(\App\Listeners\HandleDownloadFailed::class);
    });

    it('extends base EventServiceProvider', function () {
        $provider = new EventServiceProvider(app());

        expect($provider)->toBeInstanceOf(\Illuminate\Foundation\Support\Providers\EventServiceProvider::class);
    });
});
