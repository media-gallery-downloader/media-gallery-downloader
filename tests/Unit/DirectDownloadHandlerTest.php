<?php

use App\Services\Download\DirectDownloadHandler;

function assertPublicUrl(string $url): void
{
    $handler = new DirectDownloadHandler;
    $method = new ReflectionMethod($handler, 'assertPublicUrl');
    $method->setAccessible(true);
    $method->invoke($handler, $url);
}

describe('DirectDownloadHandler SSRF guard', function () {
    it('rejects loopback, link-local and private addresses', function (string $url) {
        expect(fn () => assertPublicUrl($url))->toThrow(Exception::class);
    })->with([
        'loopback v4' => 'http://127.0.0.1/file.mp4',
        'cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
        'private 10/8' => 'http://10.0.0.5/file.mp4',
        'private 192.168' => 'http://192.168.1.10/file.mp4',
        'private 172.16' => 'http://172.16.5.5/file.mp4',
        'loopback v6' => 'http://[::1]/file.mp4',
        'no host' => 'file:///etc/passwd',
    ]);

    it('allows a public IP literal', function () {
        expect(fn () => assertPublicUrl('https://8.8.8.8/file.mp4'))->not->toThrow(Exception::class);
    });

    it('can be disabled via config', function () {
        config(['mgd.downloads.block_private_hosts' => false]);

        expect(fn () => assertPublicUrl('http://127.0.0.1/file.mp4'))->not->toThrow(Exception::class);
    });
});
