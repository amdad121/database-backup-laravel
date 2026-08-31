<?php

declare(strict_types=1);

use AmdadulHaq\DatabaseBackup\Restorers\MysqlRestorer;
use AmdadulHaq\DatabaseBackup\Restorers\PostgresRestorer;

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

it('builds the mysql restore command and password env', function (): void {
    $restorer = new MysqlRestorer;

    $command = $restorer->command([
        'driver' => 'mysql',
        'host' => 'db.internal',
        'port' => 3307,
        'username' => 'backup',
        'database' => 'shop',
    ], '/tmp/in.sql');

    expect($command)->toBe([
        'mysql',
        '--host=db.internal',
        '--port=3307',
        '--user=backup',
        '--execute=SOURCE /tmp/in.sql',
        'shop',
    ])->and($restorer->env(['password' => 's3cret']))->toBe(['MYSQL_PWD' => 's3cret']);
});

it('adds --socket for a mysql restore over a unix socket', function (): void {
    $command = (new MysqlRestorer)->command([
        'driver' => 'mysql',
        'database' => 'shop',
        'unix_socket' => '/var/run/mysqld/mysqld.sock',
    ], '/tmp/in.sql');

    expect($command)->toContain('--socket=/var/run/mysqld/mysqld.sock');
});
