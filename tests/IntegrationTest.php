<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/*
 * Round-trip against a real MySQL / MariaDB / PostgreSQL server. Runs only when
 * DB_BACKUP_IT_DRIVER is set (see .github/workflows/integration.yml).
 */

beforeEach(function (): void {
    $driver = (string) getenv('DB_BACKUP_IT_DRIVER');

    if ($driver === '') {
        $this->markTestSkipped('Set DB_BACKUP_IT_DRIVER to run the integration tests.');
    }

    config()->set('database.connections.it', [
        'driver' => $driver,
        'host' => '127.0.0.1',
        'port' => (string) getenv('DB_BACKUP_IT_PORT'),
        'database' => (string) getenv('DB_BACKUP_IT_DATABASE'),
        'username' => (string) getenv('DB_BACKUP_IT_USERNAME'),
        'password' => (string) getenv('DB_BACKUP_IT_PASSWORD'),
        'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
        'prefix' => '',
    ]);

    config()->set('database-backup.connection', 'it');
    config()->set('database-backup.disk', 'backups_disk');
    config()->set('database-backup.path', 'backups');
    config()->set('database-backup.compress', true);

    Storage::fake('backups_disk');

    Schema::connection('it')->dropIfExists('items');
    Schema::connection('it')->create('items', function ($table): void {
        $table->id();
        $table->string('name');
    });
    DB::connection('it')->table('items')->insert([['name' => 'alpha'], ['name' => "o'brien \"quoted\""]]);
});

afterEach(function (): void {
    if (getenv('DB_BACKUP_IT_DRIVER')) {
        Schema::connection('it')->dropIfExists('items');
    }
});

it('backs up and restores a real database', function (): void {
    $this->artisan('db:backup')->assertSuccessful();

    DB::connection('it')->table('items')->insert(['name' => 'added after backup']);
    DB::connection('it')->table('items')->where('name', 'alpha')->delete();

    $this->artisan('db:restore', ['--latest' => true, '--force' => true])->assertSuccessful();

    expect(DB::connection('it')->table('items')->orderBy('id')->pluck('name')->all())
        ->toBe(['alpha', "o'brien \"quoted\""]);
});

it('restores after the table was dropped', function (): void {
    $this->artisan('db:backup')->assertSuccessful();

    Schema::connection('it')->drop('items');

    $this->artisan('db:restore', ['--latest' => true, '--force' => true])->assertSuccessful();

    expect(DB::connection('it')->table('items')->count())->toBe(2);
});
