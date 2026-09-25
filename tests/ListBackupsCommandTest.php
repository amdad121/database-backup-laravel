<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config()->set('database.connections.pgsql', ['driver' => 'pgsql', 'database' => 'app']);
    config()->set('database.connections.pgsql_replica', ['driver' => 'pgsql', 'database' => 'app']);
    config()->set('database-backup.connection', 'pgsql');
    config()->set('database-backup.disk', 'backups_disk');
    config()->set('database-backup.path', 'backups');

    Storage::fake('backups_disk');
});

it('lists backups for the connection, newest first', function (): void {
    Storage::disk('backups_disk')->put('backups/pgsql/pgsql-app-2026-01-01_000000.sql.gz', 'old');
    Storage::disk('backups_disk')->put('backups/pgsql/pgsql-app-2026-02-01_000000.sql.gz', 'new');
    Storage::disk('backups_disk')->put('backups/mysql/mysql-other-2026-02-01_000000.sql.gz', 'other');

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
    Storage::disk('backups_disk')->put('backups/mysql/mysql-shop-2026-02-01_000000.sql.gz', 'x');

    $this->artisan('db:backups', ['--connection' => 'mysql'])
        ->assertSuccessful()
        ->expectsOutputToContain('mysql-shop-2026-02-01_000000.sql.gz');
});

it('does not list backups of a connection whose name shares a prefix', function (): void {
    Storage::disk('backups_disk')->put('backups/pgsql-replica-app-2026-02-01_000000.sql.gz', 'legacy');
    Storage::disk('backups_disk')->put('backups/pgsql-replica/pgsql-replica-app-2026-02-01_000000.sql.gz', 'x');

    $this->artisan('db:backups')
        ->assertSuccessful()
        ->expectsOutputToContain('No backups found');
});

it('lists legacy backups stored directly in the root folder', function (): void {
    Storage::disk('backups_disk')->put('backups/pgsql-app-2025-01-01_000000.sql.gz', str_repeat('x', 2048));

    $this->artisan('db:backups')
        ->assertSuccessful()
        ->expectsOutputToContain('backups/pgsql-app-2025-01-01_000000.sql.gz | 2.0 KB');
});

it('resolves the database name from a connection url', function (): void {
    config()->set('database.connections.pg_url', ['driver' => 'pgsql', 'url' => 'pgsql://user:secret@db.example.com:5432/shopdb']);
    Storage::disk('backups_disk')->put('backups/pg_url/pg-url-shopdb-2026-01-01_000000.sql.gz', 'x');

    $this->artisan('db:backups', ['--connection' => 'pg_url'])
        ->assertSuccessful()
        ->expectsOutputToContain('pg-url-shopdb-2026-01-01_000000.sql.gz');
});
