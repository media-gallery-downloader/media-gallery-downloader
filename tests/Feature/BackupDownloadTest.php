<?php

use App\Filament\Pages\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // A throwaway backups directory holding one dummy backup: in the dev
    // container storage/ is the live app storage, real backups included.
    $this->backupDir = useTemporaryBackupsDirectory();
    File::makeDirectory($this->backupDir, 0755, true);

    // Create a dummy backup file with proper SQLite header
    File::put($this->backupDir.'/test_backup.sqlite', 'SQLite format 3 test data');
});

afterEach(function () {
    File::deleteDirectory($this->backupDir);
});

describe('Backup Download', function () {
    it('can render the settings page', function () {
        Livewire::test(Settings::class)
            ->assertSuccessful();
    });

    it('lists the backups with a download link', function () {
        Livewire::test(Settings::class)
            ->assertSee('test_backup.sqlite')
            ->assertSee(route('backup.download', ['filename' => 'test_backup.sqlite']));
    });

    it('downloads a backup from the settings page', function () {
        Livewire::test(Settings::class)
            ->call('downloadBackup', 'test_backup.sqlite')
            ->assertFileDownloaded('test_backup.sqlite', 'SQLite format 3 test data');
    });

    it('serves a backup from the download route', function () {
        $response = $this->get(route('backup.download', ['filename' => 'test_backup.sqlite']));

        $response->assertOk()->assertDownload('test_backup.sqlite');
        expect($response->streamedContent())->toBe('SQLite format 3 test data');
    });
});
