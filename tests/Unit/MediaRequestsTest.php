<?php

use App\Http\Requests\StoreMediaRequest;
use App\Http\Requests\UpdateMediaRequest;

describe('StoreMediaRequest', function () {
    it('has authorize method that returns false by default', function () {
        $request = new StoreMediaRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('has empty rules by default', function () {
        $request = new StoreMediaRequest;
        expect($request->rules())->toBeArray();
        expect($request->rules())->toBeEmpty();
    });
});

describe('UpdateMediaRequest', function () {
    it('has authorize method that returns false by default', function () {
        $request = new UpdateMediaRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('has empty rules by default', function () {
        $request = new UpdateMediaRequest;
        expect($request->rules())->toBeArray();
        expect($request->rules())->toBeEmpty();
    });
});
