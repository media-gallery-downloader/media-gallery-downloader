<?php

use App\Filament\Pages\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create backup directory and a test backup file
    $backupDir = storage_path('app/data/backups');
    if (! File::exists($backupDir)) {
        File::makeDirectory($backupDir, 0755, true);
    }

    // Create a dummy backup file with proper SQLite header
    File::put($backupDir.'/test_backup.sqlite', 'SQLite format 3 test data');
});

afterEach(function () {
    // Clean up test backup
    $backupFile = storage_path('app/data/backups/test_backup.sqlite');
    if (File::exists($backupFile)) {
        File::delete($backupFile);
    }
});

describe('Backup Download', function () {
    it('can render the settings page', function () {
        Livewire::test(Settings::class)
            ->assertSuccessful();
    });
});
