<?php

declare(strict_types=1);

use AmdadulHaq\DatabaseBackup\Exceptions\RestoreFailedException;
use AmdadulHaq\DatabaseBackup\Restorers\MysqlRestorer;
use AmdadulHaq\DatabaseBackup\Restorers\PostgresRestorer;
use AmdadulHaq\DatabaseBackup\Restorers\SqliteRestorer;

it('builds the psql restore command and password env', function (): void {
    $restorer = new PostgresRestorer;

    $command = $restorer->command([
        'driver' => 'pgsql',
        'host' => 'pg.internal',
        'port' => 5433,
        'username' => 'backup',
        'database' => 'shop',
    ], '/tmp/in.sql');

    expect($command)->toBe([
        'psql',
        '--host=pg.internal',
        '--port=5433',
        '--username=backup',
        '--dbname=shop',
        '--no-password',
        '--single-transaction',
        '--set=ON_ERROR_STOP=on',
        '--file=/tmp/in.sql',
    ])->and($restorer->env(['password' => 'p@ss']))->toBe(['PGPASSWORD' => 'p@ss']);
});

it('builds the mysql restore command', function (): void {
    $restorer = new MysqlRestorer;

    $command = $restorer->command([
        'driver' => 'mysql',
        'host' => 'db.internal',
        'port' => 3307,
        'username' => 'backup',
        'database' => 'shop',
    ]);

    expect($command)->toBe([
        'mysql',
        '--host=db.internal',
        '--port=3307',
        '--user=backup',
        'shop',
    ]);
});

it('adds --socket for a mysql restore over a unix socket', function (): void {
    $command = (new MysqlRestorer)->command([
        'driver' => 'mysql',
        'database' => 'shop',
        'unix_socket' => '/var/run/mysqld/mysqld.sock',
    ]);

    expect($command)->toContain('--socket=/var/run/mysqld/mysqld.sock');
});

it('passes postgres ssl settings to libpq via the environment', function (): void {
    expect((new PostgresRestorer)->env(['password' => 'p', 'sslmode' => 'require', 'sslrootcert' => '/ca.pem']))->toBe([
        'PGPASSWORD' => 'p',
        'PGSSLMODE' => 'require',
        'PGSSLROOTCERT' => '/ca.pem',
    ]);
});

it('prefers an explicit mariadb client binary for the mariadb driver', function (): void {
    $command = (new MysqlRestorer(['mariadb' => '/opt/bin/mariadb']))->command(['driver' => 'mariadb', 'database' => 'shop']);

    expect($command[0])->toBe('/opt/bin/mariadb');
});

it('refuses to restore a file that is not a sqlite database', function (): void {
    $source = tempnam(sys_get_temp_dir(), 'not-sqlite');
    file_put_contents($source, 'DROP TABLE users;');

    try {
        (new SqliteRestorer)->restore(['database' => sys_get_temp_dir().'/never-written.sqlite'], $source);
    } finally {
        @unlink($source);
    }
})->throws(RestoreFailedException::class, 'is not a SQLite database');

it('removes a stale WAL file when restoring sqlite', function (): void {
    $dir = sys_get_temp_dir().'/sqlite-restore-'.bin2hex(random_bytes(4));
    mkdir($dir);
    $source = $dir.'/backup.sqlite';
    (new PDO('sqlite:'.$source))->exec('CREATE TABLE t (id INTEGER)');
    file_put_contents($dir.'/app.sqlite', 'old');
    file_put_contents($dir.'/app.sqlite-wal', 'stale');

    (new SqliteRestorer)->restore(['database' => $dir.'/app.sqlite'], $source);

    expect(file_exists($dir.'/app.sqlite-wal'))->toBeFalse()
        ->and(file_get_contents($dir.'/app.sqlite', false, null, 0, 15))->toBe('SQLite format 3');

    array_map('unlink', glob($dir.'/*'));
    rmdir($dir);
});

it('makes a newly created sqlite database readable by the web server', function (): void {
    $dir = sys_get_temp_dir().'/sqlite-restore-'.bin2hex(random_bytes(4));
    mkdir($dir);
    $source = $dir.'/backup.sqlite';
    (new PDO('sqlite:'.$source))->exec('CREATE TABLE t (id INTEGER)');
    chmod($source, 0600);

    (new SqliteRestorer)->restore(['database' => $dir.'/app.sqlite'], $source);

    expect(fileperms($dir.'/app.sqlite') & 0777)->toBe(0644);

    array_map('unlink', glob($dir.'/*'));
    rmdir($dir);
});
