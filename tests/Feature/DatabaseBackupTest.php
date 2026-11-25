<?php

use App\Models\Media;
use App\Services\Maintenance\BackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/*
 * createDatabaseBackup() against a throwaway SQLite database in WAL mode: the
 * journal mode the app runs with, which phpunit.xml switches off for the
 * shared test database. The backups go to a throwaway directory as well.
 */

/**
 * @return list<string>
 */
function mediaNamesInSqliteFile(string $path): array
{
    return (new PDO('sqlite:'.$path))
        ->query('SELECT name FROM media ORDER BY name')
        ->fetchAll(PDO::FETCH_COLUMN);
}

beforeEach(function () {
    $this->backupDir = useTemporaryBackupsDirectory();

    $this->databaseDir = storage_path('framework/testing/database_'.str_replace('.', '', uniqid('', true)));
    File::makeDirectory($this->databaseDir, 0755, true);
    $this->database = $this->databaseDir.'/database.sqlite';
    touch($this->database);

    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => $this->database,
        'database.connections.sqlite.journal_mode' => 'WAL',
    ]);
    DB::purge('sqlite');

    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
});

afterEach(function () {
    DB::purge('sqlite');
    File::deleteDirectory($this->databaseDir);
    File::deleteDirectory($this->backupDir);
});

it('backs up changes that are still in the write-ahead log', function () {
    Media::factory()->create(['name' => 'deleted since the checkpoint']);
    Media::factory()->create(['name' => 'kept']);

    // Move everything so far into the main database file. What follows stays
    // in database.sqlite-wal, because this connection stays open and nothing
    // checkpoints again.
    DB::select('PRAGMA wal_checkpoint(TRUNCATE)');

    Media::where('name', 'deleted since the checkpoint')->delete();
    Media::factory()->create(['name' => 'added since the checkpoint']);

    // The scenario really is "WAL mode with un-checkpointed writes": the main
    // file on its own still shows the state as of the checkpoint.
    expect(DB::selectOne('PRAGMA journal_mode')->journal_mode)->toBe('wal');
    File::copy($this->database, $this->databaseDir.'/main-file-only.sqlite');
    expect(mediaNamesInSqliteFile($this->databaseDir.'/main-file-only.sqlite'))
        ->toBe(['deleted since the checkpoint', 'kept']);

    $backup = app(BackupService::class)->createDatabaseBackup();

    expect($backup)->toStartWith($this->backupDir.'/backup_')
        ->and(mediaNamesInSqliteFile($backup))->toBe(['added since the checkpoint', 'kept']);
});

it('replaces a backup taken in the same second', function () {
    // The file name only goes down to the second. A date in the past, so that
    // pruning (which compares file times with "now") leaves the backup alone.
    $this->travelTo('2026-01-02 03:04:05');

    $first = app(BackupService::class)->createDatabaseBackup();
    Media::factory()->create(['name' => 'added after the first backup']);
    $second = app(BackupService::class)->createDatabaseBackup();

    expect($first)->toBe($this->backupDir.'/backup_2026-01-02_030405.sqlite')
        ->and($second)->toBe($first)
        ->and(mediaNamesInSqliteFile($second))->toBe(['added after the first backup'])
        ->and(File::files($this->backupDir))->toHaveCount(1);
});

it('fails when the database file is missing', function () {
    // The app's own connection points at the missing file too, so reporting
    // the failure logs a second error. Keep both out of the real log.
    Log::spy();

    $missing = $this->databaseDir.'/missing.sqlite';
    config(['database.connections.sqlite.database' => $missing]);
    DB::purge('sqlite');

    $backup = app(BackupService::class)->createDatabaseBackup();

    // Opening a missing SQLite file creates it, empty: that must neither
    // happen nor pass for a backup.
    expect($backup)->toBeNull()
        ->and(File::exists($missing))->toBeFalse()
        ->and(File::files($this->backupDir))->toBe([]);
});

it('leaves nothing behind when the backup cannot be put in place', function () {
    Log::spy();

    // Something that cannot be replaced sits where the backup belongs.
    $this->travelTo('2026-01-02 03:04:05');
    File::makeDirectory($this->backupDir.'/backup_2026-01-02_030405.sqlite', 0755, true);

    $backup = app(BackupService::class)->createDatabaseBackup();

    expect($backup)->toBeNull()
        ->and(File::files($this->backupDir))->toBe([]);

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message) => str_starts_with($message, 'Database backup failed'))
        ->once();
});
