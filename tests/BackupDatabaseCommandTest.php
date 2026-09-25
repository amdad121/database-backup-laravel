<?php

declare(strict_types=1);

use AmdadulHaq\DatabaseBackup\Events\BackupCompleted;
use AmdadulHaq\DatabaseBackup\Events\BackupPruneFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->dbFile = sys_get_temp_dir().'/db-backup-test-'.bin2hex(random_bytes(6)).'.sqlite';

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

it('dumps the configured connection and uploads it to the disk', function (): void {
    Event::fake([BackupCompleted::class]);

    $this->artisan('db:backup')->assertSuccessful();

    $files = Storage::disk('backups_disk')->files('backups/sqlite-backup');

    expect($files)->toHaveCount(1)
        ->and($files[0])->toStartWith('backups/sqlite-backup/sqlite-backup-');

    $restored = sys_get_temp_dir().'/db-backup-restored-'.bin2hex(random_bytes(6)).'.sqlite';
    file_put_contents($restored, Storage::disk('backups_disk')->get($files[0]));
    $count = (new PDO('sqlite:'.$restored))->query('SELECT COUNT(*) FROM items')->fetchColumn();
    @unlink($restored);

    expect((int) $count)->toBe(2);

    Event::assertDispatched(BackupCompleted::class);
});

it('gzips the dump when compression is on', function (): void {
    config()->set('database-backup.compress', true);

    $this->artisan('db:backup')->assertSuccessful();

    expect(Storage::disk('backups_disk')->files('backups/sqlite-backup')[0])->toEndWith('.sqlite.gz');
});

it('backs up the connection passed via --connection', function (): void {
    config()->set('database-backup.connection');

    $this->artisan('db:backup', ['--connection' => 'sqlite_backup'])->assertSuccessful();

    expect(Storage::disk('backups_disk')->files('backups/sqlite-backup'))->toHaveCount(1);
});

it('stores backups under an app-name folder so a bucket can be shared', function (): void {
    config()->set('database-backup.path', 'acme-site');

    $this->artisan('db:backup')->assertSuccessful();

    expect(Storage::disk('backups_disk')->files('acme-site/sqlite-backup'))->toHaveCount(1);
});

it('prunes older backups beyond keep_last', function (): void {
    config()->set('database-backup.retention.keep_last', 2);

    Storage::disk('backups_disk')->put('backups/sqlite-backup/sqlite-backup-db-2020-01-01_000000.sqlite', 'old');
    Storage::disk('backups_disk')->put('backups/sqlite-backup/sqlite-backup-db-2020-01-02_000000.sqlite', 'old');

    $this->artisan('db:backup')->assertSuccessful();

    expect(Storage::disk('backups_disk')->files('backups/sqlite-backup'))->toHaveCount(2)
        ->and(Storage::disk('backups_disk')->exists('backups/sqlite-backup/sqlite-backup-db-2020-01-02_000000.sqlite'))->toBeTrue();
});

it('never prunes backups of a connection whose name shares a prefix', function (): void {
    config()->set('database-backup.retention.keep_last', 1);
    config()->set('database.connections.sqlite_backup_replica', ['driver' => 'sqlite', 'database' => 'other.sqlite']);

    // Legacy flat layout and the new per-connection folder.
    Storage::disk('backups_disk')->put('backups/sqlite-backup-replica-other-2020-01-01_000000.sqlite', 'keep');
    Storage::disk('backups_disk')->put('backups/sqlite-backup-replica/sqlite-backup-replica-other-2020-01-01_000000.sqlite', 'keep');

    $this->artisan('db:backup')->assertSuccessful();
    $this->artisan('db:backup')->assertSuccessful();

    expect(Storage::disk('backups_disk')->exists('backups/sqlite-backup-replica-other-2020-01-01_000000.sqlite'))->toBeTrue()
        ->and(Storage::disk('backups_disk')->exists('backups/sqlite-backup-replica/sqlite-backup-replica-other-2020-01-01_000000.sqlite'))->toBeTrue()
        ->and(Storage::disk('backups_disk')->files('backups/sqlite-backup'))->toHaveCount(1);
});

it('prunes legacy backups stored in the root folder', function (): void {
    config()->set('database-backup.retention.keep_last', 1);

    $legacy = 'backups/sqlite-backup-'.basename($this->dbFile, '.sqlite').'-2020-01-01_000000.sqlite';
    Storage::disk('backups_disk')->put($legacy, 'old');

    $this->artisan('db:backup')->assertSuccessful();

    expect(Storage::disk('backups_disk')->exists($legacy))->toBeFalse();
});

it('does not overwrite a backup taken in the same second', function (): void {
    $this->travelTo(now()->startOfSecond());

    $this->artisan('db:backup')->assertSuccessful();
    $this->artisan('db:backup')->assertSuccessful();

    expect(Storage::disk('backups_disk')->files('backups/sqlite-backup'))->toHaveCount(2);
});

it('captures committed data still in the WAL file', function (): void {
    $pdo = new PDO('sqlite:'.$this->dbFile);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA wal_autocheckpoint=0');
    $pdo->exec("INSERT INTO items (name) VALUES ('gamma')");

    $this->artisan('db:backup')->assertSuccessful();
    $pdo = null;

    $restored = sys_get_temp_dir().'/db-backup-wal-'.bin2hex(random_bytes(6)).'.sqlite';
    file_put_contents($restored, Storage::disk('backups_disk')->get(Storage::disk('backups_disk')->files('backups/sqlite-backup')[0]));
    $count = (new PDO('sqlite:'.$restored))->query('SELECT COUNT(*) FROM items')->fetchColumn();
    @unlink($restored);
    @unlink($this->dbFile.'-wal');
    @unlink($this->dbFile.'-shm');

    expect((int) $count)->toBe(3);
});

it('keeps the backup and reports success when pruning fails', function (): void {
    Event::fake([BackupPruneFailed::class]);
    config()->set('database-backup.retention.keep_last', 1);

    $disk = Mockery::mock(Storage::disk('backups_disk'))->makePartial();
    $disk->shouldReceive('delete')->andThrow(new RuntimeException('permission denied'));
    Storage::set('backups_disk', $disk);

    Storage::disk('backups_disk')->put('backups/sqlite-backup/sqlite-backup-db-2020-01-01_000000.sqlite', 'old');

    $this->artisan('db:backup')->assertSuccessful();

    Event::assertDispatched(BackupPruneFailed::class);
});

it('writes dumps that only the owner can read', function (): void {
    $dir = sys_get_temp_dir().'/db-backup-perms-'.bin2hex(random_bytes(4));
    config()->set('database-backup.temp_directory', $dir);

    $this->artisan('db:backup')->assertSuccessful();

    expect(fileperms($dir) & 0777)->toBe(0700);
    rmdir($dir);
});

it('fails when no connection is configured', function (): void {
    config()->set('database-backup.connection');

    $this->artisan('db:backup')->assertFailed();
});

it('fails for an unsupported driver', function (): void {
    config()->set('database.connections.weird', ['driver' => 'mongodb', 'database' => 'x']);
    config()->set('database-backup.connection', 'weird');

    $this->artisan('db:backup')->assertFailed();
});
