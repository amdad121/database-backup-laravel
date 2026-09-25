<?php

declare(strict_types=1);

use AmdadulHaq\DatabaseBackup\Events\RestoreCompleted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->dbFile = sys_get_temp_dir().'/db-restore-test-'.bin2hex(random_bytes(6)).'.sqlite';

    $pdo = new PDO('sqlite:'.$this->dbFile);
    $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');
    $pdo->exec("INSERT INTO items (name) VALUES ('alpha'), ('beta')");
    $pdo = null;

    config()->set('database.connections.sqlite_backup', [
        'driver' => 'sqlite',
        'database' => $this->dbFile,
        'prefix' => '',
    ]);

    config()->set('database-backup.connection', 'sqlite_backup');
    config()->set('database-backup.disk', 'backups_disk');
    config()->set('database-backup.path', 'backups');
    config()->set('database-backup.compress', false);
    config()->set('database-backup.temp_directory', sys_get_temp_dir());

    Storage::fake('backups_disk');
});

afterEach(function (): void {
    @unlink($this->dbFile);
});

function itemCount(string $file): int
{
    return (int) (new PDO('sqlite:'.$file))->query('SELECT COUNT(*) FROM items')->fetchColumn();
}

function wipeItems(string $file): void
{
    (new PDO('sqlite:'.$file))->exec('DELETE FROM items');
}

it('restores the latest backup for the connection', function (): void {
    $this->artisan('db:backup')->assertSuccessful();

    wipeItems($this->dbFile);
    expect(itemCount($this->dbFile))->toBe(0);

    $this->artisan('db:restore', ['--latest' => true, '--force' => true])->assertSuccessful();

    expect(itemCount($this->dbFile))->toBe(2);
});

it('restores from an explicit disk path', function (): void {
    $this->artisan('db:backup')->assertSuccessful();
    $file = Storage::disk('backups_disk')->files('backups/sqlite-backup')[0];

    wipeItems($this->dbFile);

    $this->artisan('db:restore', ['file' => $file, '--force' => true])->assertSuccessful();

    expect(itemCount($this->dbFile))->toBe(2);
});

it('gunzips a compressed backup before restoring', function (): void {
    config()->set('database-backup.compress', true);

    $this->artisan('db:backup')->assertSuccessful();
    wipeItems($this->dbFile);

    $this->artisan('db:restore', ['--latest' => true, '--force' => true])->assertSuccessful();

    expect(itemCount($this->dbFile))->toBe(2);
});

it('fails without a file argument or --latest', function (): void {
    $this->artisan('db:restore', ['--force' => true])->assertFailed();
});

it('fails when the backup file does not exist', function (): void {
    $this->artisan('db:restore', ['file' => 'backups/sqlite-backup/missing.sqlite', '--force' => true])->assertFailed();
});

it('aborts without --force when the confirmation is declined', function (): void {
    $this->artisan('db:backup')->assertSuccessful();

    $this->artisan('db:restore', ['--latest' => true])
        ->expectsConfirmation('This overwrites the [sqlite_backup] database. Continue?', 'no')
        ->assertFailed();
});

it('fails cleanly when the temp directory cannot be created', function (): void {
    $this->artisan('db:backup')->assertSuccessful();
    config()->set('database-backup.temp_directory', '/proc/nope/database-backup');

    $this->artisan('db:restore', ['--latest' => true, '--force' => true])
        ->expectsOutputToContain('Unable to create temp directory')
        ->assertFailed();
});

it('refuses to restore a sql dump into a sqlite connection', function (): void {
    Storage::disk('backups_disk')->put('backups/sqlite-backup/pgsql-app-2026-01-01_000000.sql', 'SELECT 1;');

    $this->artisan('db:restore', ['file' => 'backups/sqlite-backup/pgsql-app-2026-01-01_000000.sql', '--force' => true])
        ->expectsOutputToContain('is not a .sqlite backup')
        ->assertFailed();
});

it('dispatches restore events', function (): void {
    Event::fake([RestoreCompleted::class]);
    $this->artisan('db:backup')->assertSuccessful();

    $this->artisan('db:restore', ['--latest' => true, '--force' => true])->assertSuccessful();

    Event::assertDispatched(RestoreCompleted::class);
});

it('restores the newest backup by the timestamp in its name', function (): void {
    $this->artisan('db:backup')->assertSuccessful();
    $newest = Storage::disk('backups_disk')->files('backups/sqlite-backup')[0];

    // An older backup that was uploaded later (so its mtime is newer).
    Storage::disk('backups_disk')->put('backups/sqlite-backup/sqlite-backup-db-2000-01-01_000000.sqlite', 'broken');

    $this->artisan('db:restore', ['--latest' => true, '--force' => true])
        ->expectsOutputToContain($newest)
        ->assertSuccessful();
});
