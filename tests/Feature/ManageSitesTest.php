<?php

use App\Livewire\ManageSites;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function redditCookies(string $value = 'SECRET-VALUE-123'): string
{
    return ".reddit.com\tTRUE\t/\tTRUE\t9999999999\treddit_session\t{$value}\n";
}

beforeEach(function () {
    $this->cookiesDir = useTemporaryCookiesDirectory();
    fakeYtDlpArgumentHelper(['changed' => ['impersonate']]);
});

afterEach(function () {
    File::deleteDirectory($this->cookiesDir);
});

describe('Settings → Sites', function () {
    it('adds a site with pasted cookies and its own arguments', function () {
        Livewire::test(ManageSites::class)
            ->callTableAction('create', data: [
                'name' => 'Reddit',
                'domains' => ['reddit.com', 'redd.it'],
                'extra_args' => '--impersonate firefox',
                'combine_args' => false,
                'cookies_paste' => redditCookies(),
            ])
            ->assertHasNoTableActionErrors();

        $site = Site::sole();
        expect($site->name)->toBe('Reddit')
            ->and($site->domains)->toBe(['reddit.com', 'redd.it'])
            ->and($site->extra_args)->toBe('--impersonate firefox')
            ->and($site->combine_args)->toBeFalse()
            ->and(file_get_contents($site->cookiesPath()))->toContain('SECRET-VALUE-123');
    });

    it('accepts an uploaded cookies.txt', function () {
        Livewire::test(ManageSites::class)
            ->callTableAction('create', data: [
                'name' => 'Reddit',
                'domains' => ['reddit.com'],
                'cookies_file' => UploadedFile::fake()->createWithContent('cookies.txt', redditCookies()),
            ])
            ->assertHasNoTableActionErrors();

        expect(file_get_contents(Site::sole()->cookiesPath()))->toContain('SECRET-VALUE-123');
    });

    it('deletes the uploaded file once its cookies are stored', function () {
        Storage::fake('local'); // Livewire's temporary-upload disk (config/livewire.php)

        Livewire::test(ManageSites::class)
            ->callTableAction('create', data: [
                'name' => 'Reddit',
                'domains' => ['reddit.com'],
                'cookies_file' => UploadedFile::fake()->createWithContent('cookies.txt', redditCookies()),
            ])
            ->assertHasNoTableActionErrors();

        $leftovers = array_filter(
            Storage::disk('local')->allFiles('livewire-tmp'),
            fn (string $file): bool => str_contains((string) Storage::disk('local')->get($file), 'SECRET-VALUE-123'),
        );
        expect(Site::sole()->hasCookies())->toBeTrue()
            ->and($leftovers)->toBe([]);
    });

    it('refuses a domain another site already has', function () {
        makeSite('Reddit', ['reddit.com']);

        Livewire::test(ManageSites::class)
            ->callTableAction('create', data: ['name' => 'Reddit again', 'domains' => ['Reddit.com']])
            ->assertHasTableActionErrors(['domains']);

        expect(Site::count())->toBe(1);
    });

    it('refuses entries that are not domains', function () {
        Livewire::test(ManageSites::class)
            ->callTableAction('create', data: ['name' => 'Broken', 'domains' => ['not a domain']])
            ->assertHasTableActionErrors(['domains']);

        expect(Site::count())->toBe(0);
    });

    it('refuses arguments yt-dlp would not allow', function () {
        fakeYtDlpArgumentHelper([
            'changed' => ['exec_cmd'],
            'used' => [['token' => '--exec', 'option' => '--exec', 'dest' => 'exec_cmd']],
        ]);

        Livewire::test(ManageSites::class)
            ->callTableAction('create', data: ['name' => 'Example', 'domains' => ['example.com'], 'extra_args' => '--exec ls'])
            ->assertHasTableActionErrors(['extra_args']);

        expect(Site::count())->toBe(0);
    });

    it('refuses cookies for another site and saves nothing', function () {
        Livewire::test(ManageSites::class)
            ->callTableAction('create', data: [
                'name' => 'Reddit',
                'domains' => ['reddit.com'],
                'cookies_paste' => ".youtube.com\tTRUE\t/\tTRUE\t9999999999\tLOGIN_INFO\tx\n",
            ])
            ->assertHasTableActionErrors(['cookies_paste']);

        expect(Site::count())->toBe(0);
    });

    it('never shows stored cookie values, only a summary', function () {
        $site = makeSite('Reddit', ['reddit.com']);
        $site->storeCookies(redditCookies());

        $component = Livewire::test(ManageSites::class)->assertSee('1 cookie');
        expect($component->html())->not->toContain('SECRET-VALUE-123');

        $component->mountTableAction('edit', $site);
        expect($component->html())->not->toContain('SECRET-VALUE-123');
    });

    it('keeps the stored cookies when a site is edited without new ones', function () {
        $site = makeSite('Reddit', ['reddit.com']);
        $site->storeCookies(redditCookies());

        Livewire::test(ManageSites::class)
            ->callTableAction('edit', $site, data: ['name' => 'Reddit (alt)', 'domains' => ['reddit.com'], 'cookies_paste' => ''])
            ->assertHasNoTableActionErrors();

        expect($site->fresh()->name)->toBe('Reddit (alt)')
            ->and(file_get_contents($site->cookiesPath()))->toContain('SECRET-VALUE-123');
    });

    it('replaces the stored cookies when new ones are pasted', function () {
        $site = makeSite('Reddit', ['reddit.com']);
        $site->storeCookies(redditCookies());

        Livewire::test(ManageSites::class)
            ->callTableAction('edit', $site, data: ['name' => 'Reddit', 'domains' => ['reddit.com'], 'cookies_paste' => redditCookies('FRESH-VALUE-456')])
            ->assertHasNoTableActionErrors();

        expect(file_get_contents($site->cookiesPath()))->toContain('FRESH-VALUE-456')->not->toContain('SECRET-VALUE-123');
    });

    it('lets an edited site keep its own domains', function () {
        $site = makeSite('Reddit', ['reddit.com']);

        Livewire::test(ManageSites::class)
            ->callTableAction('edit', $site, data: ['name' => 'Reddit', 'domains' => ['reddit.com', 'redd.it']])
            ->assertHasNoTableActionErrors();

        expect($site->fresh()->domains)->toBe(['reddit.com', 'redd.it']);
    });

    it('clears a site\'s cookies', function () {
        $site = makeSite('Reddit', ['reddit.com']);
        $site->storeCookies(redditCookies());

        Livewire::test(ManageSites::class)->callTableAction('clearCookies', $site);

        expect($site->fresh()->hasCookies())->toBeFalse();
    });

    it('deletes a site together with its cookie file', function () {
        $site = makeSite('Reddit', ['reddit.com']);
        $site->storeCookies(redditCookies());
        $cookieFile = $site->cookiesPath();

        Livewire::test(ManageSites::class)->callTableAction('delete', $site);

        expect(Site::count())->toBe(0)
            ->and(file_exists($cookieFile))->toBeFalse();
    });

    it('deletes the selected sites and their cookies in one go', function () {
        $keep = makeSite('Reddit', ['reddit.com']);
        $keep->storeCookies(redditCookies());
        $shop = makeSite('Shop', ['shop.example']);
        $shop->storeCookies(".shop.example\tTRUE\t/\tTRUE\t9999999999\tsid\tshop-secret\n");
        $tracker = makeSite('Tracker', ['tracker.example']);

        Livewire::test(ManageSites::class)
            ->callTableBulkAction('delete', [$shop, $tracker]);

        expect(Site::pluck('name')->all())->toBe(['Reddit'])
            ->and(file_exists($shop->cookiesPath()))->toBeFalse()
            ->and($keep->fresh()->hasCookies())->toBeTrue();
    });
});
