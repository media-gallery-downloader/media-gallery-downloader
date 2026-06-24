<?php

describe('media:encoders', function () {
    it('runs and reports each probe section (degrading gracefully with no GPU)', function () {
        $this->artisan('media:encoders')
            ->assertSuccessful()
            ->expectsOutputToContain('Render devices')
            ->expectsOutputToContain('ffmpeg hardware encoders')
            ->expectsOutputToContain('VAAPI capabilities')
            ->expectsOutputToContain('NVIDIA');
    });
});
