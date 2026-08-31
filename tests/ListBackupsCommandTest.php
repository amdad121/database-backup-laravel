<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config()->set('database-backup.connection', 'pgsql');
    config()->set('database-backup.disk', 'backups_disk');
    config()->set('database-backup.path', 'backups');

    Storage::fake('backups_disk');
});

it('lists backups for the connection, newest first', function (): void {
    Storage::disk('backups_disk')->put('backups/pgsql-app-2026-01-01_000000.sql.gz', 'old');
    Storage::disk('backups_disk')->put('backups/pgsql-app-2026-02-01_000000.sql.gz', 'new');
    Storage::disk('backups_disk')->put('backups/mysql-other-2026-02-01_000000.sql.gz', 'other');

    $this->artisan('db:backups')
        ->assertSuccessful()
        ->expectsOutputToContain('pgsql-app-2026-02-01_000000.sql.gz')
        ->expectsOutputToContain('pgsql-app-2026-01-01_000000.sql.gz')
        ->doesntExpectOutputToContain('mysql-other-2026-02-01_000000.sql.gz');
});

it('reports when there are no backups', function (): void {
    $this->artisan('db:backups')
        ->assertSuccessful()
        ->expectsOutputToContain('No backups found');
});

it('lists backups for a connection passed via --connection', function (): void {
    Storage::disk('backups_disk')->put('backups/mysql-shop-2026-02-01_000000.sql.gz', 'x');

    $this->artisan('db:backups', ['--connection' => 'mysql'])
        ->assertSuccessful()
        ->expectsOutputToContain('mysql-shop-2026-02-01_000000.sql.gz');
});
