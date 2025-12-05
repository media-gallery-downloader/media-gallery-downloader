<?php

use App\Models\Media;
use App\Models\User;
use App\Policies\MediaPolicy;

describe('MediaPolicy', function () {
    beforeEach(function () {
        $this->policy = new MediaPolicy;
        $this->media = new Media;
    });

    describe('viewAny', function () {
        it('allows anyone to view any media', function () {
            expect($this->policy->viewAny(null))->toBeTrue();
        });

        it('allows authenticated users to view any media', function () {
            $user = new User;
            expect($this->policy->viewAny($user))->toBeTrue();
        });
    });

    describe('view', function () {
        it('allows anyone to view media', function () {
            expect($this->policy->view(null, $this->media))->toBeTrue();
        });

        it('allows authenticated users to view media', function () {
            $user = new User;
            expect($this->policy->view($user, $this->media))->toBeTrue();
        });
    });

    describe('create', function () {
        it('allows anyone to create media', function () {
            expect($this->policy->create(null))->toBeTrue();
        });

        it('allows authenticated users to create media', function () {
            $user = new User;
            expect($this->policy->create($user))->toBeTrue();
        });
    });

    describe('update', function () {
        it('allows anyone to update media', function () {
            expect($this->policy->update(null, $this->media))->toBeTrue();
        });

        it('allows authenticated users to update media', function () {
            $user = new User;
            expect($this->policy->update($user, $this->media))->toBeTrue();
        });
    });

    describe('delete', function () {
        it('allows anyone to delete media', function () {
            expect($this->policy->delete(null, $this->media))->toBeTrue();
        });

        it('allows authenticated users to delete media', function () {
            $user = new User;
            expect($this->policy->delete($user, $this->media))->toBeTrue();
        });
    });

    describe('restore', function () {
        it('allows anyone to restore media', function () {
            expect($this->policy->restore(null, $this->media))->toBeTrue();
        });

        it('allows authenticated users to restore media', function () {
            $user = new User;
            expect($this->policy->restore($user, $this->media))->toBeTrue();
        });
    });

    describe('forceDelete', function () {
        it('allows anyone to force delete media', function () {
            expect($this->policy->forceDelete(null, $this->media))->toBeTrue();
        });

        it('allows authenticated users to force delete media', function () {
            $user = new User;
            expect($this->policy->forceDelete($user, $this->media))->toBeTrue();
        });
    });
});
