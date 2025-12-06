<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class BackupDownloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create backup directory and a test backup file
        $backupDir = storage_path('app/data/backups');
        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        // Create a dummy backup file
        File::put($backupDir.'/test_backup.sql', 'SQLite format 3 test data');
    }

    protected function tearDown(): void
    {
        // Clean up test backup
        $backupFile = storage_path('app/data/backups/test_backup.sql');
        if (File::exists($backupFile)) {
            File::delete($backupFile);
        }

        parent::tearDown();
    }

    public function test_settings_page_renders_with_livewire(): void
    {
        Livewire::test(Settings::class)
            ->assertSuccessful();
    }
}
