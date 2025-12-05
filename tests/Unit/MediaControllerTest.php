<?php

use App\Http\Controllers\MediaController;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('MediaController', function () {
    it('can be instantiated', function () {
        $controller = new MediaController;
        expect($controller)->toBeInstanceOf(MediaController::class);
    });

    describe('index', function () {
        it('returns null by default', function () {
            $controller = new MediaController;
            expect($controller->index())->toBeNull();
        });
    });

    describe('create', function () {
        it('returns null by default', function () {
            $controller = new MediaController;
            expect($controller->create())->toBeNull();
        });
    });

    describe('show', function () {
        it('returns null by default', function () {
            $media = new Media;
            $controller = new MediaController;
            expect($controller->show($media))->toBeNull();
        });
    });

    describe('edit', function () {
        it('returns null by default', function () {
            $media = new Media;
            $controller = new MediaController;
            expect($controller->edit($media))->toBeNull();
        });
    });

    describe('destroy', function () {
        it('returns null by default', function () {
            $media = new Media;
            $controller = new MediaController;
            expect($controller->destroy($media))->toBeNull();
        });
    });
});
