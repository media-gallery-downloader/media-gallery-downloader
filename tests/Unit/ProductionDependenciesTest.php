<?php

// The container's start script links public/storage with
// `storage:link --relative`, which needs symfony/filesystem. Production images
// install without dev packages, so it must be a production requirement even
// though no application code uses it: when only a dev tool pulled it in, the
// link was never created and every media and thumbnail URL returned 404.
it('installs symfony/filesystem in production for the relative storage link', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
    $lock = json_decode((string) file_get_contents(base_path('composer.lock')), true);

    expect($composer['require'])->toHaveKey('symfony/filesystem')
        ->and(array_column($lock['packages'], 'name'))->toContain('symfony/filesystem');
});
